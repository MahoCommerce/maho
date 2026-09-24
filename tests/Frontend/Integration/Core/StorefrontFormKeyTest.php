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
 * A storefront action without a compiled route needs no form key on GET, so each one here
 * only reads data. Give a new action that changes state a route that accepts POST only.
 */
const STOREFRONT_ACTIONS_WITHOUT_ROUTE = [
    'Mage_Api_IndexController::index',
    'Mage_Api_JsonrpcController::index',
    'Mage_Api_SoapController::index',
    'Mage_Api_V2_SoapController::index',
    'Mage_Api_XmlrpcController::index',
    'Mage_Cms_IndexController::defaultIndex',
    'Mage_Cms_IndexController::defaultNoRoute',
    'Mage_Sales_GuestController::creditmemo',
    'Mage_Sales_GuestController::invoice',
    'Mage_Sales_GuestController::print',
    'Mage_Sales_GuestController::printCreditmemo',
    'Mage_Sales_GuestController::printInvoice',
    'Mage_Sales_GuestController::printShipment',
    'Mage_Sales_GuestController::shipment',
    'Mage_Sales_GuestController::view',
    'Mage_Sales_OrderController::creditmemo',
    'Mage_Sales_OrderController::invoice',
    'Mage_Sales_OrderController::print',
    'Mage_Sales_OrderController::printCreditmemo',
    'Mage_Sales_OrderController::printInvoice',
    'Mage_Sales_OrderController::printShipment',
    'Mage_Sales_OrderController::shipment',
    'Mage_Sales_OrderController::view',
];

/**
 * @return list<string>
 */
function sfkActionsWithoutRoute(): array
{
    $compiled = include Mage::getBaseDir() . '/vendor/composer/maho_attributes.php';
    $routed = [];
    foreach ($compiled['routes'] as $route) {
        $routed[strtolower($route['class'] . '::' . $route['action'])] = true;
    }

    $classes = [];
    foreach (Composer\Autoload\ClassLoader::getRegisteredLoaders() as $loader) {
        foreach ($loader->getClassMap() as $class => $path) {
            if (str_contains($path, '/app/code/core/') && str_contains($path, '/controllers/') && !str_contains($path, 'Adminhtml')) {
                $classes[] = $class;
            }
        }
    }

    $actions = [];
    foreach ($classes as $class) {
        $reflection = new ReflectionClass($class);
        if (!$reflection->isSubclassOf(Mage_Core_Controller_Front_Action::class) || $reflection->isAbstract()) {
            continue;
        }
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $declaring = $method->getDeclaringClass()->getName();
            if (!str_ends_with($method->getName(), 'Action')
                || in_array($declaring, [Mage_Core_Controller_Front_Action::class, Mage_Core_Controller_Varien_Action::class], true)
                || isset($routed[strtolower($class . '::' . $method->getName())])
            ) {
                continue;
            }
            $actions[] = $class . '::' . substr($method->getName(), 0, -strlen('Action'));
        }
    }
    sort($actions);
    return $actions;
}

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

function sfkGetController(string $action, string $method = 'GET'): Mage_Checkout_CartController
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/checkout/cart/' . $action . '/id/5', $method));
    $request->setModuleName('checkout')->setControllerName('cart')->setActionName($action)->setDispatched(true);
    Mage::app()->setRequest($request);

    return new Mage_Checkout_CartController($request, new Mage_Core_Controller_Response_Http());
}

it('asks no form key of a GET to an action whose route accepts GET', function () {
    expect(sfkIsFormKeyRequired(sfkGetController('index')))->toBeFalse();
});

it('asks the form key of a GET that reaches a POST-only action through extra path parts', function () {
    expect(sfkIsFormKeyRequired(sfkGetController('delete')))->toBeTrue();
});

it('treats HEAD and OPTIONS like GET', function (string $method) {
    expect(sfkIsFormKeyRequired(sfkGetController('index', $method)))->toBeFalse()
        ->and(sfkIsFormKeyRequired(sfkGetController('delete', $method)))->toBeTrue();
})->with(['HEAD', 'OPTIONS']);

it('runs a GET to a POST-only action when the path carries a valid form key', function () {
    $controller = sfkGetController('delete');
    $controller->getRequest()->setParam('form_key', Mage::getSingleton('core/session')->getFormKey());

    $controller->preDispatch();

    expect($controller->getFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH))->toBeFalsy();
});

it('refuses a GET to a POST-only action when the path carries no form key', function () {
    $controller = sfkGetController('delete');

    $controller->preDispatch();

    expect($controller->getFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH))->toBeTrue();
});

it('asks no form key of a GET to a controller that is not the class of the route', function () {
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/checkout/cart/delete/id/5', 'GET'));
    $request->setModuleName('checkout')->setControllerName('cart')->setActionName('delete')->setDispatched(true);
    Mage::app()->setRequest($request);
    $controller = new class ($request, new Mage_Core_Controller_Response_Http()) extends Mage_Core_Controller_Front_Action {};

    expect(sfkIsFormKeyRequired($controller))->toBeFalse();
});

/** What the system log gained while $work ran, with logging switched on for the duration. */
function sfkLogOutput(callable $work): string
{
    $sizes = function (): array {
        $sizes = [];
        foreach (glob(Mage::getBaseDir('var') . DS . 'log' . DS . 'system*.log') ?: [] as $file) {
            $sizes[$file] = (int) filesize($file);
        }
        return $sizes;
    };

    $before = $sizes();
    $store = Mage::app()->getStore();
    $wasActive = Mage::getStoreConfig('dev/log/active');
    $store->setConfig('dev/log/active', 1);
    try {
        $work();
    } finally {
        $store->setConfig('dev/log/active', $wasActive);
    }

    clearstatcache();
    $output = '';
    foreach ($sizes() as $file => $size) {
        if ($size > ($before[$file] ?? 0)) {
            $output .= (string) file_get_contents($file, false, null, $before[$file] ?? 0);
        }
    }
    return $output;
}

it('logs a refused request with the reason', function (array $post, string $reason) {
    $output = sfkLogOutput(function () use ($post) {
        sfkController('Mage_Checkout_CartController', 'cart', 'add', $post)->dispatch('add');
    });

    expect($output)->toContain('Refused POST')->toContain('cart_add: ' . $reason);
})->with([
    'no key' => [['product' => 1], 'no form key'],
    'wrong key' => [['product' => 1, 'form_key' => 'wrong'], 'a wrong form key'],
]);

it('knows every storefront action without a compiled route', function () {
    expect(sfkActionsWithoutRoute())->toBe(STOREFRONT_ACTIONS_WITHOUT_ROUTE);
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
