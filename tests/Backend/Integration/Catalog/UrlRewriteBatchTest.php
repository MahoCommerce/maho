<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function urlBatchCleanup(): void
{
    foreach (Mage::getResourceModel('catalog/product_collection')->addAttributeToFilter('sku', ['like' => 'urlbatch-%']) as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    foreach (Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', ['like' => 'urlbatch-%']) as $category) {
        Mage::getModel('catalog/category')->load($category->getId())->delete();
    }
}

/**
 * Return the rewrites of the products in all stores. A history rewrite gets a random id_path, so the function leaves it out.
 *
 * @param int[] $productIds
 * @return list<array<string, mixed>>
 */
function urlBatchRewrites(array $productIds): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $rows = $adapter->fetchAll($adapter->select()
        ->from(
            Mage::getSingleton('core/resource')->getTableName('core/url_rewrite'),
            ['store_id', 'id_path', 'request_path', 'target_path', 'is_system', 'category_id', 'product_id', 'options'],
        )
        ->where('product_id IN (?)', $productIds)
        ->order(['store_id', 'request_path']));
    foreach ($rows as &$row) {
        if ((int) $row['is_system'] === 0) {
            $row['id_path'] = null;
        }
    }
    return $rows;
}

beforeEach(function (): void {
    urlBatchCleanup();
    $store = Mage::app()->getDefaultStoreView();
    $root = Mage::getModel('catalog/category')->load($store->getRootCategoryId());
    $this->categories = [];
    foreach (['urlbatch-one', 'urlbatch-two'] as $urlKey) {
        $category = Mage::getModel('catalog/category')->setStoreId(0)->setName($urlKey)->setUrlKey($urlKey)->setIsActive(1);
        $category->setAttributeSetId($category->getDefaultAttributeSetId())->setPath($root->getPath())->save();
        $this->categories[] = (int) $category->getId();
    }

    $this->productIds = [];
    $assignments = [
        'urlbatch-a' => [$this->categories[0]],
        'urlbatch-b' => $this->categories,
        'urlbatch-c' => [$this->categories[1]],
    ];
    foreach ($assignments as $sku => $categoryIds) {
        $product = Mage::getModel('catalog/product')->setStoreId(0)->setName($sku)->setSku($sku)->setUrlKey($sku)
            ->setPrice(10.00)->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
            ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)->setWebsiteIds([1])->setCategoryIds($categoryIds);
        $product->save();
        $this->productIds[] = (int) $product->getId();
    }

    $url = Mage::getSingleton('catalog/url')->setShouldSaveRewritesHistory(true);
    foreach ($this->productIds as $productId) {
        $url->refreshProductRewrite($productId);
    }

    // Change the data behind the rewrites without the indexer: a new url key, a category removed, a website removed
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    Mage::getResourceSingleton('catalog/url')->saveProductAttribute(
        new Maho\DataObject(['id' => $this->productIds[0], 'store_id' => 0, 'url_key' => 'urlbatch-a-new']),
        'url_key',
    );
    $adapter->delete($resource->getTableName('catalog/category_product'), [
        'product_id = ?' => $this->productIds[1],
        'category_id = ?' => $this->categories[1],
    ]);
    $adapter->delete($resource->getTableName('catalog/product_website'), ['product_id = ?' => $this->productIds[2]]);
});

afterEach(fn() => urlBatchCleanup());

it('gives the same rewrites for a list of products as one refresh for each product', function (): void {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $url = Mage::getSingleton('catalog/url')->setShouldSaveRewritesHistory(true);

    $adapter->beginTransaction();
    try {
        foreach ($this->productIds as $productId) {
            $url->refreshProductRewrite($productId);
        }
        $expected = urlBatchRewrites($this->productIds);
    } finally {
        $adapter->rollBack();
    }

    $adapter->beginTransaction();
    try {
        $url->refreshProductsRewrites($this->productIds);
        $actual = urlBatchRewrites($this->productIds);
    } finally {
        $adapter->rollBack();
    }

    $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    $suffix = Mage::helper('catalog/product')->getProductUrlSuffix($storeId);
    $systemPaths = array_column(array_filter(
        $actual,
        fn(array $row): bool => (int) $row['is_system'] === 1 && (int) $row['store_id'] === $storeId,
    ), 'request_path', 'id_path');

    expect($actual)->toBe($expected)
        ->and(array_filter($actual, fn(array $row): bool => (int) $row['product_id'] === $this->productIds[2]))->toBe([])
        ->and($systemPaths["product/{$this->productIds[0]}"])->toBe('urlbatch-a-new' . $suffix)
        ->and($systemPaths)->not->toHaveKey("product/{$this->productIds[1]}/{$this->categories[1]}")
        ->and($systemPaths)->toHaveKey("product/{$this->productIds[1]}/{$this->categories[0]}");
});

it('refreshes the products of one store only when it gets a store', function (): void {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    $otherStores = array_filter(array_keys(Mage::app()->getStores()), fn(int $id): bool => $id !== $storeId);
    $before = array_values(array_filter(urlBatchRewrites($this->productIds), fn(array $row): bool => in_array((int) $row['store_id'], $otherStores, true)));

    $adapter->beginTransaction();
    try {
        Mage::getSingleton('catalog/url')->refreshProductsRewrites($this->productIds, $storeId);
        $after = urlBatchRewrites($this->productIds);
    } finally {
        $adapter->rollBack();
    }

    expect(array_values(array_filter($after, fn(array $row): bool => in_array((int) $row['store_id'], $otherStores, true))))->toBe($before)
        ->and(array_filter($after, fn(array $row): bool => (int) $row['store_id'] === $storeId && (int) $row['product_id'] === $this->productIds[2]))->toBe([]);
});
