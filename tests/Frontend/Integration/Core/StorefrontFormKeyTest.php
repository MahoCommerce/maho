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

uses(Tests\MahoFrontendTestCase::class);

/**
 * Every storefront route that accepts a body needs the form key, and the few that do not
 * are listed here. A new entry in this list is a deliberate decision, not an accident.
 */
const STOREFRONT_PUBLIC_ACTIONS = [
    'Maho_ApiPlatform_IndexController::index',
    'Maho_ApiPlatform_IndexController::jsonrpc',
    'Maho_ApiPlatform_IndexController::soap',
    'Maho_ApiPlatform_IndexController::v2Soap',
    'Maho_ApiPlatform_IndexController::xmlrpc',
    'Maho_Paypal_CheckoutController::shippingCallback',
    'Maho_Paypal_WebhookController::index',
    'Mage_Newsletter_SubscriberController::unsubscribe',
    'Mage_Oauth_InitiateController::index',
    'Mage_Oauth_TokenController::index',
];

/**
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
function sfkPostRoutes(): array
{
    $compiled = include Mage::getBaseDir() . '/vendor/composer/maho_attributes.php';
    $routes = [];
    foreach ($compiled['routes'] as $route) {
        if ($route['area'] !== 'frontend') {
            continue;
        }
        if ($route['methods'] !== [] && !in_array('POST', $route['methods'], true)) {
            continue;
        }
        $action = substr($route['action'], 0, -strlen('Action'));
        $routes[$route['class'] . '::' . $action] = [$route['class'], $route['controllerName'], $action];
    }
    ksort($routes);
    return $routes;
}

function sfkController(string $class, string $controllerName, string $action, array $post): Mage_Core_Controller_Front_Action
{
    $path = '/' . $controllerName . '/' . $action;
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create($path, 'POST', $post));
    $request->setPathInfo($path);
    $request->setControllerName($controllerName)
        ->setActionName($action)
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    return new $class($request, new Mage_Core_Controller_Response_Http());
}

function sfkRedirectUrl(Mage_Core_Controller_Response_Http $response): ?string
{
    foreach ($response->getHeaders() as $header) {
        if (strcasecmp($header['name'], 'Location') === 0) {
            return $header['value'];
        }
    }
    return null;
}

function sfkIsFormKeyRequired(Mage_Core_Controller_Front_Action $controller): bool
{
    $method = new ReflectionMethod($controller, '_isFormKeyRequired');
    $method->setAccessible(true);
    return (bool) $method->invoke($controller);
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::getSingleton('core/session')->getMessages(true);
    unset($_SERVER['HTTP_REFERER']);
});

afterEach(function () {
    unset($_SERVER['HTTP_REFERER']);
});

it('finds the storefront routes that accept a body', function () {
    expect(count(sfkPostRoutes()))->toBeGreaterThan(50);
});

it('asks every storefront route that accepts a body for the form key', function () {
    $missing = [];
    foreach (sfkPostRoutes() as $key => [$class, $controllerName, $action]) {
        $controller = sfkController($class, $controllerName, $action, []);
        $required = sfkIsFormKeyRequired($controller);
        $expected = !in_array($key, STOREFRONT_PUBLIC_ACTIONS, true);
        if ($required !== $expected) {
            $missing[] = $key;
        }
    }

    expect($missing)->toBe([]);
});

it('lists no stale public action', function () {
    $known = array_keys(sfkPostRoutes());
    foreach (STOREFRONT_PUBLIC_ACTIONS as $key) {
        expect($known)->toContain($key);
    }
});

it('never asks a GET request for the form key', function () {
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/checkout/cart/add', 'GET'));
    $request->setControllerName('cart')->setActionName('add')->setDispatched(true);
    Mage::app()->setRequest($request);

    $controller = new Mage_Checkout_CartController($request, new Mage_Core_Controller_Response_Http());

    expect(sfkIsFormKeyRequired($controller))->toBeFalse();
});

it('sends a plain request back to the referer with an error', function () {
    $controller = sfkController('Mage_Checkout_CartController', 'cart', 'add', ['product' => 1]);
    $_SERVER['HTTP_REFERER'] = Mage::getBaseUrl() . 'checkout/cart';

    $controller->dispatch('add');

    expect(sfkRedirectUrl($controller->getResponse()))->toBe(Mage::getBaseUrl() . 'checkout/cart');
    expect($controller->getFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH))->toBeTrue();
    expect(Mage::getSingleton('core/session')->getMessages()->getLastAddedMessage()->getText())
        ->toBe('Invalid form key. Please refresh the page.');
});

it('ignores a referer that points outside the store', function () {
    $controller = sfkController('Mage_Checkout_CartController', 'cart', 'add', ['product' => 1]);
    $_SERVER['HTTP_REFERER'] = 'https://evil.example.com/';

    $controller->dispatch('add');

    expect(sfkRedirectUrl($controller->getResponse()))->toBe(Mage::getBaseUrl());
});

it('answers an AJAX request with a 403 and a JSON body', function () {
    $controller = sfkController('Mage_Checkout_CartController', 'cart', 'add', ['product' => 1, 'isAjax' => 1]);

    $controller->dispatch('add');

    expect($controller->getResponse()->getHttpResponseCode())->toBe(403);
    expect(Mage::helper('core')->jsonDecode($controller->getResponse()->getBody()))->toBe([
        'error' => true,
        'message' => 'Invalid form key. Please refresh the page.',
    ]);
});

it('runs a public action without a form key', function () {
    $controller = sfkController('Maho_Paypal_WebhookController', 'webhook', 'index', []);

    $controller->dispatch('index');

    expect($controller->getFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH))->toBeFalse();
    expect(Mage::getSingleton('core/session')->getMessages()->count())->toBe(0);
});
