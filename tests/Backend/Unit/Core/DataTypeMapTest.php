<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A value takes the PHP type of its column, or of its typed setter, when it enters
 * setData(). The type is then the same on every database backend.
 */

it('types a loaded row by the column types of the declarative schema', function () {
    $item = Mage::getModel('cataloginventory/stock_item')->getCollection()->setPageSize(1)->getFirstItem();
    expect($item->getData('item_id'))->toBeInt()
        ->and($item->getData('qty'))->toBeFloat()
        ->and($item->getData('is_in_stock'))->toBeInt();

    $loaded = Mage::getModel('cataloginventory/stock_item')->load($item->getId());
    expect($loaded->getData('qty'))->toBeFloat()
        ->and($loaded->getData('product_id'))->toBeInt();
});

it('casts scalars on setData by column type and passes the rest through', function () {
    $item = Mage::getModel('cataloginventory/stock_item');
    $item->setData('qty', '12.5000');
    $item->setData('product_id', '7');
    $item->setData('is_in_stock', '');
    $item->setData('low_stock_date', '2026-01-01 00:00:00');
    $item->setData('min_qty', null);
    $item->setData('notify_stock_qty', new Maho\Db\Expr('qty + 1'));

    expect($item->getData('qty'))->toBe(12.5)
        ->and($item->getData('product_id'))->toBe(7)
        ->and($item->getData('is_in_stock'))->toBe(0)
        ->and($item->getData('low_stock_date'))->toBe('2026-01-01 00:00:00')
        ->and($item->getData('min_qty'))->toBeNull()
        ->and($item->getData('notify_stock_qty'))->toBeInstanceOf(Maho\Db\Expr::class);
});

it('casts the array form of setData and addData', function () {
    $item = Mage::getModel('cataloginventory/stock_item');
    $item->setData(['qty' => '1.5', 'product_id' => '3']);
    $item->addData(['min_qty' => '2']);

    expect($item->getData('qty'))->toBe(1.5)
        ->and($item->getData('product_id'))->toBe(3)
        ->and($item->getData('min_qty'))->toBe(2.0);
});

it('routes array access writes through setData', function () {
    $item = Mage::getModel('cataloginventory/stock_item');
    $item['product_id'] = '9';

    expect($item->getData('product_id'))->toBe(9)
        ->and($item->hasDataChanges())->toBeTrue();
});

it('types a class without a table by its typed setters', function () {
    $request = new Mage_Shipping_Model_Rate_Request();
    $request->setData('dest_region_id', '5');
    $request->setData('package_weight', '2.5');
    $request->setData('free_shipping', '1');
    $request->setData('dest_postcode', '0123');

    expect($request->getData('dest_region_id'))->toBe(5)
        ->and($request->getData('package_weight'))->toBe(2.5)
        ->and($request->getData('free_shipping'))->toBeTrue()
        ->and($request->getData('dest_postcode'))->toBe('0123');
});

it('types EAV attribute values by their backend type', function () {
    $product = Mage::getModel('catalog/product')->getCollection()
        ->addAttributeToSelect(['price', 'status'])
        ->addAttributeToFilter('price', ['notnull' => true])
        ->setPageSize(1)
        ->getFirstItem();
    expect($product->getData('price'))->toBeFloat()
        ->and($product->getData('status'))->toBeInt();

    $loaded = Mage::getModel('catalog/product')->load($product->getId());
    expect($loaded->getData('price'))->toBeFloat()
        ->and($loaded->getData('status'))->toBeInt()
        ->and($loaded->getData('sku'))->toBeString();
});
