<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('returns the type model of the product type', function () {
    $product = new Maho\DataObject(['type_id' => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE]);

    expect(Mage_Catalog_Model_Product_Type::factory($product))->toBeInstanceOf(Mage_Catalog_Model_Product_Type_Simple::class);
});

it('rejects a type model that does not extend the product type abstract', function () {
    $types = new ReflectionProperty(Mage_Catalog_Model_Product_Type::class, '_types');
    $original = $types->getValue();
    $types->setValue(null, ['broken' => ['model' => 'catalog/product']]);

    try {
        expect(fn() => Mage_Catalog_Model_Product_Type::factory(new Maho\DataObject(['type_id' => 'broken'])))
            ->toThrow(UnexpectedValueException::class);
    } finally {
        $types->setValue(null, $original);
    }
});
