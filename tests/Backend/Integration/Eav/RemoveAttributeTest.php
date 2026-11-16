<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A setup script that calls startSetup() turns the foreign keys off, so the database does not delete the rows
 * of a removed attribute (#1851). The test removes an attribute in that state and reads the rows that refer to it.
 */

const REMOVE_ATTRIBUTE_CODE = 'remove_attribute_test';

/**
 * @return array{values: int, options: int, optionValues: int, setRows: int}
 */
function removeAttributeRowCounts(Mage_Eav_Model_Entity_Setup $setup, int $attributeId, array $optionIds): array
{
    $adapter = $setup->getConnection();
    $count = fn(string $table, string $column, array $ids): int => (int) $adapter->fetchOne(
        $adapter->select()
            ->from($setup->getTable($table), [new Maho\Db\Expr('COUNT(*)')])
            ->where("{$column} IN (?)", $ids),
    );

    return [
        'values' => $count('catalog_product_entity_int', 'attribute_id', [$attributeId]),
        'options' => $count('eav/attribute_option', 'attribute_id', [$attributeId]),
        'optionValues' => $count('eav/attribute_option_value', 'option_id', $optionIds),
        'setRows' => $count('eav/entity_attribute', 'attribute_id', [$attributeId]),
    ];
}

beforeEach(function () {
    $this->setup = Mage::getResourceModel('catalog/setup', 'catalog_setup');
    $this->setup->removeAttribute(Mage_Catalog_Model_Product::ENTITY, REMOVE_ATTRIBUTE_CODE);
    $this->setup->addAttribute(Mage_Catalog_Model_Product::ENTITY, REMOVE_ATTRIBUTE_CODE, [
        'type' => 'int',
        'input' => 'select',
        'label' => 'Remove Attribute Test',
        'group' => 'General',
        'user_defined' => true,
        'option' => ['values' => ['Red', 'Blue']],
    ]);

    $adapter = $this->setup->getConnection();
    $this->attributeId = (int) $this->setup->getAttributeId(Mage_Catalog_Model_Product::ENTITY, REMOVE_ATTRIBUTE_CODE);
    $this->optionIds = array_map(intval(...), $adapter->fetchCol(
        $adapter->select()
            ->from($this->setup->getTable('eav/attribute_option'), ['option_id'])
            ->where('attribute_id = ?', $this->attributeId),
    ));
    $adapter->insert($this->setup->getTable('catalog_product_entity_int'), [
        'entity_type_id' => (int) $this->setup->getEntityTypeId(Mage_Catalog_Model_Product::ENTITY),
        'attribute_id' => $this->attributeId,
        'store_id' => 0,
        'entity_id' => (int) Mage::getResourceModel('catalog/product_collection')->getFirstItem()->getId(),
        'value' => $this->optionIds[0],
    ]);
});

afterEach(function () {
    $this->setup->removeAttribute(Mage_Catalog_Model_Product::ENTITY, REMOVE_ATTRIBUTE_CODE);
});

it('deletes the values and the options of an attribute that a script removes with the foreign keys off', function () {
    $before = removeAttributeRowCounts($this->setup, $this->attributeId, $this->optionIds);
    expect($before['values'])->toBe(1)
        ->and($before['options'])->toBe(2)
        ->and($before['optionValues'])->toBe(2)
        ->and($before['setRows'])->toBeGreaterThan(0);

    $this->setup->startSetup();
    $this->setup->removeAttribute(Mage_Catalog_Model_Product::ENTITY, REMOVE_ATTRIBUTE_CODE);
    $this->setup->endSetup();

    expect(removeAttributeRowCounts($this->setup, $this->attributeId, $this->optionIds))
        ->toBe(['values' => 0, 'options' => 0, 'optionValues' => 0, 'setRows' => 0]);
});
