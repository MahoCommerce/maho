<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

/**
 * Integration coverage for the Maho_StructuredData JSON-LD blocks (issue #483).
 *
 * Exercises the real config helper, layout/block factory and product type models so the
 * generated schema.org graph is validated end-to-end rather than against mocked data.
 */

/**
 * Decode the JSON-LD payload from a rendered <script type="application/ld+json"> block.
 *
 * @return array<string, mixed>|null
 */
function decodeJsonLd(string $html): ?array
{
    if (trim($html) === '') {
        return null;
    }
    $json = preg_replace('/^.*?>|<\/script>\s*$/s', '', trim($html));
    return json_decode((string) $json, true);
}

beforeEach(function () {
    $this->helper = Mage::helper('structureddata');
});

describe('config helper', function () {
    test('availability maps to full schema.org URLs', function () {
        $schema = Maho_StructuredData_Helper_Data::SCHEMA;

        $product = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('type_id', 'simple')
            ->setPageSize(1)
            ->getFirstItem();

        if (!$product->getId()) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        $product->setStoreId(Mage::app()->getStore()->getId());

        // Sample-data simple products ship enabled and in stock.
        expect($this->helper->getAvailabilityUrl($product))->toBe($schema . 'InStock');

        // Disabling the product makes it not saleable, so it reports out of stock.
        $product->setStatus(Mage_Catalog_Model_Product_Status::STATUS_DISABLED);
        expect($this->helper->getAvailabilityUrl($product))->toBe($schema . 'OutOfStock');
    });

    test('price formatting always yields two decimals', function () {
        expect($this->helper->formatPrice(19.5))->toBe('19.50');
        expect($this->helper->formatPrice(190.0))->toBe('190.00');
    });

    test('social profiles only include configured non-empty URLs', function () {
        Mage::app()->getStore()->setConfig('general/social_profiles/facebook_url', 'https://facebook.com/demo');
        Mage::app()->getStore()->setConfig('general/social_profiles/twitter_url', '');
        expect($this->helper->getSocialProfiles())->toBe(['https://facebook.com/demo']);
    });
});

describe('Organization block', function () {
    test('renders an Organization graph with name and url', function () {
        $data = decodeJsonLd(
            Mage::app()->getLayout()->createBlock('structureddata/jsonld_organization')->toHtml(),
        );

        expect($data)->not->toBeNull();
        expect($data['@type'])->toBe('OnlineStore');
        expect($data['@context'])->toBe('https://schema.org/');
        expect($data['name'])->not->toBeEmpty();
        expect($data['url'])->toStartWith('http');
    });

    test('outputs nothing when disabled', function () {
        Mage::app()->getStore()->setConfig('catalog/structured_data/enabled', '0');
        $html = Mage::app()->getLayout()->createBlock('structureddata/jsonld_organization')->toHtml();
        expect(trim($html))->toBe('');
    });
});

describe('Website block', function () {
    test('renders a WebSite graph with a SearchAction', function () {
        $data = decodeJsonLd(
            Mage::app()->getLayout()->createBlock('structureddata/jsonld_website')->toHtml(),
        );

        expect($data['@type'])->toBe('WebSite');
        expect($data['potentialAction']['@type'])->toBe('SearchAction');
        expect($data['potentialAction']['query-input'])->toBe('required name=search_term_string');
        expect($data['potentialAction']['target']['urlTemplate'])->toContain('{search_term_string}');
    });
});

