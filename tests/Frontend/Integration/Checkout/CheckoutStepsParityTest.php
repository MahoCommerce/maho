<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Put;
use Mage\Checkout\Api\CartProcessor;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\AdminApiParity;
use Tests\Helpers\CartParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The one-page checkout and the guest cart API set the addresses, the shipping method and the payment method through
 * Mage_Checkout_Service_Cart (#1348). Each test gives the same input to both transports and compares the outcome.
 */

/**
 * Return a complete US address in the shape of the storefront form, with the fields of $data.
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function checkoutStepsWebAddress(array $data = []): array
{
    return $data + [
        'firstname' => 'Step', 'lastname' => 'Wise', 'email' => 'step.wise@example.com', 'street' => ['123 Setter St'],
        'city' => 'Los Angeles', 'region_id' => '12', 'region' => '', 'postcode' => '90210', 'country_id' => 'US',
        'telephone' => '5550100',
    ];
}

/**
 * Return the address of checkoutStepsWebAddress() in the shape of the API, with the fields of $data.
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function checkoutStepsApiAddress(array $data = []): array
{
    return $data + [
        'firstName' => 'Step', 'lastName' => 'Wise', 'street' => ['123 Setter St'], 'city' => 'Los Angeles',
        'region' => 'California', 'postcode' => '90210', 'countryId' => 'US', 'telephone' => '5550100',
    ];
}

function checkoutStepsOnepage(Mage_Sales_Model_Quote $quote): Mage_Checkout_Model_Type_Onepage
{
    return Mage::getModel('checkout/type_onepage')->setQuote(checkoutStepsReload($quote));
}

function checkoutStepsReload(Mage_Sales_Model_Quote $quote): Mage_Sales_Model_Quote
{
    return Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId());
}

/**
 * Send $body to the guest cart operation $operation, as PUT /api/rest/v2/guest-carts/{id}/$path.
 *
 * @param array<string, mixed> $body
 * @return array{status: int, result: mixed, errors: list<string>, fields: list<string>}
 */
function checkoutStepsApi(Mage_Sales_Model_Quote $quote, string $operation, string $path, array $body): array
{
    $maskedId = (string) $quote->getData('masked_quote_id');
    $request = AdminApiParity::jsonRequest('PUT', "/api/rest/v2/guest-carts/{$maskedId}/{$path}", $body);
    $processor = new CartProcessor(CartParity::guestSecurity());

    return AdminApiParity::api($request, fn() => $processor->process(null, new Put(name: $operation), ['id' => $maskedId], ['request' => $request]));
}

/**
 * Give the shipping address of $quote a rate $code for which the carrier gives an error.
 */
function checkoutStepsAddErrorRate(Mage_Sales_Model_Quote $quote, string $code): void
{
    [$carrier, $method] = explode('_', $code, 2);
    $address = checkoutStepsReload($quote)->getShippingAddress();
    $address->addShippingRate(Mage::getModel('sales/quote_address_rate')
        ->setCode($code)->setCarrier($carrier)->setMethod($method)->setPrice(0)->setErrorMessage('The carrier is not available.'));
    $address->setCollectShippingRates(false)->save();
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    $_SESSION = [];
    Mage::unregister('_singleton/checkout/session');
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $this->carts = [CartParity::cart(), CartParity::cart()];
});

afterEach(function () {
    foreach ($this->carts as $cart) {
        $cart->delete();
    }
});

it('refuses an incomplete address with the same messages', function () {
    [$webCart, $apiCart] = $this->carts;

    $web = checkoutStepsOnepage($webCart)->saveBilling(checkoutStepsWebAddress(['city' => '']), false);
    $api = checkoutStepsApi($apiCart, 'set_guest_billing_address', 'billing-address', checkoutStepsApiAddress(['city' => '']));

    expect($web['error'])->toBe(1)
        ->and($api['status'])->toBe(422)
        ->and($api['errors'])->toBe($web['message'])
        ->and(array_unique($api['fields']))->toBe(['city']);
});

