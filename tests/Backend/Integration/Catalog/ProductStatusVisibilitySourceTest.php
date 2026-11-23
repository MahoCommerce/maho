<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The source of an attribute must extend Mage_Eav_Model_Entity_Attribute_Source_Abstract. The product attributes
 * status and visibility used Mage_Catalog_Model_Product_Status and Mage_Catalog_Model_Product_Visibility, which do not.
 */

it('gives the product attributes status and visibility a source that lists their options', function (string $code, string $sourceClass, array $values) {
    $source = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $code)->getSource();

    expect($source)->toBeInstanceOf($sourceClass)
        ->and(array_column($source->getAllOptions(false), 'value'))->toBe($values)
        ->and(array_column($source->getAllOptions(), 'value'))->toBe(['', ...$values])
        ->and($source->getOptionText($values[0]))->toBe($source->getOptionArray()[$values[0]])
        ->and($source->getOptionText(99))->toBeFalse();
})->with([
    'status' => ['status', Mage_Catalog_Model_Product_Attribute_Source_Status::class, [
        Mage_Catalog_Model_Product_Status::STATUS_ENABLED,
        Mage_Catalog_Model_Product_Status::STATUS_DISABLED,
    ]],
    'visibility' => ['visibility', Mage_Catalog_Model_Product_Attribute_Source_Visibility::class, [
        Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE,
        Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG,
        Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
        Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
    ]],
]);

it('refuses a source model that does not extend the source base class', function () {
    $attribute = Mage::getModel('catalog/resource_eav_attribute')
        ->setAttributeCode('pest_status')
        ->setSourceModel('catalog/product_status');

    expect(fn() => $attribute->getSource())->toThrow(Mage_Eav_Exception::class, 'catalog/product_status');
});