describe('Product block', function () {
    beforeEach(function () {
        Mage::unregister('current_product');
    });

    afterEach(function () {
        Mage::unregister('current_product');
    });

    test('renders a valid Product graph for a simple product', function () {
        $product = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('type_id', 'simple')
            ->setPageSize(1)
            ->getFirstItem();

        if (!$product->getId()) {
            $this->markTestSkipped('No simple product in catalog.');
        }
        $product->setStoreId(Mage::app()->getStore()->getId());
        Mage::register('current_product', $product);

        $data = decodeJsonLd(
            Mage::app()->getLayout()->createBlock('structureddata/jsonld_product')->toHtml(),
        );

        expect($data['@type'])->toBe('Product');
        expect($data['name'])->not->toBeEmpty();
        // Google requires name + at least one of offers/review/aggregateRating.
        expect($data)->toHaveKey('offers');
        expect($data['offers']['@type'])->toBe('Offer');
        expect($data['offers']['price'])->toMatch('/^\d+\.\d{2}$/');
        expect($data['offers']['priceCurrency'])->not->toBeEmpty();
        expect($data['offers']['availability'])->toStartWith('https://schema.org/');
    });

    test('uses AggregateOffer for grouped products', function () {
        $product = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('type_id', 'grouped')
            ->setPageSize(1)
            ->getFirstItem();

        if (!$product->getId()) {
            $this->markTestSkipped('No grouped product in catalog.');
        }
        $product->setStoreId(Mage::app()->getStore()->getId());
        Mage::register('current_product', $product);

        $data = decodeJsonLd(
            Mage::app()->getLayout()->createBlock('structureddata/jsonld_product')->toHtml(),
        );

        expect($data['offers']['@type'])->toBe('AggregateOffer');
        expect((float) $data['offers']['lowPrice'])->toBeLessThanOrEqual((float) $data['offers']['highPrice']);
        expect($data['offers']['offerCount'])->toBeGreaterThan(1);
    });

    test('renders nothing without a current product', function () {
        $html = Mage::app()->getLayout()->createBlock('structureddata/jsonld_product')->toHtml();
        expect(trim($html))->toBe('');
    });
});

describe('Article (blog) block', function () {
    beforeEach(function () {
        Mage::unregister('current_blog_post');
    });

    afterEach(function () {
        Mage::unregister('current_blog_post');
    });

    test('renders a BlogPosting graph for a blog post', function () {
        if (!Mage::helper('core')->isModuleEnabled('Maho_Blog')) {
            $this->markTestSkipped('Maho_Blog module is not enabled.');
        }

        $post = Mage::getModel('blog/post')->getCollection()->setPageSize(1)->getFirstItem();
        if (!$post->getId()) {
            $this->markTestSkipped('No blog post in catalog.');
        }
        Mage::register('current_blog_post', $post);

        $data = decodeJsonLd(
            Mage::app()->getLayout()->createBlock('structureddata/jsonld_article')->toHtml(),
        );

        expect($data['@type'])->toBe('BlogPosting');
        expect($data['headline'])->not->toBeEmpty();
        expect($data['url'])->toStartWith('http');
        expect($data['mainEntityOfPage']['@id'])->toBe($data['url']);
        expect($data['publisher']['@type'])->toBe('Organization');
        expect($data['author'])->toHaveKey('name');
    });

    test('renders nothing without a current blog post', function () {
        $html = Mage::app()->getLayout()->createBlock('structureddata/jsonld_article')->toHtml();
        expect(trim($html))->toBe('');
    });
});

describe('Breadcrumbs block', function () {
    test('requires at least two crumbs', function () {
        $layout = Mage::app()->getLayout();
        $crumbs = $layout->createBlock('page/html_breadcrumbs', 'breadcrumbs');
        $crumbs->addCrumb('home', ['label' => 'Home', 'link' => 'http://maho.test/']);

        $block = $layout->createBlock('structureddata/jsonld_breadcrumbs');
        expect(trim($block->toHtml()))->toBe('');

        $crumbs->addCrumb('product', ['label' => 'A Product']);
        $data = decodeJsonLd($block->toHtml());

        expect($data['@type'])->toBe('BreadcrumbList');
        expect($data['itemListElement'])->toHaveCount(2);
        expect($data['itemListElement'][0]['position'])->toBe(1);
        expect($data['itemListElement'][0])->toHaveKey('item');
        // Last crumb has no link, so the "item" property is omitted.
        expect($data['itemListElement'][1])->not->toHaveKey('item');
    });
});
