<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin product form sets an attribute to false when "Use Default Value" is checked (Product service useDefault()).
 */
it('adds a product whose name is set to use the default value to a stock item', function () {
    $product = Mage::getModel('catalog/product')->setId(1)->setName(false);
    $item = Mage::getModel('cataloginventory/stock_item')->setProduct($product);

    expect($item->getProductName())->toBeNull();
});

it('adds the name of a product to a stock item', function () {
    $product = Mage::getModel('catalog/product')->setId(1)->setName('Pest Name');
    $item = Mage::getModel('cataloginventory/stock_item')->setProduct($product);

    expect($item->getProductName())->toBe('Pest Name');
});
