<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function primaryCategoryCleanup(): void
{
    Mage::register('isSecureArea', true, true);
    foreach (Mage::getResourceModel('catalog/product_collection')->addAttributeToFilter('sku', ['like' => 'pcat-%']) as $product) {
        Mage::getModel('catalog/product')->load($product->getId())->delete();
    }
    $categoryIds = Mage::getResourceModel('catalog/category_collection')
        ->addAttributeToFilter('url_key', ['like' => 'pcat-%'])
        ->setOrder('level', 'ASC')
        ->getAllIds();
    foreach ($categoryIds as $categoryId) {
        $category = Mage::getModel('catalog/category')->load($categoryId);
        if ($category->getId()) {
            $category->delete();
        }
    }
}

function primaryCategoryCreate(string $key, Mage_Catalog_Model_Category|string $parent, bool $active = true, bool $inMenu = true): Mage_Catalog_Model_Category
{
    $category = Mage::getModel('catalog/category')->setStoreId(0)->setName('Pcat ' . $key)
        ->setUrlKey('pcat-' . $key)->setIsActive($active ? 1 : 0)->setIncludeInMenu($inMenu ? 1 : 0);
    $category->setAttributeSetId($category->getDefaultAttributeSetId())
        ->setPath($parent instanceof Mage_Catalog_Model_Category ? $parent->getPath() : $parent)
        ->save();
    return $category;
}

/**
 * @param list<Mage_Catalog_Model_Category> $categories
 */
function primaryCategoryProduct(array $categories): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product')->setStoreId(0)->setName('Pcat Product')
        ->setSku('pcat-' . uniqid())->setUrlKey('pcat-' . uniqid())->setPrice(10.00)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(4)->setWebsiteIds([1])
        ->setCategoryIds(array_map(fn(Mage_Catalog_Model_Category $category): int => (int) $category->getId(), $categories));
    $product->save();
    Mage::getResourceSingleton('catalog/category_indexer_product')->reindexProductIds([(int) $product->getId()]);
    return $product;
}

function primaryCategoryOf(Mage_Catalog_Model_Product $product): ?int
{
    return Mage::getModel('catalog/product')->setStoreId(Mage::app()->getStore()->getId())
        ->load($product->getId())->getPrimaryCategoryId();
}

function primaryCategoryInit(Mage_Catalog_Model_Product $product, int|false|null $categoryId = null): ?Mage_Catalog_Model_Category
{
    foreach (['current_category', 'current_entity_key', 'current_product', 'product'] as $key) {
        Mage::unregister($key);
    }
    $params = new \Maho\DataObject();
    $params->setCategoryId($categoryId ?? 0);
    expect(Mage::helper('catalog/product')->initProduct((int) $product->getId(), null, $params))
        ->toBeInstanceOf(Mage_Catalog_Model_Product::class);
    return Mage::registry('current_category');
}

function primaryCategoryTitle(): string
{
    $layout = Mage::app()->getLayout();
    $layout->createBlock('page/html_head', 'head');
    $layout->createBlock('page/html_breadcrumbs', 'breadcrumbs');
    $layout->createBlock('catalog/breadcrumbs');
    return (string) $layout->getBlock('head')->getTitle();
}

beforeEach(function (): void {
    primaryCategoryCleanup();
    $store = Mage::app()->getDefaultStoreView();
    Mage::app()->setCurrentStore($store->getId());
    Mage::getSingleton('catalog/session')->unsLastVisitedCategoryId();

    $root = Mage::getModel('catalog/category')->load($store->getRootCategoryId());
    $this->clothing = primaryCategoryCreate('clothing', $root);
    $this->jackets = primaryCategoryCreate('jackets', $this->clothing);
    $this->winter = primaryCategoryCreate('winter', $this->jackets);
    $this->shoes = primaryCategoryCreate('shoes', $root);
    $this->sale = primaryCategoryCreate('sale', $root, inMenu: false);
    $this->saleWinter = primaryCategoryCreate('sale-winter', $this->sale);
    $this->clearance = primaryCategoryCreate('clearance', $this->saleWinter);
    $this->lastPieces = primaryCategoryCreate('last-pieces', $this->clearance);
    $this->archive = primaryCategoryCreate('archive', $root, active: false);
    $this->archiveDeep = primaryCategoryCreate('archive-deep', primaryCategoryCreate('archive-mid', $this->archive));
    $this->otherRoot = primaryCategoryCreate('other-root', (string) Mage_Catalog_Model_Category::TREE_ROOT_ID);
    $this->otherDeep = primaryCategoryCreate('other-deep', primaryCategoryCreate('other-mid', $this->otherRoot));
});

afterEach(function (): void {
    Mage::getSingleton('catalog/session')->unsLastVisitedCategoryId();
    primaryCategoryCleanup();
});

