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

describe('converter', function () {
    test('decodes text entities but keeps angle brackets escaped', function () {
        $markdown = Mage::getSingleton('contentnegotiation/converter')
            ->toMarkdown('<p><a href="/x">Tops &amp; Blouses</a> &copy; &#8364; &lt;tag&gt;</p>');

        expect($markdown)->toBe('[Tops & Blouses](/x) © € &lt;tag&gt;');
    });
});

describe('text', function () {
    test('keeps angle brackets escaped and a link label safe in a table', function () {
        $renderer = new class extends Maho_ContentNegotiation_Model_Renderer_AbstractRenderer {
            #[\Override]
            public function render(): ?string
            {
                return null;
            }

            public function textOf(string $html): string
            {
                return $this->text($html);
            }

            public function linkTo(string $label, string $url): string
            {
                return $this->link($label, $url);
            }
        };

        expect($renderer->textOf('Use &lt;b&gt;only&lt;/b&gt; indoors'))->toBe('Use &lt;b&gt;only&lt;/b&gt; indoors');
        expect($renderer->linkTo('Shirt | Blue [XL]', '/shirt'))->toBe('[Shirt \\| Blue XL](/shirt)');
    });
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
            fn(Mage_Catalog_Model_Product $child): bool => (int) $child->getStatus() === Mage_Catalog_Model_Product_Status::STATUS_ENABLED,
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
        if ($category->getDisplayMode() !== Mage_Catalog_Model_Category::DM_PAGE) {
            expect($markdown)->toContain('## Products')->not->toContain('Next page');
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

describe('category landing page', function () {
    test('renders the cms block of a category in static block mode', function () {
        $store = Mage::app()->getStore();
        $block = Mage::getModel('cms/block')
            ->setTitle('Landing page test')
            ->setIdentifier('cn_landing_' . uniqid())
            ->setIsActive(1)
            ->setStores([Mage_Core_Model_App::ADMIN_STORE_ID])
            ->setContent('<p>Landing text for agents.</p><ul><li>First point</li></ul>')
            ->save();
        $category = Mage::getModel('catalog/category')->setStoreId($store->getId())->load($store->getRootCategoryId());
        $category->setDisplayMode(Mage_Catalog_Model_Category::DM_PAGE)->setLandingPage($block->getId());
        Mage::register('current_category', $category);

        try {
            $markdown = rdRender('contentnegotiation/renderer_category');

            expect($markdown)->toContain('Landing text for agents.')
                ->toContain('- First point')
                ->not->toContain('## Products')
                ->not->toContain('<');
            expect(Mage::getModel('contentnegotiation/renderer_category')->getCacheTags())
                ->toContain(Mage_Cms_Model_Block::CACHE_TAG . '_' . $block->getId());
        } finally {
            $block->delete();
        }
    });
});

describe('blog list', function () {
    test('renders the posts in one document', function () {
        $markdown = rdRender('contentnegotiation/renderer_blogList');

        expect($markdown)->toStartWith("# Blog\n")->not->toContain('<');
        /** @var Maho_Blog_Helper_Data $blog */
        $blog = Mage::helper('blog');
        if ($blog->hasVisiblePosts()) {
            expect($markdown)->toContain("\n- [");
        } else {
            expect($markdown)->toContain('There are no posts yet.');
        }
    });

    test('renders a category with the posts of the category and its children', function () {
        $storeId = (int) Mage::app()->getStore()->getId();
        $suffix = uniqid();
        $parent = Mage::getModel('blog/category')
            ->setName('Markdown Parent ' . $suffix)
            ->setUrlKey('cn-parent-' . $suffix)
            ->setMetaDescription('Posts about markdown.')
            ->setIsActive(1)
            ->save();
        $child = Mage::getModel('blog/category')
            ->setName('Markdown Child ' . $suffix)
            ->setUrlKey('cn-child-' . $suffix)
            ->setParentId((int) $parent->getId())
            ->setIsActive(1)
            ->save();
        $inChild = Mage::getModel('blog/post')
            ->setTitle('Post In Child ' . $suffix)
            ->setUrlKey('cn-in-child-' . $suffix)
            ->setContent('<p>Child content</p>')
            ->setIsActive(1)
            ->setPublishDate('2025-01-01')
            ->setStores([$storeId])
            ->setCategories([(int) $child->getId()])
            ->save();
        $elsewhere = Mage::getModel('blog/post')
            ->setTitle('Post Elsewhere ' . $suffix)
            ->setUrlKey('cn-elsewhere-' . $suffix)
            ->setContent('<p>Other content</p>')
            ->setIsActive(1)
            ->setPublishDate('2025-01-01')
            ->setStores([$storeId])
            ->save();

        try {
            $category = Mage::getModel('blog/category')->load($parent->getId());
            Mage::register('current_blog_category', $category);

            $markdown = rdRender('contentnegotiation/renderer_blogList');

            expect($markdown)->toStartWith('# Markdown Parent ' . $suffix . "\n\n> Posts about markdown.\n")
                ->toContain('[Post In Child ' . $suffix . ']')
                ->not->toContain('Post Elsewhere');
            expect(Mage::getModel('contentnegotiation/renderer_blogList')->getCacheTags())
                ->toContain('blog_category_' . $parent->getId(), 'blog_post');
        } finally {
            $inChild->delete();
            $elsewhere->delete();
            $child->delete();
            $parent->delete();
        }
    });
});

describe('blog excerpt', function () {
    test('cuts the content on a character, not a byte', function () {
        $post = Mage::getModel('blog/post')->setContent('<p>' . str_repeat('a', 199) . 'über alles</p>');
        $excerpt = Mage::helper('blog')->truncateContent($post, 200);

        expect($excerpt)->toBe(str_repeat('a', 199) . 'ü...');
        expect(Mage::helper('structureddata')->toPlainText($excerpt))->toBe($excerpt);
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
