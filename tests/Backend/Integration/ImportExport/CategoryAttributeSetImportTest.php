<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * @param list<list<string>> $rows
 */
function categorySetImport(array $rows): Mage_ImportExport_Model_Import_Entity_Category
{
    $path = tempnam(sys_get_temp_dir(), 'category_set');
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    $entity = Mage::getModel('importexport/import_entity_category');
    $entity->setSource(Mage::getModel('importexport/import_adapter_csv', $path));
    $entity->setParameters(['behavior' => Mage_ImportExport_Model_Import::BEHAVIOR_APPEND]);
    $entity->validateData();
    $entity->importData();
    unlink($path);
    return $entity;
}

function categorySetImportFind(string $urlKey): Mage_Catalog_Model_Category
{
    $id = (int) Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', $urlKey)->getFirstItem()->getId();
    return Mage::getModel('catalog/category')->setStoreId(0)->load($id);
}

function categorySetImportRootName(): string
{
    return (string) Mage::getModel('catalog/category')->load(2)->getName();
}

beforeEach(function () {
    $this->suffix = substr(uniqid(), -6);
    $type = Mage::getSingleton('eav/config')->getEntityType(Mage_Catalog_Model_Category::ENTITY);
    $this->defaultSetId = (int) $type->getDefaultAttributeSetId();
    $this->set = Mage::getModel('eav/entity_attribute_set')
        ->setEntityTypeId((int) $type->getId())
        ->setAttributeSetName('Pest Brand ' . $this->suffix);
    $this->set->save();
    $this->set->initFromSkeleton($this->defaultSetId)->save();
    $this->code = 'pest_story_' . $this->suffix;
    $this->attribute = Mage::getModel('catalog/resource_eav_attribute')->setData([
        'entity_type_id' => (int) $type->getId(),
        'attribute_code' => $this->code,
        'backend_type' => 'varchar',
        'frontend_input' => 'text',
        'frontend_label' => 'Pest Story',
        'is_user_defined' => 1,
        'attribute_set_id' => (int) $this->set->getId(),
        'attribute_group_id' => (int) Mage::getResourceModel('eav/entity_attribute_group_collection')
            ->setAttributeSetFilter((int) $this->set->getId())
            ->setSortOrder()
            ->getFirstItem()
            ->getId(),
    ]);
    $this->attribute->save();
    Mage::getSingleton('eav/config')->clear();
});

afterEach(function () {
    foreach (Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', ['like' => 'cs-' . $this->suffix . '%']) as $category) {
        $category = Mage::getModel('catalog/category')->load($category->getId());
        if ($category->getId()) {
            $category->delete();
        }
    }
    $this->attribute->delete();
    $this->set->delete();
    Mage::getSingleton('eav/config')->clear();
});

it('gives a new category the set of the column, or else the set of its parent', function () {
    $brand = 'cs-' . $this->suffix . '-brand';
    $child = 'cs-' . $this->suffix . '-child';
    $plain = 'cs-' . $this->suffix . '-plain';

    $entity = categorySetImport([
        ['_root', '_path', 'name', '_attribute_set', $this->code],
        [categorySetImportRootName(), $brand, 'CS Brand', $this->set->getAttributeSetName(), 'Since 1987'],
        [categorySetImportRootName(), "{$brand}/{$child}", 'CS Child', '', 'Hand-made'],
        [categorySetImportRootName(), $plain, 'CS Plain', '', ''],
    ]);

    expect($entity->getErrorsCount())->toBe(0)
        ->and(categorySetImportFind($brand)->getAttributeSetId())->toBe((int) $this->set->getId())
        ->and(categorySetImportFind($brand)->getData($this->code))->toBe('Since 1987')
        ->and(categorySetImportFind($child)->getAttributeSetId())->toBe((int) $this->set->getId())
        ->and(categorySetImportFind($child)->getData($this->code))->toBe('Hand-made')
        ->and(categorySetImportFind($plain)->getAttributeSetId())->toBe((int) Mage::getModel('catalog/category')->load(2)->getAttributeSetId() ?: $this->defaultSetId);
});

it('refuses a value of an attribute that is not in the set of the category', function () {
    $plain = 'cs-' . $this->suffix . '-refused';

    $entity = categorySetImport([
        ['_root', '_path', 'name', $this->code],
        [categorySetImportRootName(), $plain, 'CS Refused', 'Lost tonight'],
    ]);

    expect($entity->getErrorMessages())->toHaveKey('Attribute "' . $this->code . '" is not in the attribute set of the category')
        ->and(categorySetImportFind($plain)->getId())->toBeNull();
});

it('refuses a set name that is not a set of categories', function () {
    $entity = categorySetImport([
        ['_root', '_path', 'name', '_attribute_set'],
        [categorySetImportRootName(), 'cs-' . $this->suffix . '-unknown', 'CS Unknown', 'No Such Set'],
    ]);

    expect($entity->getErrorMessages())->toHaveKey('Attribute set "No Such Set" is not a set of categories');
});

it('exports the name of the set of each category', function () {
    $brand = 'cs-' . $this->suffix . '-export';
    categorySetImport([
        ['_root', '_path', 'name', '_attribute_set'],
        [categorySetImportRootName(), $brand, 'CS Export', $this->set->getAttributeSetName()],
    ]);
    $csv = tempnam(sys_get_temp_dir(), 'category_set_export');

    Mage::getModel('importexport/export')
        ->setData(['entity' => 'catalog_category', 'file_format' => 'csv', 'export_filter' => ['url_key' => $brand]])
        ->setDestination($csv)
        ->exportFile();

    $handle = fopen($csv, 'r');
    $header = fgetcsv($handle, escape: '\\');
    $row = array_combine($header, fgetcsv($handle, escape: '\\'));
    fclose($handle);
    unlink($csv);

    expect($row['_attribute_set'])->toBe($this->set->getAttributeSetName());
});
