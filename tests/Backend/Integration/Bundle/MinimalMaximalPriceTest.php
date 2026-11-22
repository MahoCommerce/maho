<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('returns the indexed minimal and maximal price of a bundle', function () {
    $product = Mage::getModel('catalog/product')->setStoreId(1)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_BUNDLE)
        ->setData('min_price', 10.0)
        ->setData('max_price', 25.0)
        ->setTaxPercent(0.0);
    $price = Mage::getModel('bundle/product_price');

    expect($price->getMinimalPrice($product))->toBe(10.0)
        ->and($price->getMaximalPrice($product))->toBe(25.0);
});
