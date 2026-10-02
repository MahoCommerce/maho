<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function keyColumnsRootName(): string
{
    return (string) Mage::getModel('catalog/category')->load(2)->getName();
}

function keyColumnsCategory(string $urlKey, int $parentId = 2, array $data = []): Mage_Catalog_Model_Category
{
    $category = Mage::getModel('catalog/category')->setStoreId(0);
    $category->addData($data)
        ->setName(ucfirst($urlKey))
        ->setUrlKey($urlKey)
        ->setIsActive(1)
        ->setPath(Mage::getModel('catalog/category')->load($parentId)->getPath())
        ->setAttributeSetId($category->getDefaultAttributeSetId())
        ->save();
    return Mage::getModel('catalog/category')->setStoreId(0)->load($category->getId());
}

/**
 * @param list<list<string>> $rows
 */
function keyColumnsImport(array $rows, string $behavior = Mage_ImportExport_Model_Import::BEHAVIOR_APPEND): Mage_ImportExport_Model_Import_Entity_Category
{
    $path = tempnam(sys_get_temp_dir(), 'key_columns');
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    $entity = Mage::getModel('importexport/import_entity_category');
    $entity->setSource(Mage::getModel('importexport/import_adapter_csv', $path));
    $entity->setParameters(['behavior' => $behavior]);
    $entity->validateData();
    $entity->importData();
    unlink($path);
    return $entity;
}

function keyColumnsCleanup(): void
{
    foreach (Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', ['like' => 'kc-%']) as $category) {
        $category = Mage::getModel('catalog/category')->load($category->getId());
        if ($category->getId()) {
            $category->delete();
        }
    }
    foreach (Mage::getResourceModel('cms/block_collection')->addFieldToFilter('identifier', 'kc-landing') as $block) {
        $block->delete();
    }
}

beforeEach(fn() => keyColumnsCleanup());
afterEach(fn() => keyColumnsCleanup());

it('exports the key of each category next to its IDs, with portable values', function (): void {
    $block = Mage::getModel('cms/block')->setData(['identifier' => 'kc-landing', 'title' => 'KC Landing', 'content' => 'x', 'stores' => [0]])->save();
    $parent = keyColumnsCategory('kc-parent');
    $child = keyColumnsCategory('kc-child', (int) $parent->getId(), [
        'display_mode' => Mage_Catalog_Model_Category::DM_MIXED,
        'landing_page' => $block->getId(),
    ]);
    $csv = tempnam(sys_get_temp_dir(), 'key_columns_export');

    Mage::getModel('importexport/export')
        ->setData(['entity' => 'catalog_category', 'file_format' => 'csv', 'export_filter' => ['url_key' => 'kc-child']])
        ->setDestination($csv)
        ->exportFile();

    $handle = fopen($csv, 'r');
    $header = fgetcsv($handle, escape: '\\');
    $row = array_combine($header, fgetcsv($handle, escape: '\\'));
    fclose($handle);
    unlink($csv);
    expect(array_slice($header, 0, 5))->toBe(['category_id', 'parent_id', '_store', '_root', '_path']);
    expect($row['category_id'])->toBe((string) $child->getId());
    expect($row['_root'])->toBe(keyColumnsRootName());
    expect($row['_path'])->toBe('kc-parent/kc-child');
    expect($row['display_mode'])->toBe(Mage_Catalog_Model_Category::DM_MIXED);
    expect($row['landing_page'])->toBe('kc-landing');
});

it('creates the category of a key and leaves alone the category that a foreign category_id names', function (): void {
    $local = keyColumnsCategory('kc-local');

    $entity = keyColumnsImport([
        ['category_id', 'parent_id', '_store', '_root', '_path', 'name'],
        [(string) $local->getId(), '99999', '', keyColumnsRootName(), 'kc-new', 'KC New'],
    ]);

    expect($entity->getErrorsCount())->toBe(0);
    $reloaded = Mage::getModel('catalog/category')->setStoreId(0)->load($local->getId());
    expect($reloaded->getName())->toBe('Kc-local')
        ->and((int) $reloaded->getParentId())->toBe(2);
    $created = Mage::getResourceModel('catalog/category_collection')
        ->addAttributeToFilter('url_key', 'kc-new')
        ->addFieldToFilter('parent_id', 2)
        ->getFirstItem();
    expect($created->getId())->not->toBeNull()
        ->and((int) $created->getId())->not->toBe((int) $local->getId());
});

it('moves a category when category_id and the key name the same category', function (): void {
    $moving = keyColumnsCategory('kc-moving');
    $target = keyColumnsCategory('kc-target');

    $entity = keyColumnsImport([
        ['category_id', 'parent_id', '_store', '_root', '_path', 'name'],
        [(string) $moving->getId(), (string) $target->getId(), '', keyColumnsRootName(), 'kc-moving', ''],
    ]);

    expect($entity->getErrorsCount())->toBe(0);
    $moved = Mage::getModel('catalog/category')->load($moving->getId());
    expect((int) $moved->getParentId())->toBe((int) $target->getId())
        ->and($moved->getPath())->toBe($target->getPath() . '/' . $moving->getId());
});

it('rejects a move below the category itself', function (): void {
    $parent = keyColumnsCategory('kc-loop');
    $child = keyColumnsCategory('kc-loop-child', (int) $parent->getId());

    $entity = keyColumnsImport([
        ['category_id', 'parent_id', '_store', '_root', '_path'],
        [(string) $parent->getId(), (string) $child->getId(), '', keyColumnsRootName(), 'kc-loop'],
    ]);

    expect($entity->getErrorMessages())->toHaveKey('Circular reference detected in category path "' . keyColumnsRootName() . '/kc-loop"');
    expect((int) Mage::getModel('catalog/category')->load($parent->getId())->getParentId())->toBe(2);
});

it('rejects a category_id that does not exist when the row has no key', function (): void {
    $entity = keyColumnsImport([
        ['category_id', 'parent_id', '_store', 'name', 'url_key'],
        ['99999', '2', '', 'KC Ghost', 'kc-ghost'],
    ]);

    expect($entity->getErrorMessages())->toHaveKey('Category ID "99999" is invalid or does not exist');
    expect(Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', 'kc-ghost')->count())->toBe(0);
});

it('deletes a category and its children by the key', function (): void {
    $parent = keyColumnsCategory('kc-doomed');
    $child = keyColumnsCategory('kc-doomed-child', (int) $parent->getId());

    $entity = keyColumnsImport([['_root', '_path'], [keyColumnsRootName(), 'kc-doomed']], Mage_ImportExport_Model_Import::BEHAVIOR_DELETE);

    expect($entity->getErrorsCount())->toBe(0);
    expect(Mage::getModel('catalog/category')->load($parent->getId())->getId())->toBeNull()
        ->and(Mage::getModel('catalog/category')->load($child->getId())->getId())->toBeNull();
});
