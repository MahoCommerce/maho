<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoFrontendTestCase;

uses(MahoFrontendTestCase::class)->group('browser');

afterAll(fn() => MahoServer::stop());

beforeEach(function () {
    if (!browserTestsReady()) {
        test()->markTestSkipped('Playwright is not installed');
    }
    // Enable a second display currency so the storefront currency switcher renders.
    $config = Mage::getModel('core/config');
    $config->saveConfig('currency/options/base', 'USD');
    $config->saveConfig('currency/options/allow', 'USD,EUR');
    $config->saveConfig('currency/options/default', 'USD');
    Mage::getModel('directory/currency')->saveRates(['USD' => ['USD' => 1.0, 'EUR' => 0.9]]);
    Mage::app()->getStore()->resetConfig();
    Mage::app()->cleanCache();
    MahoServer::start();
});

/** A salable, priced, visible simple product page URL. */
function currencySwitcherProductUrl(): string
{
    $product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', 1)
        ->addAttributeToFilter('visibility', ['in' => [3, 4]])
        ->addAttributeToFilter('price', ['gt' => 0])
        ->setPageSize(1)
        ->getFirstItem();
    return '/catalog/product/view/id/' . (int) $product->getId() . '/';
}

it('switches the storefront display currency and reflects it on the new page', function () {
    $productUrl = currencySwitcherProductUrl();

    // A normal product page with the multi-currency switcher in the header. The
    // switcher's selected option carries the switch URL of the current currency, so
    // its value is the reliable signal of which currency is active (the visible $/€
    // symbols and both option values are in the DOM regardless of what's selected).
    $page = visit(MahoServer::baseUrl() . $productUrl)
        ->assertPresent('#select-currency');
    expect($page->page()->locator('#select-currency')->inputValue())->toContain('currency=USD');

    // Switch to EUR through the real <select>. Its onchange navigates to the switch
    // URL (already a fully-encoded URL), which 302s back to this page with EUR active.
    $page->select('#select-currency', '€')->wait(2);

    // The redirect completed cleanly (regression: a double-encoded uenc back-URL used to
    // put a newline in the Location header, triggering PHP's "Header may not contain
    // more than a single header" warning page instead of landing back here).
    $page->assertDontSee('Header may not contain')
        ->assertPresent('#select-currency');

    // The new page reflects the newly selected currency: the switcher is now on EUR.
    expect($page->page()->locator('#select-currency')->inputValue())->toContain('currency=EUR');
});
