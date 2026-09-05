<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

/**
 * Enabled, visible products of the current store, oldest first.
 *
 * @return array<int, string> entity_id => sku
 */
function productsListWidgetSkus(int $limit): array
{
    $collection = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToSelect('sku')
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::getVisibleInCatalogIds())
        ->addStoreFilter()
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setPageSize($limit)
        ->setCurPage(1);
    $collection->getSelect()->order('e.entity_id ASC');

    $skus = [];
    foreach ($collection as $product) {
        $skus[(int) $product->getId()] = (string) $product->getSku();
    }
    return $skus;
}

function productsListWidgetCollection(Mage_Catalog_Block_Product_Widget_List $block): Mage_Catalog_Model_Resource_Product_Collection
{
    $method = new ReflectionMethod($block, '_getProductCollection');
    return $method->invoke($block);
}

describe('Products List widget block', function () {
    beforeEach(function () {
        $this->block = new Mage_Catalog_Block_Product_Widget_List();
    });

    it('exposes sensible defaults', function () {
        expect($this->block->getSortMode())->toBe('position');
        expect($this->block->getProductsCount())->toBe(5);
        expect($this->block->onlyInStock())->toBeTrue();
        expect($this->block->getCategoryId())->toBeNull();
        expect($this->block->getSkus())->toBe([]);
        expect($this->block->getTitle())->toBe('');
    });

    it('accepts the chooser value and a bare category id', function () {
        $this->block->setCategoryId('category/12');
        expect($this->block->getCategoryId())->toBe(12);

        $this->block->setCategoryId('7');
        expect($this->block->getCategoryId())->toBe(7);

        $this->block->setCategoryId('category/abc');
        expect($this->block->getCategoryId())->toBeNull();

        $this->block->setCategoryId('0');
        expect($this->block->getCategoryId())->toBeNull();
    });

    it('splits the SKU list on commas and whitespace and drops duplicates', function () {
        $this->block->setSkus(" a-1, b-2\nb-2 ,, c-3 ");
        expect($this->block->getSkus())->toBe(['a-1', 'b-2', 'c-3']);
    });

    it('includes the selection and the sort in the cache key', function () {
        $this->block->setCategoryId('category/3')->setSkus('x,y')->setSort('random')->setTitle('Picks');
        $info = $this->block->getCacheKeyInfo();
        expect($info)->toContain(3);
        expect($info)->toContain('x,y');
        expect($info)->toContain('random');
        expect($info)->toContain('Picks');
    });

    it('renders a grid unless the carousel layout is chosen', function () {
        expect($this->block->getLayoutMode())->toBe('grid');
        expect($this->block->isCarousel())->toBeFalse();
        expect($this->block->getProductsGridClass())->toBe('products-grid');

        $this->block->setLayoutMode('carousel');
        expect($this->block->isCarousel())->toBeTrue();
        expect($this->block->getProductsGridClass())->toBe('products-grid products-grid--carousel');
        expect($this->block->getCacheKeyInfo())->toContain('carousel');

        $this->block->setLayoutMode('bogus');
        expect($this->block->getLayoutMode())->toBe('grid');
    });

    it('renders an empty, error-free collection when nothing is selected', function () {
        $collection = productsListWidgetCollection($this->block);

        expect($collection)->toBeInstanceOf(Mage_Catalog_Model_Resource_Product_Collection::class);
        expect($collection->getSize())->toBe(0);
    });

    it('renders an empty collection for a category that does not exist', function () {
        $this->block->setCategoryId('category/999999999');
        expect(productsListWidgetCollection($this->block)->getSize())->toBe(0);
    });

    it('keeps the SKU list order when sorting by position', function () {
        $skus = productsListWidgetSkus(3);
        if (count($skus) < 3) {
            $this->markTestSkipped('Not enough enabled products to assert ordering.');
        }

        $expectedIds = array_reverse(array_keys($skus));
        $this->block->setSkus(implode(', ', array_reverse($skus)))->setOnlyInStock(false);

        $loaded = array_values(array_map(fn($p) => (int) $p->getId(), productsListWidgetCollection($this->block)->getItems()));
        expect($loaded)->toBe($expectedIds);
    });

    it('caps the SKU list at the configured count', function () {
        $skus = productsListWidgetSkus(3);
        if (count($skus) < 3) {
            $this->markTestSkipped('Not enough enabled products to assert the limit.');
        }

        $this->block->setSkus(implode(',', $skus))->setProductsCount(2)->setOnlyInStock(false);
        expect(count(productsListWidgetCollection($this->block)->getItems()))->toBe(2);
    });

    it('lists the products of a category', function () {
        $skus = productsListWidgetSkus(1);
        if ($skus === []) {
            $this->markTestSkipped('No enabled product to pick a category from.');
        }

        $product = Mage::getModel('catalog/product')->load(array_key_first($skus));
        $categoryIds = array_map(intval(...), $product->getCategoryIds());
        if ($categoryIds === []) {
            $this->markTestSkipped('The product is not assigned to a category.');
        }

        $this->block->setCategoryId('category/' . $categoryIds[0])->setOnlyInStock(false)->setProductsCount(50);
        $ids = array_map(fn($p) => (int) $p->getId(), productsListWidgetCollection($this->block)->getItems());
        expect($ids)->toContain((int) $product->getId());
    });

    it('intersects a category with a SKU list', function () {
        $skus = productsListWidgetSkus(1);
        if ($skus === []) {
            $this->markTestSkipped('No enabled product to pick a category from.');
        }

        $product = Mage::getModel('catalog/product')->load(array_key_first($skus));
        $categoryIds = array_map(intval(...), $product->getCategoryIds());
        if ($categoryIds === []) {
            $this->markTestSkipped('The product is not assigned to a category.');
        }

        $this->block->setCategoryId('category/' . $categoryIds[0])
            ->setSkus($product->getSku() . ', sku-that-does-not-exist')
            ->setOnlyInStock(false);
        $ids = array_values(array_map(fn($p) => (int) $p->getId(), productsListWidgetCollection($this->block)->getItems()));
        expect($ids)->toBe([(int) $product->getId()]);
    });
});