describe('primary category', function (): void {
    it('picks the deepest category of the menu over a deeper category outside the menu', function (): void {
        $product = primaryCategoryProduct([$this->jackets, $this->winter, $this->lastPieces]);

        expect(primaryCategoryOf($product))->toBe((int) $this->winter->getId());
    });

    it('picks the deepest category when no category is in the menu', function (): void {
        $product = primaryCategoryProduct([$this->sale, $this->lastPieces]);

        expect(primaryCategoryOf($product))->toBe((int) $this->lastPieces->getId());
    });

    it('picks the lower ID between two categories at the same depth', function (): void {
        $product = primaryCategoryProduct([$this->shoes, $this->clothing]);

        expect(primaryCategoryOf($product))->toBe(min((int) $this->clothing->getId(), (int) $this->shoes->getId()));
    });

    it('skips a category below an inactive category', function (): void {
        $product = primaryCategoryProduct([$this->clothing, $this->archiveDeep]);

        expect(primaryCategoryOf($product))->toBe((int) $this->clothing->getId());
    });

    it('skips a category outside the root category of the store', function (): void {
        $product = primaryCategoryProduct([$this->clothing, $this->otherDeep]);

        expect(primaryCategoryOf($product))->toBe((int) $this->clothing->getId());
    });

    it('gives no category when the product has no category in the store', function (): void {
        expect(primaryCategoryOf(primaryCategoryProduct([])))->toBeNull()
            ->and(primaryCategoryOf(primaryCategoryProduct([$this->archive, $this->otherDeep])))->toBeNull();
    });
});

describe('product page category', function (): void {
    it('uses the primary category when the request and the session name no category', function (): void {
        $product = primaryCategoryProduct([$this->clothing, $this->winter, $this->lastPieces]);

        expect((int) primaryCategoryInit($product)?->getId())->toBe((int) $this->winter->getId())
            ->and(array_keys(Mage::helper('catalog')->getBreadcrumbPath()))->toBe([
                'category' . $this->clothing->getId(),
                'category' . $this->jackets->getId(),
                'category' . $this->winter->getId(),
                'product',
            ]);
    });

    it('uses the category of the URL over the primary category', function (): void {
        $product = primaryCategoryProduct([$this->clothing, $this->winter]);

        expect((int) primaryCategoryInit($product, (int) $this->clothing->getId())?->getId())->toBe((int) $this->clothing->getId());
    });

    it('uses the primary category when the category of the URL does not hold the product', function (): void {
        $product = primaryCategoryProduct([$this->winter]);

        expect((int) primaryCategoryInit($product, (int) $this->shoes->getId())?->getId())->toBe((int) $this->winter->getId());
    });

    it('uses the last visited category over the primary category', function (): void {
        $product = primaryCategoryProduct([$this->clothing, $this->winter]);
        Mage::getSingleton('catalog/session')->setLastVisitedCategoryId((int) $this->clothing->getId());

        expect((int) primaryCategoryInit($product)?->getId())->toBe((int) $this->clothing->getId());
    });

    it('uses the primary category when the last visited category does not hold the product', function (): void {
        $product = primaryCategoryProduct([$this->winter]);
        Mage::getSingleton('catalog/session')->setLastVisitedCategoryId((int) $this->shoes->getId());

        expect((int) primaryCategoryInit($product)?->getId())->toBe((int) $this->winter->getId());
    });

    it('gives no category when the caller passes false', function (): void {
        $product = primaryCategoryProduct([$this->winter]);

        expect(primaryCategoryInit($product, false))->toBeNull();
    });
});

describe('product page URL', function (): void {
    beforeEach(function (): void {
        $this->storeId = (int) Mage::app()->getStore()->getId();
        Mage::app()->getStore($this->storeId)->setConfig(Mage_Catalog_Helper_Product::XML_PATH_PRODUCT_URL_USE_CATEGORY, '1');
        $this->product = primaryCategoryProduct([$this->clothing, $this->winter]);
        Mage::getSingleton('catalog/url')->refreshProductRewrite((int) $this->product->getId(), $this->storeId);
    });

    it('keeps the product URL without category path when the primary category is used', function (): void {
        $productPath = Mage::getModel('core/url_rewrite')->setStoreId($this->storeId)
            ->loadByIdPath("product/{$this->product->getId()}")->getRequestPath();
        primaryCategoryInit($this->product);

        expect(Mage::registry('current_product')->getProductUrl())
            ->toBe(Mage::getModel('core/url')->setStore($this->storeId)->getDirectUrl($productPath));
    });

    it('keeps the category path in the product URL when the URL names the category', function (): void {
        $categoryPath = Mage::getModel('core/url_rewrite')->setStoreId($this->storeId)
            ->loadByIdPath("product/{$this->product->getId()}/{$this->clothing->getId()}")->getRequestPath();
        primaryCategoryInit($this->product, (int) $this->clothing->getId());

        expect($categoryPath)->toStartWith('pcat-clothing/')
            ->and(Mage::registry('current_product')->getProductUrl())
            ->toBe(Mage::getModel('core/url')->setStore($this->storeId)->getDirectUrl($categoryPath));
    });
});

describe('product page title', function (): void {
    beforeEach(function (): void {
        $this->product = primaryCategoryProduct([$this->winter]);
    });

    it('leaves the primary category out of the title', function (): void {
        primaryCategoryInit($this->product);

        expect(primaryCategoryTitle())->toContain('Pcat Product')->not->toContain('Pcat winter');
    });

    it('keeps the category of the URL in the title', function (): void {
        primaryCategoryInit($this->product, (int) $this->winter->getId());

        expect(primaryCategoryTitle())->toContain('Pcat Product')->toContain('Pcat winter');
    });
});
