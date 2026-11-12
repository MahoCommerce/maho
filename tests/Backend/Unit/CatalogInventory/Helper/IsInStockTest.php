<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogInventory
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function isInStockItem(bool $flag): Mage_CatalogInventory_Model_Stock_Item
{
    // getIsInStock() is always true for an item that does not manage stock
    return Mage::getModel('cataloginventory/stock_item')
        ->setUseConfigManageStock(false)
        ->setManageStock()
        ->setIsInStock($flag);
}

function isInStockProduct(int $storeId, bool $flag): Mage_Catalog_Model_Product
{
    return Mage::getModel('catalog/product')
        ->setId(1)
        ->setTypeId('configurable')
        ->setStoreId($storeId)
        ->setStockItem(isInStockItem($flag));
}

describe('Mage_CatalogInventory_Helper_Data::isInStock()', function () {
    it('takes the stock index value over the flag of the product', function (bool $flag, bool $indexed) {
        $product = isInStockProduct(1, $flag)->setIsSalable($indexed);

        expect(Mage::helper('cataloginventory')->isInStock($product))->toBe($indexed);
    })->with([
        'all children sold out' => [true, false],
        'stock not managed' => [false, true],
    ]);

    it('takes the flag of the product when the index has no row', function (bool $flag) {
        $product = isInStockProduct(1, $flag)->setIsSalable(null);

        expect(Mage::helper('cataloginventory')->isInStock($product))->toBe($flag);
    })->with([true, false]);

    it('takes the flag of the product in the admin store, which has no index rows', function (bool $flag) {
        $product = isInStockProduct(0, $flag);

        expect(Mage::helper('cataloginventory')->isInStock($product))->toBe($flag);
    })->with([true, false]);

    it('prefers the stock item that the caller passes', function () {
        $product = isInStockProduct(0, false);
        expect(Mage::helper('cataloginventory')->isInStock($product, isInStockItem(true)))->toBeTrue();
    });
});
