<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function openGraphHead(): Mage_Page_Block_Html_Head
{
    $head = Mage::app()->getLayout()->createBlock('page/html_head', 'head');
    $head->setTitle('Example &amp; "sample"');
    $head->setDescription('A <strong>sample</strong> &amp; replacement.');
    $head->setRobots('INDEX,FOLLOW');
    return $head;
}

function openGraphHtml(): string
{
    return Mage::app()->getLayout()->createBlock('structureddata/openGraph')->toHtml();
}

function openGraphEnable(): void
{
    Mage::app()->getStore()->setConfig('catalog/social_sharing/open_graph_enabled', '1');
}

it('only adds the Open Graph block to supported layout handles', function (): void {
    foreach (['cms_index_index', 'cms_page_view', 'catalog_product_view', 'catalog_category_view'] as $handle) {
        $update = Mage::getModel('core/layout_update')->addHandle($handle)->load();
        expect($update->asString())->toContain('name="structured_data.open_graph"');
    }
    foreach (['cms_index_noroute', 'cms_preview_view', 'catalogsearch_result_index', 'checkout_cart_index'] as $handle) {
        $update = Mage::getModel('core/layout_update')->addHandle($handle)->load();
        expect($update->asString())->not->toContain('name="structured_data.open_graph"');
    }
});

it('keeps Open Graph disabled by default', function (): void {
    openGraphHead()->addLinkRel('canonical', 'https://example.test/product');

    expect(openGraphHtml())->toBe('');
});

it('renders escaped metadata with the head canonical URL and the product image', function (): void {
    openGraphEnable();
    $store = Mage::app()->getStore();
    $store->setConfig('general/store_information/name', 'Example & Co');
    $store->setConfig('general/locale/code', 'en_GB');
    openGraphHead()->addLinkRel('canonical', 'https://example.test/product?___store=uk&amp;view=1');

    $product = Mage::getModel('catalog/product')->setData(['image' => '/e/x/example.jpg']);
    Mage::register('current_product', $product);
    $image = htmlspecialchars($product->getMediaConfig()->getMediaUrl($product->getImage()), ENT_QUOTES, 'UTF-8');

    $html = openGraphHtml();
    expect($html)->toContain(
        '<meta property="og:type" content="website">',
        '<meta property="og:title" content="Example &amp; &quot;sample&quot;">',
        '<meta property="og:description" content="A sample &amp; replacement.">',
        '<meta property="og:url" content="https://example.test/product?___store=uk&amp;view=1">',
        '<meta property="og:image" content="' . $image . '">',
        '<meta property="og:site_name" content="Example &amp; Co">',
        '<meta property="og:locale" content="en_GB">',
    );
});

it('uses the category URL and public media image when the head has no canonical link', function (): void {
    openGraphEnable();
    openGraphHead();
    $category = Mage::getModel('catalog/category')->setData([
        'url' => 'https://example.test/category',
        'image' => 'example.png',
    ]);
    Mage::register('current_category', $category);
    $image = $category->getImageUrl();
    expect($image)->toMatch('#^https?://#');

    expect(openGraphHtml())->toContain(
        '<meta property="og:url" content="https://example.test/category">',
        '<meta property="og:image" content="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '">',
    );
});

it('uses the CMS canonical URL and the storefront logo for a content page', function (string $identifier, string $logo): void {
    openGraphEnable();
    $store = Mage::app()->getStore();
    $store->setConfig('web/default/cms_home_page', 'home');
    $store->setConfig('design/header/logo_src', $logo);
    Mage::getSingleton('cms/page')->setIdentifier($identifier)->setTitle('Example page');
    openGraphHead();

    $layout = Mage::app()->getLayout();
    $layout->createBlock('page/html_header', 'header');
    $page = $layout->createBlock('cms/page', 'cms_page');
    $canonical = htmlspecialchars($page->getCanonicalUrl(), ENT_QUOTES, 'UTF-8');
    $image = str_starts_with($logo, '//') ? Mage::app()->getRequest()->getScheme() . ':' . $logo : $logo;

    expect(openGraphHtml())->toContain(
        '<meta property="og:url" content="' . $canonical . '">',
        '<meta property="og:image" content="' . $image . '">',
    );
})->with([
    'homepage with a full logo URL' => ['home', 'https://media.example.test/share.png'],
    'content page with a scheme-relative logo URL' => ['about', '//media.example.test/share.png'],
]);

it('omits Open Graph on noindex and error responses', function (string $robots, int $status, string $robotsHeader): void {
    openGraphEnable();
    $head = openGraphHead();
    $head->addLinkRel('canonical', 'https://example.test/content');
    $head->setRobots($robots);
    $response = Mage::app()->getResponse();
    $response->setHttpResponseCode($status);
    if ($robotsHeader !== '') {
        $response->setHeader('X-Robots-Tag', $robotsHeader);
    }

    expect(openGraphHtml())->toBe('');
})->with([
    'head meta robots' => ['NOINDEX, FOLLOW', 200, ''],
    'head none directive' => ['NONE', 200, ''],
    'response robots header' => ['INDEX, FOLLOW', 200, 'noindex'],
    'not found' => ['INDEX, FOLLOW', 404, ''],
]);
