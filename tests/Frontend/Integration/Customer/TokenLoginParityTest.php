<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Post;
use Maho\ApiPlatform\Api\AuthTokenProcessor;
use Maho\ApiPlatform\Service\JwtService;
use Maho\ApiPlatform\Service\TokenBlacklist;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Helpers\TransportParity;

uses(Tests\MahoFrontendTestCase::class);

/**
 * The storefront login and the customer grant of POST /auth/token check the login through
 * Mage_Customer_Service_Customer (#1348), so they share the login limits and the answer for an account that is
 * not confirmed.
 */

const TOKEN_LOGIN_PASSWORD = 'Password123!';
const TOKEN_LOGIN_WEBSITE = 'token_login';

function tokenLoginCustomer(?int $websiteId = null): Mage_Customer_Model_Customer
{
    $websiteId ??= (int) Mage::app()->getStore()->getWebsiteId();
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId($websiteId)
        ->setStoreId((int) Mage::app()->getWebsite($websiteId)->getDefaultStore()->getId())
        ->setEmail('token-login-' . bin2hex(random_bytes(4)) . '@example.com')
        ->setFirstname('Token')
        ->setLastname('Login')
        ->setPassword(TOKEN_LOGIN_PASSWORD);
    $customer->save();
    return $customer;
}

/**
 * @return array{success: list<string>, errors: list<string>}
 */
function tokenLoginWeb(string $email, string $password): array
{
    $params = ['login' => ['username' => $email, 'password' => $password]];
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/customer/account/loginPost', 'POST', $params));
    $request->setPathInfo('/customer/account/loginPost');
    $request->setRouteName('customer');
    $request->setControllerName('account');
    $request->setActionName('loginPost');
    $request->setControllerModule('Mage_Customer');
    $request->setDispatched(true);
    Mage::app()->setRequest($request);

    new Mage_Customer_AccountController($request, new Mage_Core_Controller_Response_Http())->loginPostAction();

    $messages = ['success' => [], 'errors' => []];
    foreach (Mage::getSingleton('customer/session')->getMessages(true)->getItems() as $message) {
        $messages[$message->getType() === Mage_Core_Model_Message::SUCCESS ? 'success' : 'errors'][] = $message->getText();
    }
    return $messages;
}

/**
 * @return array{status: int, message: string, fields: list<string>, result: mixed}
 */
function tokenLoginApi(string $email, string $password): array
{
    $request = SymfonyRequest::create('/api/rest/v2/auth/token', 'POST', content: (string) json_encode(['email' => $email, 'password' => $password]));
    $processor = new AuthTokenProcessor(TransportParity::adminSecurity(), new JwtService(), new TokenBlacklist());

    return TransportParity::api(fn() => $processor->process(null, new Post(name: 'get_token'), [], ['request' => $request]));
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::getSingleton('customer/session')->logout();
    Mage::getSingleton('customer/session')->getMessages(true);

    $store = Mage::app()->getStore();
    $store->setConfig('customer/account/enabled_in_frontend', '1');
    $store->setConfig('customer/create_account/confirm', '0');
    $store->setConfig('system/smtp/enabled', '');
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
});

afterEach(function () {
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    Mage::getSingleton('customer/session')->logout();
    Mage::register('isSecureArea', true, true);
    $customers = Mage::getResourceModel('customer/customer_collection')->addAttributeToFilter('email', ['like' => 'token-login-%@example.com']);
    foreach ($customers as $customer) {
        $customer->delete();
    }
    Mage::unregister('isSecureArea');
    deletePriceWebsite(TOKEN_LOGIN_WEBSITE);
});

it('counts the storefront login and the customer grant against one limit for each email', function () {
    $customer = tokenLoginCustomer();
    Mage::app()->getStore()->setConfig(Mage_Customer_Service_Customer::XML_PATH_LOGIN_RATE_LIMIT, '1');

    $web = tokenLoginWeb($customer->getEmail(), 'wrong-password');
    $api = tokenLoginApi($customer->getEmail(), TOKEN_LOGIN_PASSWORD);

    expect($web['errors'])->toBe([Mage::helper('customer')->__('Invalid login or password.')])
        ->and($api['status'])->toBe(429)
        ->and($api['message'])->toBe(Mage::helper('customer')->__('Too many requests. Please try again later.'));
});

it('answers a wrong password with an error on the storefront and a 401 on the customer grant', function () {
    $customer = tokenLoginCustomer();

    $web = tokenLoginWeb($customer->getEmail(), 'wrong-password');
    $api = tokenLoginApi($customer->getEmail(), 'wrong-password');

    expect($web['errors'])->toBe([Mage::helper('customer')->__('Invalid login or password.')])
        ->and($api['status'])->toBe(401)
        ->and($api['message'])->toBe('Invalid email or password');
});

it('refuses an account that is not confirmed with a 403 on the customer grant', function () {
    $customer = tokenLoginCustomer();
    $customer->setConfirmation('token-login-confirmation')->save();
    Mage::app()->getStore()->setConfig('customer/create_account/confirm', '1');

    $web = tokenLoginWeb($customer->getEmail(), TOKEN_LOGIN_PASSWORD);
    $api = tokenLoginApi($customer->getEmail(), TOKEN_LOGIN_PASSWORD);

    expect($web['errors'])->toHaveCount(1)
        ->and(Mage::getSingleton('customer/session')->isLoggedIn())->toBeFalse()
        ->and($api['status'])->toBe(403)
        ->and($api['message'])->toBe(Mage::helper('customer')->__('This account is not confirmed. Please check your email for the confirmation link.'));
});

it('finds the customer of another website when the client names no store', function () {
    $website = createPriceWebsite(TOKEN_LOGIN_WEBSITE);
    Mage::app()->getStore(TOKEN_LOGIN_WEBSITE)->setConfig('customer/create_account/confirm', '0');
    Mage::app()->getStore()->setConfig(Mage_Customer_Model_Config_Share::XML_PATH_CUSTOMER_ACCOUNT_SHARE, '1');
    $customer = tokenLoginCustomer((int) $website->getId());
    /** @var Mage_Customer_Service_Customer $service */
    $service = Mage::getService('customer/customer');
    $websiteId = (int) Mage::app()->getStore()->getWebsiteId();

    expect(fn() => $service->authenticate($customer->getEmail(), TOKEN_LOGIN_PASSWORD, $websiteId))
        ->toThrow(Mage_Core_Exception::class, Mage::helper('customer')->__('Invalid login or password.'))
        ->and((int) $service->authenticateInAnyWebsite($customer->getEmail(), TOKEN_LOGIN_PASSWORD, $websiteId)->getId())
        ->toBe((int) $customer->getId());
});
