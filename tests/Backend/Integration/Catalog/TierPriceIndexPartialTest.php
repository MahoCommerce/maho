<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function tierIndexProduct(string $skuPrefix, float $tierPrice): Mage_Catalog_Model_Product
{
    return createPriceWebsiteProduct($skuPrefix, 10.0, null, [
        'tier_price' => [[
            'website_id' => 0,
            'cust_group' => Mage_Customer_Model_Group::CUST_GROUP_ALL,
            'price_qty' => 2,
            'price' => $tierPrice,
        ]],
    ]);
}

function tierIndexInvoke(string $method, mixed ...$arguments): void
{
    $resource = Mage::getResourceSingleton('catalog/product_indexer_price');
    (new ReflectionMethod($resource, $method))->invoke($resource, ...$arguments);
}

function tierIndexRowCount(int $productId): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    return (int) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('catalog/product_index_tier_price'), [new Maho\Db\Expr('COUNT(*)')])
            ->where('entity_id = ?', $productId),
    );
}

function tierIndexIndexedTierPrice(int $productId): ?float
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $value = $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('catalog/product_index_price'), ['tier_price'])
            ->where('entity_id = ?', $productId)
            ->where('website_id = ?', 1)
            ->where('customer_group_id = ?', Mage_Customer_Model_Group::NOT_LOGGED_IN_ID),
    );
    return $value === false || $value === null ? null : (float) $value;
}

beforeEach(function () {
    $this->products = [];
});

afterEach(function () {
    foreach ($this->products as $product) {
        $product->delete();
    }
});

it('keeps the tier prices of other products when it rebuilds the tier prices of one product', function () {
    $this->products[] = $first = tierIndexProduct('tier-index-first', 8.0);
    $this->products[] = $second = tierIndexProduct('tier-index-second', 7.0);

    tierIndexInvoke('_prepareWebsiteDateTable');
    tierIndexInvoke('_prepareTierPriceIndex', [(int) $first->getId()]);
    tierIndexInvoke('_prepareTierPriceIndex', [(int) $second->getId()]);

    expect(tierIndexRowCount((int) $first->getId()))->toBeGreaterThan(0)
        ->and(tierIndexRowCount((int) $second->getId()))->toBeGreaterThan(0);
});

it('indexes the tier price of each product that reindexProductIds reindexes', function () {
    $this->products[] = $product = tierIndexProduct('tier-index-mass', 8.0);

    // A save of another product leaves only that product in the tier price index
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->delete($resource->getTableName('catalog/product_index_tier_price'));

    Mage::getResourceSingleton('catalog/product_indexer_price')->reindexProductIds([(int) $product->getId()]);

    expect(tierIndexIndexedTierPrice((int) $product->getId()))->toBe(8.0);
});
