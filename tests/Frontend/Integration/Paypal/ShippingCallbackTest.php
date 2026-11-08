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

class ShippingCallbackTestClient extends Maho_Paypal_Model_Api_Client
{
    /** @var array<string, mixed> */
    public static array $orderRequest = [];

    #[\Override]
    public function createOrder(array $orderRequest): array
    {
        self::$orderRequest = $orderRequest;
        return ['id' => 'CALLBACKCREATED'];
    }
}

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

function shippingCallbackToken(string $url): string
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
    return (string) ($params['token'] ?? '');
}

function dispatchPaypalCheckoutAction(string $action, SymfonyRequest $symfonyRequest): Mage_Core_Controller_Response_Http
{
    $request = new Mage_Core_Controller_Request_Http($symfonyRequest);
    $request->setRouteName('paypal')
        ->setControllerName('checkout')
        ->setActionName($action)
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    $controller = new Maho_Paypal_CheckoutController($request, new Mage_Core_Controller_Response_Http());
    $controller->dispatch($action);
    return $controller->getResponse();
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
    return dispatchPaypalCheckoutAction('shippingCallback', SymfonyRequest::create(
        '/paypal/checkout/shippingCallback?' . http_build_query($query),
        'POST',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: $body,
    ));
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

it('sends PayPal the shipping callback URL with the token of the quote', function () {
    $cache = new ReflectionProperty(Mage_Core_Model_Config::class, '_classNameCache');
    $cache->setValue(Mage::getConfig(), []);
    Mage::getConfig()->setNode('global/models/paypal/rewrite/api_client', ShippingCallbackTestClient::class);
    Mage::getSingleton('checkout/session')->setQuoteId((int) $this->quote->getId());

    try {
        $response = dispatchPaypalCheckoutAction('createOrder', SymfonyRequest::create('/paypal/checkout/createOrder', 'POST', [
            'method' => Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT,
            'form_key' => Mage::getSingleton('core/session')->getFormKey(),
        ]));
    } finally {
        Mage::getConfig()->setNode('global/models/paypal/rewrite/api_client', '');
        $cache->setValue(Mage::getConfig(), []);
    }

    $result = Mage::helper('core')->jsonDecode($response->getBody());
    expect($result['success'])->toBeTrue();
    $callbackUrl = ShippingCallbackTestClient::$orderRequest['body']['payment_source']['paypal']['experience_context']
        ['order_update_callback_config']['callback_url'];
    expect($callbackUrl)->toContain('paypal/checkout/shippingCallback')
        ->and(Mage::helper('paypal')->isValidShippingCallbackToken($this->quote, shippingCallbackToken($callbackUrl)))->toBeTrue();
});

it('answers a callback that carries the token of the quote', function () {
    $token = shippingCallbackToken(Mage::helper('paypal')->getShippingCallbackUrl($this->quote));
    $response = shippingCallbackDispatch($this->paypalOrderId, ['token' => $token]);

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

    $token = shippingCallbackToken(Mage::helper('paypal')->getShippingCallbackUrl($otherQuote));
    $response = shippingCallbackDispatch($this->paypalOrderId, ['token' => $token]);

    expect($response->getHttpResponseCode())->toBe(422);
});

it('refuses a token after the encryption key changes', function () {
    $helper = Mage::helper('paypal');
    $token = shippingCallbackToken($helper->getShippingCallbackUrl($this->quote));
    $key = Mage::getEncryptionKeyAsHex();

    try {
        Mage::getConfig()->setNode('global/crypt/key', Mage::generateEncryptionKeyAsHex());
        expect($helper->isValidShippingCallbackToken($this->quote, $token))->toBeFalse();
    } finally {
        Mage::getConfig()->setNode('global/crypt/key', $key);
    }

    expect($helper->isValidShippingCallbackToken($this->quote, $token))->toBeTrue();
});
