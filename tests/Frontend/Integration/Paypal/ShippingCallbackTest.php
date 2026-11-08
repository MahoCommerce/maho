<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal');

/**
 * Save an active quote with one simple product, linked to a PayPal order.
 */
function shippingCallbackQuote(string $paypalOrderId): ?Mage_Sales_Model_Quote
{
    $product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', 1)
        ->addAttributeToSelect(['price', 'name'])
        ->setPageSize(1)
        ->getFirstItem();
    if (!$product->getId()) {
        return null;
    }

    $quote = Mage::getModel('sales/quote')->setStoreId(1)->setIsActive(true);
    $quote->addProduct($product, 1);
    $quote->getShippingAddress()->setCountryId('US')->setPostcode('90210');
    $quote->getPayment()->setMethod(Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT)
        ->setAdditionalInformation('paypal_order_id', $paypalOrderId)
        ->setData('paypal_order_id', $paypalOrderId);
    $quote->collectTotals()->save();
    return $quote;
}

function shippingCallbackToken(Mage_Sales_Model_Quote $quote): string
{
    $query = (string) parse_url(Mage::helper('paypal')->getShippingCallbackUrl($quote), PHP_URL_QUERY);
    parse_str($query, $params);
    return (string) $params['token'];
}

/**
 * @param array<string, mixed> $query
 */
function shippingCallbackDispatch(string $paypalOrderId, array $query): Mage_Core_Controller_Response_Http
{
    $body = Mage::helper('core')->jsonEncode([
        'id' => $paypalOrderId,
        'shipping_address' => ['country_code' => 'US', 'admin_area_1' => 'CA', 'admin_area_2' => 'Los Angeles', 'postal_code' => '90001'],
    ]);
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create(
            '/paypal/checkout/shippingCallback?' . http_build_query($query),
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $body,
        ),
    );
    $request->setRouteName('paypal')
        ->setControllerName('checkout')
        ->setActionName('shippingCallback')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    $controller = new Maho_Paypal_CheckoutController($request, new Mage_Core_Controller_Response_Http());
    $controller->dispatch('shippingCallback');
    return $controller->getResponse();
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    unset($_SESSION['checkout']);
    Mage::unregister('_singleton/checkout/session');
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());

    $this->paypalOrderId = 'CALLBACK' . strtoupper(bin2hex(random_bytes(4)));
    $this->quote = shippingCallbackQuote($this->paypalOrderId);
    if (!$this->quote) {
        $this->markTestSkipped('No simple product available for testing');
    }
});

afterEach(function () {
    if ($this->quote) {
        Mage::register('isSecureArea', true, true);
        $this->quote->delete();
        Mage::unregister('isSecureArea');
    }
    unset($_SESSION['checkout']);
    Mage::unregister('_singleton/checkout/session');
});

it('puts a token in the shipping callback URL', function () {
    $url = Mage::helper('paypal')->getShippingCallbackUrl($this->quote);

    expect($url)->toContain('paypal/checkout/shippingCallback')
        ->and(shippingCallbackToken($this->quote))->toMatch('/^[0-9a-f]{64}$/');
});

it('answers a callback that carries the token of the quote', function () {
    $response = shippingCallbackDispatch($this->paypalOrderId, ['token' => shippingCallbackToken($this->quote)]);

    expect($response->getHttpResponseCode())->toBe(200);
    $result = Mage::helper('core')->jsonDecode($response->getBody());
    expect($result['id'])->toBe($this->paypalOrderId)
        ->and($result['purchase_units'][0]['shipping_options'])->not->toBeEmpty();
    $address = Mage::getModel('sales/quote')->load($this->quote->getId())->getShippingAddress();
    expect($address->getPostcode())->toBe('90001');
});

it('refuses a callback without the token, or with a wrong one, and leaves the quote unchanged', function (array $query) {
    $response = shippingCallbackDispatch($this->paypalOrderId, $query);

    expect($response->getHttpResponseCode())->toBe(422)
        ->and($response->getBody())->toBe('');
    $address = Mage::getModel('sales/quote')->load($this->quote->getId())->getShippingAddress();
    expect($address->getPostcode())->toBe('90210');
})->with([
    'no token' => [[]],
    'empty token' => [['token' => '']],
    'wrong token' => [['token' => str_repeat('0', 64)]],
    'token as an array' => [['token' => ['x']]],
]);

it('refuses the token of another quote', function () {
    $otherQuote = Mage::getModel('sales/quote')->setId((int) $this->quote->getId() + 1);

    $response = shippingCallbackDispatch($this->paypalOrderId, ['token' => shippingCallbackToken($otherQuote)]);

    expect($response->getHttpResponseCode())->toBe(422);
});
