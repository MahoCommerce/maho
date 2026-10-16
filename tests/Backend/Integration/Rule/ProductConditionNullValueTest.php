<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A store view row with a NULL value reaches validate() through the values that collectValidatedAttributes() reads.
 */

/**
 * Give $condition the values of one product, as collectValidatedAttributes() does for a store view attribute.
 *
 * @param array<int, string|null> $valuesByStore
 */
function nullValueValidate(Mage_Rule_Model_Condition_Product_Abstract $condition, array $valuesByStore): bool
{
    $productId = 999999;
    (function () use ($productId, $valuesByStore): void {
        $this->_entityAttributeValues = [$productId => $valuesByStore];
    })->call($condition);

    return $condition->validate(Mage::getModel('catalog/product')->setId($productId));
}

it('skips a NULL store view row of a multiselect attribute and matches the next row', function (): void {
    $code = 'rule_ms_' . uniqid();
    Mage::getResourceModel('catalog/setup', 'catalog_setup')->addAttribute(Mage_Catalog_Model_Product::ENTITY, $code, [
        'type' => 'varchar',
        'backend' => 'eav/entity_attribute_backend_array',
        'input' => 'multiselect',
        'label' => $code,
        'source' => 'eav/entity_attribute_source_table',
        'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'required' => false,
        'user_defined' => true,
        'option' => ['values' => ['A', 'B']],
    ]);
    // The setup writes the options with raw SQL, so the cached attribute does not have them
    Mage::getSingleton('eav/config')->clear();

    try {
        $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code);
        $optionA = (string) array_column($attribute->getSource()->getAllOptions(false), 'value', 'label')['A'];

        $condition = Mage::getModel('salesrule/rule_condition_product')
            ->setAttribute($code)
            ->setOperator('()')
            ->setValue($optionA);

        expect(nullValueValidate($condition, [1 => null, 0 => $optionA]))->toBeTrue()
            ->and(nullValueValidate($condition, [1 => null]))->toBeFalse();
    } finally {
        Mage::getModel('catalog/resource_eav_attribute')
            ->loadByCode(Mage_Catalog_Model_Product::ENTITY, $code)
            ->delete();
    }
});

it('does not match a NULL store view row of a datetime attribute', function (): void {
    $condition = Mage::getModel('salesrule/rule_condition_product')
        ->setAttribute('news_from_date')
        ->setOperator('==')
        ->setValue('2026-01-01');

    expect(nullValueValidate($condition, [1 => null]))->toBeFalse();
});
