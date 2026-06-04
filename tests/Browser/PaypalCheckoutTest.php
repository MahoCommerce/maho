<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoFrontendTestCase;
use Tests\PaypalSandbox;

uses(MahoFrontendTestCase::class)->group('browser', 'paypal');

afterAll(fn() => MahoServer::stop());

beforeEach(function () {
    if (!PaypalSandbox::isConfigured()) {
        test()->markTestSkipped('PayPal sandbox credentials not set');
    }
    // Maho is already bootstrapped by MahoFrontendTestCase::setUp(); set the EUR
    // display currency first, then (re)start the server so it serves the configured DB.
    PaypalSandbox::configureDisplayCurrency();
    MahoServer::start();
});

/** Pick a salable, priced, visible simple product id from the test DB. */
function aProductId(): int
{
    $p = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', 1)
        ->addAttributeToFilter('visibility', ['in' => [3, 4]])
        ->addAttributeToFilter('price', ['gt' => 0])
        ->setPageSize(1)
        ->getFirstItem();
    return (int) $p->getId();
}

it('renders the product in EUR with a working PayPal button (no clientToken 405)', function () {
    $id = aProductId();
    expect($id)->toBeGreaterThan(0);

    visit(MahoServer::baseUrl() . "/catalog/product/view/id/{$id}/")
        ->assertSee('€')
        ->assertDontSee('Server returned status')
        ->screenshot(true, 'paypal-1-product');
});

it('adds a product to the cart and shows the cart total in EUR', function () {
    $id = aProductId();

    visit(MahoServer::baseUrl() . "/catalog/product/view/id/{$id}/")
        ->click('Add to Cart')
        ->assertSee('Cart Subtotal')
        ->assertSee('€')
        ->assertDontSee('Server returned status')
        ->screenshot(true, 'paypal-2-cart');
});
