<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A configurable product whose own Stock Availability is In Stock, and that the stock index
 * marks as out of stock because all its children are sold out.
 */
function soldOutConfigurable(): Mage_Catalog_Model_Product
{
    $product = Mage::getResourceModel('catalog/product_collection')
        ->setStoreId(1)
        ->addAttributeToSelect('*')
        ->addAttributeToFilter('type_id', 'configurable')
        ->setPageSize(1)
        ->getFirstItem();
    expect((int) $product->getId())->toBeGreaterThan(0);

    return $product
        ->setStockItem(Mage::getModel('cataloginventory/stock_item')->setIsInStock(true)->setQty(0))
        ->setIsSalable(false);
}

describe('Feed stock status of a composite product with all children sold out', function () {
    it('writes out of stock in the feed row', function () {
        $feed = Mage::getModel('feedmanager/feed')->setPlatform('google')->setStoreId(1);
        $row = (new Maho_FeedManager_Model_Mapper($feed))->extractProductData(soldOutConfigurable());

        expect($row['is_in_stock'])->toBe(0)
            ->and($row['stock_status'])->toBe(0);
    });

    it('fails a Stock Availability condition that asks for in stock', function () {
        $condition = Mage::getModel('feedmanager/rule_condition_product')
            ->setAttribute('is_in_stock')
            ->setOperator('==')
            ->setValue('1');

        expect($condition->validate(soldOutConfigurable()))->toBeFalse();
    });

    it('outputs 0 for the Is In Stock attribute of a dynamic rule', function () {
        $method = new ReflectionMethod(Maho_FeedManager_Model_DynamicRule::class, '_getProductAttributeValue');

        expect($method->invoke(Mage::getModel('feedmanager/dynamicRule'), soldOutConfigurable(), 'is_in_stock'))
            ->toBe('0');
    });
});