it('stores the address text without HTML tags on both transports', function () {
    [$webCart, $apiCart] = $this->carts;

    $web = checkoutStepsOnepage($webCart)->saveBilling(checkoutStepsWebAddress(['firstname' => 'Step <b>One</b>']), false);
    $api = checkoutStepsApi($apiCart, 'set_guest_billing_address', 'billing-address', checkoutStepsApiAddress(['firstName' => 'Step <b>One</b>']));

    expect($web)->toBe([])
        ->and($api['status'])->toBe(200)
        ->and(checkoutStepsReload($webCart)->getBillingAddress()->getFirstname())->toBe('Step One')
        ->and(checkoutStepsReload($apiCart)->getBillingAddress()->getFirstname())->toBe('Step One');
});

it('stores the place of a shipping estimate without HTML tags', function () {
    $estimate = Mage::getModel('sales/quote_address')->setCountryId('US')->setCity('<i>Los Angeles</i>')->setPostcode('90210');

    Mage::getService('checkout/cart')->estimateShipping(checkoutStepsReload($this->carts[0]), $estimate);

    expect(checkoutStepsReload($this->carts[0])->getShippingAddress()->getCity())->toBe('Los Angeles');
});

it('uses a saved address of the customer of the cart, and refuses an address of another customer', function () {
    $addresses = Mage::getResourceModel('customer/address_collection')->setPageSize(50);
    $own = $addresses->getFirstItem();
    $foreign = null;
    foreach ($addresses as $address) {
        if ((int) $address->getParentId() !== (int) $own->getParentId()) {
            $foreign = $address;
            break;
        }
    }
    if (!$own->getId() || $foreign === null) {
        $this->markTestSkipped('The store has no two customers with a saved address');
    }
    $quote = checkoutStepsReload($this->carts[0]);
    $quote->setCustomer(Mage::getModel('customer/customer')->load($own->getParentId()))->setCustomerIsGuest(false)->save();

    $foreignResult = checkoutStepsOnepage($quote)->saveBilling(['use_for_shipping' => '0'], (int) $foreign->getId());
    $ownResult = checkoutStepsOnepage($quote)->saveBilling(['use_for_shipping' => '1'], (int) $own->getId());

    $saved = checkoutStepsReload($quote);
    expect($foreignResult)->toBe(['error' => 1, 'message' => 'Customer Address is not valid.'])
        ->and($ownResult)->toBe([])
        ->and((int) $saved->getBillingAddress()->getCustomerAddressId())->toBe((int) $own->getId())
        ->and((int) $saved->getShippingAddress()->getCustomerAddressId())->toBe((int) $own->getId())
        ->and($saved->getShippingAddress()->getSameAsBilling())->toBeTrue();
});

describe('a shipping method', function () {
    beforeEach(function () {
        foreach ($this->carts as $cart) {
            Mage::getService('checkout/cart')->setShippingAddress(
                checkoutStepsReload($cart),
                Mage::getService('checkout/cart')->mapAddressInput(checkoutStepsApiAddress()),
            );
        }
    });

    it('that has a rate is selected on both transports', function () {
        [$webCart, $apiCart] = $this->carts;

        $web = checkoutStepsOnepage($webCart)->saveShippingMethod('flatrate_flatrate');
        $api = checkoutStepsApi($apiCart, 'set_guest_shipping_method', 'shipping-method', ['carrierCode' => 'flatrate', 'methodCode' => 'flatrate']);

        expect($web)->toBe([])
            ->and($api['status'])->toBe(200)
            ->and(checkoutStepsReload($webCart)->getShippingAddress()->getShippingMethod())->toBe('flatrate_flatrate')
            ->and(checkoutStepsReload($apiCart)->getShippingAddress()->getShippingMethod())->toBe('flatrate_flatrate');
    });

    it('that has no rate is refused with the same message', function () {
        [$webCart, $apiCart] = $this->carts;

        $web = checkoutStepsOnepage($webCart)->saveShippingMethod('pest_none');
        $api = checkoutStepsApi($apiCart, 'set_guest_shipping_method', 'shipping-method', ['carrierCode' => 'pest', 'methodCode' => 'none']);

        expect($web)->toBe(['error' => -1, 'message' => 'Shipping method is not available for this address'])
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe(['Shipping method is not available for this address']);
    });

    it('whose carrier gives an error is refused on both transports', function () {
        [$webCart, $apiCart] = $this->carts;
        checkoutStepsAddErrorRate($webCart, 'pesterror_down');
        checkoutStepsAddErrorRate($apiCart, 'pesterror_down');

        $web = checkoutStepsOnepage($webCart)->saveShippingMethod('pesterror_down');
        $api = checkoutStepsApi($apiCart, 'set_guest_shipping_method', 'shipping-method', ['carrierCode' => 'pesterror', 'methodCode' => 'down']);

        expect($web)->toBe(['error' => -1, 'message' => 'Shipping method is not available for this address'])
            ->and($api['status'])->toBe(422)
            ->and(checkoutStepsReload($webCart)->getShippingAddress()->getShippingMethod())->not->toBe('pesterror_down');
    });

    it('without a rate is refused on the cart page', function () {
        $quote = checkoutStepsReload($this->carts[0]);
        Mage::getSingleton('checkout/session')->replaceQuote($quote);
        $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/checkout/cart/estimateUpdatePost', 'POST', [
            'estimate_method' => 'pest_none',
            'isAjax' => '1',
        ]));
        $request->setRouteName('checkout')->setControllerName('cart')->setActionName('estimateUpdatePost')->setDispatched(true);
        Mage::app()->setRequest($request);
        $response = new Mage_Core_Controller_Response_Http();

        new Mage_Checkout_CartController($request, $response)->estimateUpdatePostAction();

        expect(Mage::helper('core')->jsonDecode($response->getBody()))->toBe([
            'success' => false,
            'error' => true,
            'message' => 'Shipping method is not available for this address',
        ])->and(checkoutStepsReload($quote)->getShippingAddress()->getShippingMethod())->not->toBe('pest_none');
    });
});

