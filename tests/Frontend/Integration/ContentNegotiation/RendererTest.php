<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function rdRender(string $alias): ?string
{
    /** @var Maho_ContentNegotiation_Model_Renderer_RendererInterface $renderer */
    $renderer = Mage::getModel($alias);
    return $renderer->render();
}

function rdLoadProduct(string $typeId): ?Mage_Catalog_Model_Product
{
    $productId = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', $typeId)
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    if (!$productId) {
        return null;
    }
    return Mage::getModel('catalog/product')->setStoreId(Mage::app()->getStore()->getId())->load($productId);
}

beforeEach(function () {
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
});

describe('product', function () {
    test('renders the facts and the description without html', function () {
        $product = loadSimplePricedProduct();
        Mage::register('current_product', $product);
        Mage::register('product', $product);

        $markdown = rdRender('contentnegotiation/renderer_product');

        expect($markdown)->toStartWith('# ' . $product->getName() . "\n")
            ->toContain('- SKU: ' . $product->getSku())
            ->toContain('- Price: ')
            ->toContain('- Availability: ')
            ->toContain('- URL: ' . $product->getProductUrl())
            ->not->toContain('<');
    });

    test('lists every enabled child of a configurable product', function () {
        $product = rdLoadProduct(Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
        if ($product === null) {
            $this->markTestSkipped('No configurable product in the sample data.');
        }
        Mage::register('current_product', $product);
        Mage::register('product', $product);

        /** @var Mage_Catalog_Model_Product_Type_Configurable $type */
        $type = $product->getTypeInstance(true);
        $children = array_filter(
            $type->getUsedProducts(null, $product),
            fn(Mage_Catalog_Model_Product $child) => (int) $child->getStatus() === Mage_Catalog_Model_Product_Status::STATUS_ENABLED,
        );
        if ($children === []) {
            $this->markTestSkipped('The configurable product has no enabled child.');
        }

        $markdown = rdRender('contentnegotiation/renderer_product');

        expect($markdown)->toContain('## Options');
        foreach ($children as $child) {
            expect($markdown)->toContain('| ' . $child->getSku() . ' |');
        }
    });

    test('returns null without a product', function () {
        expect(rdRender('contentnegotiation/renderer_product'))->toBeNull();
    });
});

describe('category', function () {
    test('renders the name and the subcategories', function () {
        $store = Mage::app()->getStore();
        $category = Mage::getResourceModel('catalog/category_collection')
            ->setStoreId($store->getId())
            ->addAttributeToSelect(['name', 'description'])
            ->addAttributeToFilter('parent_id', $store->getRootCategoryId())
            ->addAttributeToFilter('is_active', 1)
            ->setPageSize(1)
            ->getFirstItem();
        if (!$category->getId()) {
            $this->markTestSkipped('No active category in the sample data.');
        }
        Mage::register('current_category', $category);

        $markdown = rdRender('contentnegotiation/renderer_category');

        expect($markdown)->toStartWith('# ' . $category->getName() . "\n")
            ->not->toContain('<');
        if ($category->getChildrenCategories()->count() > 0) {
            expect($markdown)->toContain('## Subcategories');
        }
        expect(Mage::getModel('contentnegotiation/renderer_category')->getCacheTags())
            ->toContain(Mage_Catalog_Model_Category::CACHE_TAG . '_' . $category->getId(), Mage_Catalog_Model_Product::CACHE_TAG);
    });
});

describe('cms page', function () {
    test('converts the page content to markdown', function () {
        $page = Mage::getModel('cms/page');
        $page->setTitle('Markdown Test Page')
            ->setIdentifier('markdown-test-' . uniqid())
            ->setIsActive(1)
            ->setRootTemplate('one_column')
            ->setStores([0])
            ->setContent('<h2>Hello</h2><p>Some <strong>bold</strong> text and a <a href="https://example.com/">link</a>.</p>')
            ->save();

        try {
            Mage::getSingleton('cms/page')->setStoreId(Mage::app()->getStore()->getId())->load($page->getId());

            $markdown = rdRender('contentnegotiation/renderer_page');

            expect($markdown)->toStartWith("# Markdown Test Page\n")
                ->toContain('## Hello')
                ->toContain('**bold**')
                ->toContain('[link](https://example.com/)')
                ->not->toContain('<p>');
        } finally {
            $page->delete();
        }
    });

    test('returns null without a page', function () {
        expect(rdRender('contentnegotiation/renderer_page'))->toBeNull();
    });
});

describe('blog post', function () {
    test('renders the title, the facts and the content', function () {
        if (!Mage::helper('core')->isModuleEnabled('Maho_Blog')) {
            $this->markTestSkipped('The blog module is disabled.');
        }

        $post = Mage::getModel('blog/post');
        $post->setTitle('Markdown Test Post')
            ->setUrlKey('markdown-test-' . uniqid())
            ->setContent('<p>Hello <em>world</em></p>')
            ->setIsActive(1)
            ->setPublishDate('2025-01-01')
            ->setStores([Mage::app()->getStore()->getId()])
            ->save();

        try {
            Mage::register('current_blog_post', $post);

            $markdown = rdRender('contentnegotiation/renderer_blogPost');

            expect($markdown)->toStartWith("# Markdown Test Post\n")
                ->toContain('- Published: ')
                ->toContain('- URL: ' . $post->getUrl())
                ->toContain('Hello *world*')
                ->not->toContain('<p>');
            expect(Mage::getModel('contentnegotiation/renderer_blogPost')->getCacheTags())
                ->toContain('blog_post', 'blog_post_' . $post->getId());
        } finally {
            $post->delete();
        }
    });
});
