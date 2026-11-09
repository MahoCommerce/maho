<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Mage\Sales\Api\OrderProcessor;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\CartParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The one page checkout and POST /guest-carts/{id}/place-order refuse a guest order through
 * Mage_Sales_Service_Order::assertGuestCheckoutAllowed() when the store does not allow guest checkout (#1348).
 */

const GUEST_CHECKOUT_REFUSED = 'Sorry, guest checkout is not enabled. Please try again or contact store owner.';

/**
 * Validate $quote as the one page checkout does before it places the order.
 *
 * @return string the error, or an empty string when the checkout accepts the order
 */
function guestCheckoutViaWeb(Mage_Sales_Model_Quote $quote, string $method): string
{
    $quote->setCheckoutMethod($method);
    try {
        Mage::getModel('checkout/type_onepage')->setQuote($quote)->validate();
    } catch (Mage_Core_Exception $e) {
        return $e->getMessage();
    }
    return '';
}

/**
 * Post an empty body to /api/rest/v2/guest-carts/{id}/place-order.
 *
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function guestCheckoutViaApi(Mage_Sales_Model_Quote $quote): array
{
    $maskedId = (string) $quote->getData('masked_quote_id');
    $request = AdminApiParity::jsonRequest('POST', "/api/rest/v2/guest-carts/{$maskedId}/place-order");
    $processor = new OrderProcessor(CartParity::guestSecurity());

    return AdminApiParity::api($request, fn() => $processor->process(null, new Post(name: 'place_guest_order'), ['id' => $maskedId], ['request' => $request]));
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $this->cart = CartParity::cart();
});

afterEach(function () {
    $this->cart->delete();
});

it('refuses a guest order with the same message when the store does not allow guest checkout', function () {
    Mage::app()->getStore()->setConfig(Mage_Checkout_Helper_Data::XML_PATH_GUEST_CHECKOUT, '0');

    $web = guestCheckoutViaWeb($this->cart, Mage_Checkout_Model_Type_Onepage::METHOD_GUEST);
    $api = guestCheckoutViaApi($this->cart);

    expect($web)->toBe(GUEST_CHECKOUT_REFUSED)
        ->and($api['status'])->toBe(403)
        ->and($api['errors'])->toBe([GUEST_CHECKOUT_REFUSED]);
});

it('lets a shopper who registers in the checkout go on when the store does not allow guest checkout', function () {
    Mage::app()->getStore()->setConfig(Mage_Checkout_Helper_Data::XML_PATH_GUEST_CHECKOUT, '0');

    expect(guestCheckoutViaWeb($this->cart, Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER))->toBe('');
});

it('accepts a guest order on both transports when the store allows guest checkout', function () {
    Mage::app()->getStore()->setConfig(Mage_Checkout_Helper_Data::XML_PATH_GUEST_CHECKOUT, '1');

    $web = guestCheckoutViaWeb($this->cart, Mage_Checkout_Model_Type_Onepage::METHOD_GUEST);
    $api = guestCheckoutViaApi($this->cart);

    // The cart has no address, so the API stops at the next rule of the order
    expect($web)->toBe('')
        ->and($api['status'])->not->toBe(403)
        ->and($api['errors'])->not->toBe([GUEST_CHECKOUT_REFUSED]);
});
