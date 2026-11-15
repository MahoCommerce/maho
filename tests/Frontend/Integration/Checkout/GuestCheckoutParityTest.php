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

require_once __DIR__ . '/OnepageDriver.php';

uses(Tests\MahoFrontendTestCase::class);

/**
 * The one page checkout and POST /guest-carts/{id}/place-order refuse a guest order through
 * Mage_Sales_Service_Order::validateQuote() when the store does not allow guest checkout (#1348). No transport has
 * its own gate. A staff actor has no gate.
 */

const GUEST_CHECKOUT_REFUSED = 'Sorry, guest checkout is not enabled. Please try again or contact store owner.';

/**
 * Post the place order step of the one page checkout for $quote as a guest. For another checkout method or for
 * staff, run the check of the order service. The cart has no address, so an order that passes the guest checkout
 * gate stops at the next rule.
 *
 * @return string the message of the gate, or an empty string when the gate accepts the order
 */
function guestCheckoutViaWeb(Mage_Sales_Model_Quote $quote, string $method, Mage_Checkout_Model_Actor $actor = Mage_Checkout_Model_Actor::Customer): string
{
    $quote->setCheckoutMethod($method);
    if ($actor === Mage_Checkout_Model_Actor::Customer && $method === Mage_Checkout_Model_Type_Onepage::METHOD_GUEST) {
        $quote->save();
        $message = (string) (checkoutOnepagePost($quote, 'saveOrder', [])['error_messages'] ?? '');

        return $message === GUEST_CHECKOUT_REFUSED ? $message : '';
    }
    try {
        Mage::getService('sales/order')->validateQuote($quote, $actor);
    } catch (Mage_Core_Exception_Forbidden $e) {
        return $e->getMessage();
    } catch (Mage_Core_Exception) {
        return '';
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

it('has no guest checkout gate for a staff actor', function () {
    Mage::app()->getStore()->setConfig(Mage_Checkout_Helper_Data::XML_PATH_GUEST_CHECKOUT, '0');

    expect(guestCheckoutViaWeb($this->cart, Mage_Checkout_Model_Type_Onepage::METHOD_GUEST, Mage_Checkout_Model_Actor::Staff))->toBe('');
});

it('refuses the guest order of a customer actor in the order service, before any transport', function () {
    Mage::app()->getStore()->setConfig(Mage_Checkout_Helper_Data::XML_PATH_GUEST_CHECKOUT, '0');

    expect(fn() => Mage::getService('sales/order')->validateQuote($this->cart, Mage_Checkout_Model_Actor::Customer))
        ->toThrow(Mage_Core_Exception_Forbidden::class, GUEST_CHECKOUT_REFUSED);
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
