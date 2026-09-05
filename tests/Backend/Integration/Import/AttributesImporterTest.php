<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\Importer\Attributes;
use Maho\Import\Importer\AttributeSets;
use Maho\Import\RowException;

uses(Tests\MahoBackendTestCase::class);

/**
 * @param list<list<string>> $rows
 */
function attributesCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'attributes') . '.csv';
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    return $path;
}

function attributesCleanup(): void
{
    foreach (['imp_finish', 'imp_note'] as $code) {
        $attribute = Mage::getModel('catalog/resource_eav_attribute')->loadByCode('catalog_product', $code);
        if ($attribute->getId()) {
            $attribute->delete();
        }
    }
    $entityTypeId = (int) Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getId();
    $set = Mage::getResourceModel('eav/entity_attribute_set_collection')
        ->setEntityTypeFilter($entityTypeId)
        ->addFieldToFilter('attribute_set_name', 'Import Set')
        ->getFirstItem();
    if ($set->getId()) {
        $set->delete();
    }
    Mage::getSingleton('eav/config')->clear();
}

beforeEach(fn() => attributesCleanup());
afterEach(fn() => attributesCleanup());

it('creates a set, attributes with options and swatches, assigns them, and reruns cleanly', function (): void {
    $sets = attributesCsv([['name', 'skeleton'], ['Import Set', 'Default']]);
    expect((new AttributeSets())->import($sets)->created)->toBe(1);
    expect((new AttributeSets())->import($sets)->created)->toBe(0);

    $attributes = attributesCsv([
        ['code', 'label', 'input', 'filterable', 'is_configurable', 'sets', 'group', 'swatch_attribute'],
        ['imp_finish', 'Finish', 'select', '1', '1', 'Import Set', 'Import Group', '1'],
        ['imp_note', 'Note', 'textarea', '', '', '*', '', ''],
    ]);
    $options = attributesCsv([
        ['attribute_code', 'label', 'sort_order', 'swatch'],
        ['imp_finish', 'Matte', '10', '#333333'],
        ['imp_finish', 'Gloss', '20', ''],
    ]);
    $result = (new Attributes())->import($attributes, [Attributes::OPTION_OPTIONS_CSV => $options]);
    expect($result->created)->toBe(4);

    $finish = Mage::getModel('catalog/resource_eav_attribute')->loadByCode('catalog_product', 'imp_finish');
    expect($finish->getFrontendInput())->toBe('select');
    expect((int) $finish->getIsFilterable())->toBe(1);
    expect((int) $finish->getIsConfigurable())->toBe(1);
    expect((int) $finish->getIsUserDefined())->toBe(1);
    $labels = array_column($finish->getSource()->getAllOptions(false), 'label');
    expect($labels)->toBe(['Matte', 'Gloss']);
    $matteId = (int) $finish->getSource()->getOptionId('Matte');
    $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
    $swatch = $connection->fetchOne($connection->select()
        ->from(Mage::getSingleton('core/resource')->getTableName('eav/attribute_option_swatch'), ['value'])
        ->where('option_id = ?', $matteId));
    expect($swatch)->toBe('#333333');

    $entityTypeId = (int) Mage::getSingleton('eav/config')->getEntityType('catalog_product')->getId();
    $setId = (int) Mage::getResourceModel('eav/entity_attribute_set_collection')->setEntityTypeFilter($entityTypeId)->addFieldToFilter('attribute_set_name', 'Import Set')->getFirstItem()->getId();
    $inSet = Mage::getResourceModel('catalog/product_attribute_collection')->setAttributeSetFilter($setId)->addFieldToFilter('attribute_code', ['in' => ['imp_finish', 'imp_note']])->count();
    expect($inSet)->toBe(2);
    $inDefault = Mage::getResourceModel('catalog/product_attribute_collection')->setAttributeSetFilter(4)->addFieldToFilter('attribute_code', 'imp_note')->count();
    expect($inDefault)->toBe(1);
    $swatchIds = Mage::getResourceModel('core/config_data_collection')
        ->addFieldToFilter('path', 'configswatches/general/swatch_attributes')
        ->addFieldToFilter('scope', 'default')
        ->getFirstItem()
        ->getValue();
    expect(explode(',', (string) $swatchIds))->toContain((string) $finish->getId());

    $again = (new Attributes())->import($attributes, [Attributes::OPTION_OPTIONS_CSV => $options]);
    expect($again->created)->toBe(0);
    expect($again->updated)->toBe(4);
    $finish = Mage::getModel('catalog/resource_eav_attribute')->loadByCode('catalog_product', 'imp_finish');
    expect(count($finish->getSource()->getAllOptions(false)))->toBe(2);

    unlink($sets);
    unlink($attributes);
    unlink($options);
});

it('rejects unknown inputs, sets and bad swatches before writing', function (): void {
    $importer = new Attributes();

    $path = attributesCsv([['code', 'input'], ['imp_note', 'wysiwyg']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, "line 2: input 'wysiwyg'");
    unlink($path);

    $path = attributesCsv([['code', 'sets'], ['imp_note', 'No Such Set']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, "unknown attribute set 'No Such Set'");
    unlink($path);

    $path = attributesCsv([['code', 'input'], ['imp_finish', 'select']]);
    $options = attributesCsv([['attribute_code', 'label', 'swatch'], ['imp_finish', 'Matte', 'grey']]);
    expect(fn() => $importer->validate($path, [Attributes::OPTION_OPTIONS_CSV => $options]))->toThrow(RowException::class, "swatch 'grey'");
    unlink($path);
    unlink($options);

    expect(Mage::getModel('catalog/resource_eav_attribute')->loadByCode('catalog_product', 'imp_note')->getId())->toBeEmpty();
});
