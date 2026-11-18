<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The automatic sorts of a category (issue #1624): Newest, Bestsellers and Most viewed, and the out of stock
 * products that go last. The rolling tables hold the score of each product in each store.
 */
beforeEach(function (): void {
    $resource = Mage::getSingleton('core/resource');
    $this->db = $resource->getConnection('core_write');
    $this->db->beginTransaction();
    $this->tables = [
        'product' => $resource->getTableName('catalog/product'),
        'sales' => $resource->getTableName('sales/bestsellers_aggregated_rolling'),
        'views' => $resource->getTableName('reports/viewed_aggregated_rolling'),
        'stock' => $resource->getTableName('cataloginventory/stock_status'),
    ];
    $productType = Mage::getSingleton('eav/config')->getEntityType('catalog_product');
    $first = 1 + (int) $this->db->fetchOne($this->db->select()
        ->from($this->tables['product'], new Maho\Db\Expr('MAX(entity_id)')));
    $this->ids = [];
    foreach ([0, 1, 2] as $offset) {
        $this->ids[$offset] = $first + $offset;
        $this->db->insert($this->tables['product'], [
            'entity_id' => $first + $offset, 'entity_type_id' => $productType->getId(),
            'attribute_set_id' => $productType->getDefaultAttributeSetId(),
            'type_id' => 'simple', 'sku' => 'automatic-sort-' . $first . '-' . $offset,
            'created_at' => '2026-01-0' . (3 - $offset) . ' 10:00:00',
        ]);
    }
    $this->storeId = (int) Mage::app()->getDefaultStoreView()->getId();
});

afterEach(function (): void {
    $this->db->rollBack();
});

/**
 * @return list<int> the product IDs of the test in the order of the sort
 */
function automaticSortIds(array $ids, int $storeId, string $sort, string $direction, bool $outOfStockLast = false): array
{
    $collection = Mage::getResourceModel('catalog/product_collection')
        ->setStoreId($storeId)
        ->addIdFilter($ids);
    if ($outOfStockLast) {
        $collection->addOutOfStockLastSort();
    }
    $collection->addAttributeToSort($sort, $direction)->load();

    return array_map(intval(...), $collection->getColumnValues('entity_id'));
}

it('offers the three automatic sorts next to position', function () {
    $options = Mage::getSingleton('catalog/config')->getAttributeUsedForSortByArray();

    expect(array_keys($options))->toContain('position', 'newest', 'bestsellers', 'most_viewed');
});

it('offers Most Viewed only when the store records the views of products', function () {
    $store = Mage::app()->getStore($this->storeId);
    $store->setConfig('catalog/recently_products/enabled_recently_viewed', '0');
    expect(array_keys(Mage::getSingleton('catalog/config')->getAutomaticSortOptions()))->not->toContain('most_viewed');

    $store->setConfig('catalog/recently_products/enabled_recently_viewed', '1');
    expect(array_keys(Mage::getSingleton('catalog/config')->getAutomaticSortOptions()))->toContain('most_viewed');
});

it('sorts by the date of creation for Newest', function () {
    [$a, $b, $c] = $this->ids;

    expect(automaticSortIds($this->ids, $this->storeId, 'newest', 'desc'))->toBe([$a, $b, $c])
        ->and(automaticSortIds($this->ids, $this->storeId, 'newest', 'asc'))->toBe([$c, $b, $a]);
});

it('sorts by the units sold of the store for Bestsellers, and a product with no row goes last in both directions', function () {
    [$a, $b, $c] = $this->ids;
    $this->db->insertMultiple($this->tables['sales'], [
        ['product_id' => $a, 'store_id' => $this->storeId, 'qty_ordered' => 3, 'revenue' => 300],
        ['product_id' => $b, 'store_id' => $this->storeId, 'qty_ordered' => 9, 'revenue' => 90],
        // The score of all stores must not count in a store view
        ['product_id' => $c, 'store_id' => 0, 'qty_ordered' => 100, 'revenue' => 1000],
    ]);

    expect(automaticSortIds($this->ids, $this->storeId, 'bestsellers', 'desc'))->toBe([$b, $a, $c])
        ->and(automaticSortIds($this->ids, $this->storeId, 'bestsellers', 'asc'))->toBe([$a, $b, $c])
        ->and(automaticSortIds($this->ids, 0, 'bestsellers', 'desc'))->toBe([$c, $a, $b]);
});

it('sorts by the views of the store for Most viewed, and a product with no row goes last in both directions', function () {
    [$a, $b, $c] = $this->ids;
    $this->db->insertMultiple($this->tables['views'], [
        ['product_id' => $a, 'store_id' => $this->storeId, 'views_num' => 50],
        ['product_id' => $c, 'store_id' => $this->storeId, 'views_num' => 5],
    ]);

    expect(automaticSortIds($this->ids, $this->storeId, 'most_viewed', 'desc'))->toBe([$a, $c, $b])
        ->and(automaticSortIds($this->ids, $this->storeId, 'most_viewed', 'asc'))->toBe([$c, $a, $b]);
});

it('sorts the products that are out of stock last with every sort', function () {
    [$a, $b, $c] = $this->ids;
    $websiteId = (int) Mage::app()->getStore($this->storeId)->getWebsiteId();
    foreach ([$a => 0, $b => 1, $c => 1] as $productId => $status) {
        $this->db->insertOnDuplicate($this->tables['stock'], [
            'product_id' => $productId, 'website_id' => $websiteId, 'stock_id' => 1, 'qty' => $status, 'stock_status' => $status,
        ]);
    }

    expect(automaticSortIds($this->ids, $this->storeId, 'newest', 'desc'))->toBe([$a, $b, $c])
        ->and(automaticSortIds($this->ids, $this->storeId, 'newest', 'desc', true))->toBe([$b, $c, $a])
        ->and(automaticSortIds($this->ids, $this->storeId, 'newest', 'asc', true))->toBe([$c, $b, $a]);
});

it('lists the highest value first for the automatic sorts, unless the shopper picks a direction', function () {
    $toolbar = Mage::app()->getLayout()->createBlock('catalog/product_list_toolbar');

    expect($toolbar->getOrderDirection('bestsellers'))->toBe('desc')
        ->and($toolbar->getOrderDirection('most_viewed'))->toBe('desc')
        ->and($toolbar->getOrderDirection('newest'))->toBe('desc')
        ->and($toolbar->getOrderDirection('price'))->toBe('asc')
        ->and($toolbar->getOrderDirection('position'))->toBe('asc');

    $toolbar->setAvailableOrders(['position' => 'Position', 'bestsellers' => 'Bestsellers'])->setDefaultOrder('bestsellers');
    expect($toolbar->getCurrentDirection())->toBe('desc');
});

it('gives the default direction of the page to the default sort only', function () {
    $toolbar = Mage::app()->getLayout()->createBlock('catalog/product_list_toolbar')
        ->setAvailableOrders(['relevance' => 'Relevance', 'name' => 'Name', 'price' => 'Price'])
        ->setDefaultOrder('relevance')
        ->setDefaultDirection('desc');

    expect($toolbar->getOrderDirection('relevance'))->toBe('desc')
        ->and($toolbar->getOrderDirection('name'))->toBe('asc')
        ->and($toolbar->getOrderDirection('price'))->toBe('asc')
        ->and($toolbar->getOrderDirection('newest'))->toBe('desc');
});