describe('a payment method', function () {
    it('that is available is selected on both transports', function () {
        [$webCart, $apiCart] = $this->carts;

        $web = checkoutStepsOnepage($webCart)->savePayment(['method' => 'checkmo']);
        $api = checkoutStepsApi($apiCart, 'set_guest_payment_method', 'payment-method', ['methodCode' => 'checkmo']);

        expect($web)->toBe([])
            ->and($api['status'])->toBe(200);
        foreach ([$webCart, $apiCart] as $cart) {
            $saved = checkoutStepsReload($cart);
            expect($saved->getPayment()->getMethod())->toBe('checkmo');
        }
    });

    it('that the cart cannot use is refused on both transports', function () {
        [$webCart, $apiCart] = $this->carts;
        Mage::app()->getStore()->setConfig('payment/checkmo/min_order_total', '999999');

        expect(fn() => checkoutStepsOnepage($webCart)->savePayment(['method' => 'checkmo']))
            ->toThrow(Mage_Core_Exception::class, 'The requested Payment Method is not available.');
        $api = checkoutStepsApi($apiCart, 'set_guest_payment_method', 'payment-method', ['methodCode' => 'checkmo']);

        expect($api['status'])->toBe(422)
            ->and($api['errors'])->toBe(['Payment method is not available: The requested Payment Method is not available.'])
            ->and(checkoutStepsReload($webCart)->getPayment()->getMethod())->toBeNull()
            ->and(checkoutStepsReload($apiCart)->getPayment()->getMethod())->toBeNull();
    });

    it('that the store does not have is refused on the storefront', function () {
        expect(fn() => checkoutStepsOnepage($this->carts[0])->savePayment(['method' => 'pest_unknown']))
            ->toThrow(Mage_Core_Exception::class, 'The requested Payment Method is not available.');
    });
});

describe('a card method (D12)', function () {
    it('takes the card fields of the storefront form, and the API refuses it', function () {
        [$webCart, $apiCart] = $this->carts;
        $store = Mage::app()->getStore();
        $store->setConfig('payment/authorizenet/active', '1');
        $store->setConfig('payment/authorizenet/cctypes', 'VI');
        $store->setConfig('payment/authorizenet/useccv', '0');
        $card = ['cc_type' => 'VI', 'cc_number' => '4111111111111111', 'cc_exp_month' => '12', 'cc_exp_year' => (string) ((int) date('Y') + 2)];

        checkoutStepsOnepage($webCart)->savePayment(['method' => 'authorizenet'] + $card);
        $api = checkoutStepsApi($apiCart, 'set_guest_payment_method', 'payment-method', ['methodCode' => 'authorizenet', 'additionalData' => $card]);

        expect(checkoutStepsReload($webCart)->getPayment()->getMethod())->toBe('authorizenet')
            ->and($api['status'])->toBe(422)
            ->and($api['errors'])->toBe(['Payment method is not available for this cart']);
    });
});
