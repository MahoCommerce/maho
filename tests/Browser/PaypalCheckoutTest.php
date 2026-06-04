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

it('renders inline PayPal card fields in EUR at checkout (Advanced Checkout, no popup)', function () {
    $id = aProductId();

    // Stay in one page so the session cookie (cart) persists: add to cart, then click
    // the minicart Checkout button rather than re-visiting (a fresh visit loses the session).
    $page = visit(MahoServer::baseUrl() . "/catalog/product/view/id/{$id}/")
        ->click('Add to Cart')
        ->assertSee('Cart Subtotal')
        ->click('Checkout')
        ->assertSee('Billing Address');

    // Filling the billing fields fires change events; the debounced auto-save loads the
    // shipping methods, auto-selects the single one, then loads the payment methods.
    $page->fill('#billing\\:firstname', 'Test')
        ->fill('#billing\\:lastname', 'Buyer')
        ->fill('#billing\\:email', 'buyer-test@example.com')
        ->fill('#billing\\:street1', '1 Infinite Loop')
        ->fill('#billing\\:city', 'Cupertino')
        ->select('#billing\\:region_id', 'California')
        ->fill('#billing\\:postcode', '95014')
        ->fill('#billing\\:telephone', '5551234567')
        ->assertDontSee('Waiting for address')
        ->assertDontSee('Waiting for shipping method')
        ->assertSee('Credit or Debit Card');

    // Select Advanced Checkout: PayPal injects hosted card-field iframes inline (no popup),
    // and the order total stays in the display currency (EUR).
    $page->click('Credit or Debit Card')
        ->assertPresent('#paypal-card-fields-number iframe')
        ->assertSee('€265.50')
        ->screenshot(true, 'paypal-card-fields-eur');

    // NOTE: actually typing the test card into the hosted fields and placing the order is
    // not automatable with pest-plugin-browser 4.3.1: withinFrame() waits for the page to
    // reach networkidle, which PayPal's live card iframes never do, so the keystrokes time
    // out. Everything up to and including the inline card fields rendering in EUR is covered.
});
