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

function productsListWidgetRootPath(): string
{
    return (string) Mage::getModel('catalog/category')
        ->load((int) Mage::app()->getStore()->getRootCategoryId())
        ->getPath();
}

/**
 * A category of the product below the current store's root. A product can also sit in another
 * store's tree, and the widget refuses those. The root itself is excluded: the category index
 * lists every product of the store under the root, so it would prove no filtering.
 *
 * The path decides, not Mage_Catalog_Helper_Category::canShow(). That helper is the predicate the
 * widget applies, so reusing it here would make the test agree with the widget by construction.
 */
function productsListWidgetStoreCategoryId(Mage_Catalog_Model_Product $product): ?int
{
    $rootPath = productsListWidgetRootPath();
    foreach ($product->getCategoryIds() as $categoryId) {
        $category = Mage::getModel('catalog/category')->load((int) $categoryId);
        if (str_starts_with((string) $category->getPath(), $rootPath . '/') && $category->getIsActive()) {
            return (int) $category->getId();
        }
    }
    return null;
}

/**
 * An active category outside the current store's root, so outside what the store may show.
 */
function productsListWidgetForeignCategoryId(): ?int
{
    $collection = Mage::getResourceModel('catalog/category_collection')
        ->addAttributeToSelect('is_active')
        ->addAttributeToFilter('is_active', 1)
        ->addFieldToFilter('level', ['gt' => 1]);
    $rootPath = productsListWidgetRootPath();
    foreach ($collection as $category) {
        if (!str_starts_with((string) $category->getPath(), $rootPath . '/')) {
            return (int) $category->getId();
        }
    }
    return null;
}

/**
 * Products assigned to the category or to one of its descendants, read from the assignment table.
 * The widget reads the category index instead, so this stays an independent expectation.
 *
 * @return list<int>
 */
function productsListWidgetBranchProductIds(Mage_Catalog_Model_Category $category): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $select = $adapter->select()
        ->from(['ccp' => $resource->getTableName('catalog/category_product')], ['product_id'])
        ->join(['c' => $resource->getTableName('catalog/category')], 'c.entity_id = ccp.category_id', [])
        ->where('c.path = ? OR c.path LIKE ?', $category->getPath(), $category->getPath() . '/%');
    return array_map(intval(...), $adapter->fetchCol($select));
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

    it('renders an empty collection for a category of another store', function () {
        $categoryId = productsListWidgetForeignCategoryId();
        if ($categoryId === null) {
            $this->markTestSkipped('Every active category belongs to this store root.');
        }

        $this->block->setCategoryId('category/' . $categoryId)->setOnlyInStock(false)->setProductsCount(50);
        expect(productsListWidgetCollection($this->block)->getSize())->toBe(0);
    });

    it('lists the products of a category', function () {
        $skus = productsListWidgetSkus(1);
        if ($skus === []) {
            $this->markTestSkipped('No enabled product to pick a category from.');
        }

        $product = Mage::getModel('catalog/product')->load(array_key_first($skus));
        $categoryId = productsListWidgetStoreCategoryId($product);
        if ($categoryId === null) {
            $this->markTestSkipped('The product is not in a category below this store root.');
        }

        $this->block->setCategoryId('category/' . $categoryId)->setOnlyInStock(false)->setProductsCount(50);
        $ids = array_map(fn($p) => (int) $p->getId(), productsListWidgetCollection($this->block)->getItems());
        expect($ids)->toContain((int) $product->getId());

        $category = Mage::getModel('catalog/category')->setStoreId(Mage::app()->getStore()->getId())->load($categoryId);
        expect(array_diff($ids, productsListWidgetBranchProductIds($category)))->toBe([]);
    });

    it('intersects a category with a SKU list', function () {
        $skus = productsListWidgetSkus(1);
        if ($skus === []) {
            $this->markTestSkipped('No enabled product to pick a category from.');
        }

        $product = Mage::getModel('catalog/product')->load(array_key_first($skus));
        $categoryId = productsListWidgetStoreCategoryId($product);
        if ($categoryId === null) {
            $this->markTestSkipped('The product is not in a category below this store root.');
        }

        $this->block->setCategoryId('category/' . $categoryId)
            ->setSkus($product->getSku() . ', sku-that-does-not-exist')
            ->setOnlyInStock(false);
        $ids = array_values(array_map(fn($p) => (int) $p->getId(), productsListWidgetCollection($this->block)->getItems()));
        expect($ids)->toBe([(int) $product->getId()]);
    });
});
