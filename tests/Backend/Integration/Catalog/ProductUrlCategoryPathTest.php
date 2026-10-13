<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function categoryPathCleanup(): void
{
    foreach (Mage::getResourceModel('catalog/product_collection')->addAttributeToFilter('sku', 'categorypath-product') as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    foreach (Mage::getResourceModel('catalog/category_collection')->addAttributeToFilter('url_key', 'categorypath-category') as $category) {
        Mage::getModel('catalog/category')->load($category->getId())->delete();
    }
}

beforeEach(function (): void {
    categoryPathCleanup();
    $this->storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    $store = Mage::app()->getStore($this->storeId);
    $store->setConfig(Mage_Catalog_Helper_Product::XML_PATH_PRODUCT_URL_USE_CATEGORY, '1');

    $root = Mage::getModel('catalog/category')->load($store->getRootCategoryId());
    $this->category = Mage::getModel('catalog/category')->setStoreId(0)->setName('Categorypath Category')
        ->setUrlKey('categorypath-category')->setIsActive(1);
    $this->category->setAttributeSetId($this->category->getDefaultAttributeSetId())->setPath($root->getPath())->save();

    $this->product = Mage::getModel('catalog/product')->setStoreId(0)->setName('Categorypath Product')
        ->setSku('categorypath-product')->setUrlKey('categorypath-product')->setPrice(10.00)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(4)->setWebsiteIds([1])->setCategoryIds([$this->category->getId()]);
    $this->product->save();
    Mage::getSingleton('catalog/url')->refreshProductRewrite((int) $this->product->getId(), $this->storeId);

    $this->categoryPath = Mage::getModel('core/url_rewrite')->setStoreId($this->storeId)
        ->loadByIdPath("product/{$this->product->getId()}/{$this->category->getId()}")->getRequestPath();
    $this->productPath = Mage::getModel('core/url_rewrite')->setStoreId($this->storeId)
        ->loadByIdPath("product/{$this->product->getId()}")->getRequestPath();
    expect($this->categoryPath)->toStartWith('categorypath-category/');
});

afterEach(fn() => categoryPathCleanup());

it('keeps only the product rewrite without category when the store does not use the category path', function (string $refresh, bool $saveHistory): void {
    Mage::app()->getStore($this->storeId)->setConfig(Mage_Catalog_Helper_Product::XML_PATH_PRODUCT_URL_USE_CATEGORY, '0');
    $url = Mage::getSingleton('catalog/url')->setShouldSaveRewritesHistory($saveHistory);
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    // The full reindex changes the rewrites of every product in the store, so the test rolls them back
    $adapter->beginTransaction();
    try {
        match ($refresh) {
            'product' => $url->refreshProductRewrite((int) $this->product->getId(), $this->storeId),
            'category' => $url->refreshCategoryRewrite((int) $this->category->getId(), $this->storeId),
            'store' => $url->refreshProductRewrites($this->storeId),
        };

        $systemCategoryRewrites = $adapter->fetchCol(
            $adapter->select()
                ->from(Mage::getSingleton('core/resource')->getTableName('core/url_rewrite'), 'request_path')
                ->where('store_id = ?', $this->storeId)
                ->where('product_id = ?', (int) $this->product->getId())
                ->where('category_id IS NOT NULL')
                ->where('is_system = ?', 1),
        );
        $oldUrl = Mage::getModel('core/url_rewrite')->setStoreId($this->storeId)->loadByRequestPath($this->categoryPath);

        expect($systemCategoryRewrites)->toBe([])
            ->and(Mage::getModel('core/url_rewrite')->setStoreId($this->storeId)
                ->loadByIdPath("product/{$this->product->getId()}")->getRequestPath())->toBe($this->productPath);
        if ($saveHistory) {
            expect($oldUrl->getOptions())->toBe('RP')->and($oldUrl->getTargetPath())->toBe($this->productPath);
        } else {
            expect($oldUrl->getId())->toBeNull();
        }
    } finally {
        $adapter->rollBack();
    }
})->with([
    'after a product save' => 'product',
    'after a category save' => 'category',
    'after a full reindex' => 'store',
])->with(['with a permanent redirect' => true, 'without a permanent redirect' => false]);

it('points an older redirect to the product URL when the store stops using the category path', function (): void {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->insert(Mage::getSingleton('core/resource')->getTableName('core/url_rewrite'), [
        'store_id' => $this->storeId,
        'product_id' => (int) $this->product->getId(),
        'id_path' => 'categorypath-older',
        'request_path' => 'categorypath-older.html',
        'target_path' => $this->categoryPath,
        'is_system' => 0,
        'options' => 'RP',
    ]);
    Mage::app()->getStore($this->storeId)->setConfig(Mage_Catalog_Helper_Product::XML_PATH_PRODUCT_URL_USE_CATEGORY, '0');
    Mage::getSingleton('catalog/url')->setShouldSaveRewritesHistory(true)
        ->refreshProductRewrite((int) $this->product->getId(), $this->storeId);

    expect(Mage::getModel('core/url_rewrite')->setStoreId($this->storeId)
        ->loadByRequestPath('categorypath-older.html')->getTargetPath())->toBe($this->productPath);
});

it('gives the category URL back to the product when the store uses the category path again', function (): void {
    $store = Mage::app()->getStore($this->storeId);
    $url = Mage::getSingleton('catalog/url')->setShouldSaveRewritesHistory(true);
    $store->setConfig(Mage_Catalog_Helper_Product::XML_PATH_PRODUCT_URL_USE_CATEGORY, '0');
    $url->refreshProductRewrite((int) $this->product->getId(), $this->storeId);
    $store->setConfig(Mage_Catalog_Helper_Product::XML_PATH_PRODUCT_URL_USE_CATEGORY, '1');
    $url->refreshProductRewrite((int) $this->product->getId(), $this->storeId);

    expect(Mage::getModel('core/url_rewrite')->setStoreId($this->storeId)
        ->loadByIdPath("product/{$this->product->getId()}/{$this->category->getId()}")->getRequestPath())
        ->toBe($this->categoryPath);
});

it('gives the product URL without category inside a category when the store does not use the category path', function (): void {
    Mage::app()->getStore($this->storeId)->setConfig(Mage_Catalog_Helper_Product::XML_PATH_PRODUCT_URL_USE_CATEGORY, '0');
    Mage::app()->setCurrentStore($this->storeId);
    Mage::register('current_category', $this->category);
    $product = Mage::getModel('catalog/product')->setStoreId($this->storeId)->load($this->product->getId());

    expect($product->getProductUrl())
        ->toBe(Mage::getModel('core/url')->setStore($this->storeId)->getDirectUrl($this->productPath));
});
