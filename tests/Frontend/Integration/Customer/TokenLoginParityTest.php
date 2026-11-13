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
 * Mage_Customer_Service_Authentication (#1348), so they share the login limits, the second factor rule and the
 * answer for an account that is not confirmed.
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
function tokenLoginWeb(string $email, string $password, ?string $twofaCode = null): array
{
    $params = ['login' => ['username' => $email, 'password' => $password]];
    if ($twofaCode !== null) {
        $params['login']['twofa_verification_code'] = $twofaCode;
    }
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
function tokenLoginApi(string $email, string $password, ?string $twofaCode = null): array
{
    $body = ['email' => $email, 'password' => $password] + ($twofaCode === null ? [] : ['twofa_code' => $twofaCode]);
    $request = SymfonyRequest::create('/api/rest/v2/auth/token', 'POST', content: (string) json_encode($body));
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
    Mage::app()->getStore()->setConfig(Mage_Customer_Service_Authentication::XML_PATH_LOGIN_RATE_LIMIT, '1');

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
        ->and($api['message'])->toBe(Mage::helper('customer')->__('Invalid login or password.'));
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
    /** @var Mage_Customer_Service_Authentication $service */
    $service = Mage::getService('customer/authentication');
    $websiteId = (int) Mage::app()->getStore()->getWebsiteId();

    expect(fn() => $service->authenticate($customer->getEmail(), TOKEN_LOGIN_PASSWORD, $websiteId))
        ->toThrow(Mage_Core_Exception::class, Mage::helper('customer')->__('Invalid login or password.'))
        ->and((int) $service->authenticateInAnyWebsite($customer->getEmail(), TOKEN_LOGIN_PASSWORD, $websiteId)->getId())
        ->toBe((int) $customer->getId());
});

/**
 * Ask the storefront whether the login needs a 2FA code, as the login form does before it posts.
 */
function tokenLoginWebPrelogin(string $email, string $password): bool
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/customer/account/prelogin', 'POST', [
        'login' => ['username' => $email, 'password' => $password],
    ]));
    $request->setRouteName('customer')->setControllerName('account')->setActionName('prelogin')
        ->setControllerModule('Mage_Customer')->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    new Mage_Customer_AccountController($request, $response)->preloginAction();

    return (bool) (json_decode((string) $response->getBody(), true)['require_twofa'] ?? false);
}

it('asks a customer who uses 2FA for the code on the storefront and the customer grant, and counts the login one time', function () {
    $store = Mage::app()->getStore();
    $store->setConfig('customer/password/allow_2fa', '1');
    $store->setConfig(Mage_Customer_Service_Authentication::XML_PATH_LOGIN_RATE_LIMIT, '2');
    $secret = Mage::helper('core/security')->generateTotpSecret();
    $customer = tokenLoginCustomer();
    $customer->setTwofaSecret($secret)->setTwofaEnabled()->save();
    $email = (string) $customer->getEmail();

    $prelogin = tokenLoginWebPrelogin($email, TOKEN_LOGIN_PASSWORD);
    $webNoCode = tokenLoginWeb($email, TOKEN_LOGIN_PASSWORD);
    $apiNoCode = tokenLoginApi($email, TOKEN_LOGIN_PASSWORD);
    $isLoggedInWithoutCode = Mage::getSingleton('customer/session')->isLoggedIn();
    // The three requests for the code did not count. The two logins with the code are the limit of the email
    $totp = \OTPHP\TOTP::createFromSecret($secret);
    // The code must stay valid through the password check, so it must not come from the end of a period
    if ($totp->expiresIn() < 5) {
        sleep($totp->expiresIn() + 1);
    }
    $web = tokenLoginWeb($email, TOKEN_LOGIN_PASSWORD, $totp->now());
    $isLoggedIn = Mage::getSingleton('customer/session')->isLoggedIn();
    $apiWrongCode = tokenLoginApi($email, TOKEN_LOGIN_PASSWORD, '000000x');
    $apiOverLimit = tokenLoginApi($email, TOKEN_LOGIN_PASSWORD, \OTPHP\TOTP::createFromSecret($secret)->now());

    expect($prelogin)->toBeTrue()
        ->and($webNoCode['errors'])->toBe([Mage::helper('customer')->__('Two-factor authentication code is required.')])
        ->and($apiNoCode['status'])->toBe(401)
        ->and($apiNoCode['message'])->toBe($webNoCode['errors'][0])
        ->and($isLoggedInWithoutCode)->toBeFalse()
        ->and($web['errors'])->toBe([])
        ->and($isLoggedIn)->toBeTrue()
        ->and($apiWrongCode['status'])->toBe(401)
        ->and($apiWrongCode['message'])->toBe(Mage::helper('customer')->__('2FA verification code is invalid.'))
        ->and($apiOverLimit['status'])->toBe(429);
});

it('gives the customer grant the error code of each refused login', function (?string $password, ?string $twofaCode, string $errorCode) {
    Mage::app()->getStore()->setConfig('customer/password/allow_2fa', '1');
    $secret = Mage::helper('core/security')->generateTotpSecret();
    $customer = tokenLoginCustomer();
    $customer->setTwofaSecret($secret)->setTwofaEnabled()->save();
    /** @var Mage_Customer_Service_Authentication $service */
    $service = Mage::getService('customer/authentication');

    try {
        $service->authenticateInAnyWebsite((string) $customer->getEmail(), $password ?? TOKEN_LOGIN_PASSWORD, (int) $customer->getWebsiteId(), $twofaCode);
        $error = null;
    } catch (Mage_Core_Exception_Unauthorized $e) {
        $error = $e;
    }

    expect($error?->getErrorCode())->toBe($errorCode)
        ->and(Maho\ApiPlatform\EventListener\ApiExceptionListener::mageExceptionStatus($error))->toBe(401)
        ->and(Maho\ApiPlatform\EventListener\ApiExceptionListener::mageExceptionError($error))->toBe($errorCode);
})->with([
    'a wrong password' => ['wrong-password', null, 'invalid_credentials'],
    'no 2FA code' => [null, null, 'twofa_required'],
    'an empty 2FA code' => [null, '', 'twofa_required'],
    'a wrong 2FA code' => [null, '000000x', 'twofa_invalid'],
]);
