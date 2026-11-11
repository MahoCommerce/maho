<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Mage\Checkout\Api\CartRequest;
use Mage\Sales\Api\OrderProcessor;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\CartParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * POST /guest-carts/{id}/place-order can hold the addresses, the shipping method and the payment data of the order.
 * Mage_Checkout_Service_Cart::applyCheckoutState() applies them in one pass and saves the cart once (#1348).
 */

/**
 * @param array<string, mixed> $data
 * @return array<string, mixed> a complete US address of the API, with the fields of $data
 */
function applyCheckoutStateAddress(array $data = []): array
{
    return $data + [
        'firstName' => 'One', 'lastName' => 'Pass', 'street' => ['1 Main St'], 'city' => 'Los Angeles',
        'region' => 'California', 'postcode' => '90210', 'countryId' => 'US', 'telephone' => '5550100',
    ];
}

function applyCheckoutStateReload(Mage_Sales_Model_Quote $quote): Mage_Sales_Model_Quote
{
    return Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId());
}

/**
 * Post $body to /api/rest/v2/guest-carts/{id}/place-order as a guest.
 *
 * @param array<string, mixed> $body
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function applyCheckoutStateViaApi(Mage_Sales_Model_Quote $quote, array $body): array
{
    $maskedId = (string) $quote->getData('masked_quote_id');
    $request = AdminApiParity::jsonRequest('POST', "/api/rest/v2/guest-carts/{$maskedId}/place-order", $body);
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
    $order = Mage::getModel('sales/order')->load($this->cart->getId(), 'quote_id');
    if ($order->getId()) {
        Mage::register('isSecureArea', true, true);
        $order->delete();
        Mage::unregister('isSecureArea');
    }
    $this->cart->delete();
});

it('applies the addresses, the shipping method and the payment method, and saves the cart', function () {
    /** @var Mage_Checkout_Service_Cart $service */
    $service = Mage::getService('checkout/cart');
    $address = CartRequest::mapAddress(applyCheckoutStateAddress());

    $service->applyCheckoutState(applyCheckoutStateReload($this->cart), $address, $address, 'flatrate_flatrate', 'checkmo');

    $saved = applyCheckoutStateReload($this->cart);
    expect($saved->getShippingAddress()->getCity())->toBe('Los Angeles')
        ->and($saved->getBillingAddress()->getCity())->toBe('Los Angeles')
        ->and((int) $saved->getShippingAddress()->getRegionId())->toBe(12)
        ->and($saved->getShippingAddress()->getShippingMethod())->toBe('flatrate_flatrate')
        ->and((float) $saved->getShippingAddress()->getShippingAmount())->toBeGreaterThan(0.0)
        ->and($saved->getPayment()->getMethod())->toBe('checkmo');
});

it('keeps what the cart has for a value that is null', function () {
    /** @var Mage_Checkout_Service_Cart $service */
    $service = Mage::getService('checkout/cart');
    $address = CartRequest::mapAddress(applyCheckoutStateAddress());
    $service->applyCheckoutState(applyCheckoutStateReload($this->cart), $address, $address, 'flatrate_flatrate', 'checkmo');

    $service->applyCheckoutState(applyCheckoutStateReload($this->cart));

    $saved = applyCheckoutStateReload($this->cart);
    expect($saved->getShippingAddress()->getCity())->toBe('Los Angeles')
        ->and($saved->getShippingAddress()->getShippingMethod())->toBe('flatrate_flatrate')
        ->and($saved->getPayment()->getMethod())->toBe('checkmo');
});

it('saves nothing when the shipping method has no rate', function () {
    /** @var Mage_Checkout_Service_Cart $service */
    $service = Mage::getService('checkout/cart');
    $address = CartRequest::mapAddress(applyCheckoutStateAddress());

    expect(fn() => $service->applyCheckoutState(applyCheckoutStateReload($this->cart), $address, $address, 'pest_none', 'checkmo'))
        ->toThrow(Mage_Core_Exception::class, 'Shipping method is not available for this address');

    $saved = applyCheckoutStateReload($this->cart);
    expect((string) $saved->getShippingAddress()->getCity())->toBe('')
        ->and((string) $saved->getShippingAddress()->getShippingMethod())->toBe('')
        ->and($saved->getPayment()->getMethod())->toBeNull();
});

it('saves nothing when the payment method is not available', function () {
    /** @var Mage_Checkout_Service_Cart $service */
    $service = Mage::getService('checkout/cart');
    $address = CartRequest::mapAddress(applyCheckoutStateAddress());

    expect(fn() => $service->applyCheckoutState(applyCheckoutStateReload($this->cart), $address, $address, 'flatrate_flatrate', 'pest_unknown'))
        ->toThrow(Mage_Core_Exception::class, 'Payment method is not available for this cart');

    $saved = applyCheckoutStateReload($this->cart);
    expect((string) $saved->getShippingAddress()->getCity())->toBe('')
        ->and((string) $saved->getShippingAddress()->getShippingMethod())->toBe('');
});

it('names the address of each address error', function () {
    /** @var Mage_Checkout_Service_Cart $service */
    $service = Mage::getService('checkout/cart');
    $address = CartRequest::mapAddress(applyCheckoutStateAddress());
    $fields = [];
    try {
        $service->applyCheckoutState(applyCheckoutStateReload($this->cart), $address, ['city' => ''] + $address);
    } catch (Mage_Core_Exception_Input $e) {
        $fields = array_values(array_unique(array_column($e->getErrors(), 'field')));
    }

    expect($fields)->toBe(['billing_address.city']);
});

describe('the place order endpoint of a guest cart', function () {
    it('places the order with the checkout data of the body', function () {
        $api = applyCheckoutStateViaApi($this->cart, [
            'email' => 'one.pass@example.com',
            'shippingAddress' => applyCheckoutStateAddress(),
            'billingAddress' => applyCheckoutStateAddress(),
            'shippingMethod' => 'flatrate_flatrate',
            'paymentMethod' => 'checkmo',
        ]);

        $order = Mage::getModel('sales/order')->load($this->cart->getId(), 'quote_id');
        expect($api['status'])->toBe(200)
            ->and($order->getShippingMethod())->toBe('flatrate_flatrate')
            ->and($order->getPayment()->getMethod())->toBe('checkmo')
            ->and($order->getShippingAddress()->getCity())->toBe('Los Angeles');
    });

    it('names the fields of an address error as the client sends them, and saves nothing', function () {
        $api = applyCheckoutStateViaApi($this->cart, [
            'email' => 'one.pass@example.com',
            'shippingAddress' => applyCheckoutStateAddress(),
            'billingAddress' => applyCheckoutStateAddress(['firstName' => '', 'postcode' => '']),
            'shippingMethod' => 'flatrate_flatrate',
            'paymentMethod' => 'checkmo',
        ]);

        $fields = array_values(array_unique($api['fields']));
        sort($fields);
        expect($api['status'])->toBe(422)
            ->and($fields)->toBe(['billingAddress.firstName', 'billingAddress.postcode'])
            ->and((string) applyCheckoutStateReload($this->cart)->getShippingAddress()->getCity())->toBe('')
            ->and(Mage::getModel('sales/order')->load($this->cart->getId(), 'quote_id')->getId())->toBeNull();
    });
});
