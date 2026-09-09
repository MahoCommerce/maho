<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoFrontendTestCase::class);

function setAccountRequest(string $action, array $query): Mage_Core_Controller_Request_Http
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/customer/account/' . $action, 'GET', $query),
    );
    $request->setRouteName('customer')
        ->setControllerName('account')
        ->setActionName($action)
        ->setDispatched(true);
    Mage::app()->setRequest($request);
    return $request;
}

function dispatchAccountAction(string $action, array $query): void
{
    $request = setAccountRequest($action, $query);
    $controller = new Mage_Customer_AccountController($request, new Mage_Core_Controller_Response_Http());
    $controller->{$action . 'Action'}();
}

function tokenLimiter(): \Maho\Security\RateLimiter
{
    return Mage::helper('core')->rateLimiter(
        'customer_token',
        Mage_Customer_AccountController::TOKEN_RATE_LIMIT_MAX_ATTEMPTS,
        Mage_Customer_AccountController::TOKEN_RATE_LIMIT_WINDOW,
    );
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    Mage::getSingleton('customer/session')->logout();
    Mage::app()->getStore()->setConfig('customer/account/enabled_in_frontend', '1');
    Mage::app()->getStore()->setConfig('system/smtp/enabled', '');

    setAccountRequest('index', []);
    tokenLimiter()->clear();

    $this->customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
        ->setEmail('token-limit-' . bin2hex(random_bytes(4)) . '@example.com')
        ->setFirstname('Token')
        ->setLastname('Limit')
        ->setPassword('Password123!')
        ->save();
});

afterEach(function () {
    tokenLimiter()->clear();
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    Mage::unregister('isSecureArea');
});

it('rejects a valid confirmation key after too many failed attempts', function () {
    $this->customer->setConfirmation($this->customer->getRandomConfirmationKey())->save();
    $key = $this->customer->getConfirmation();

    for ($i = 0; $i < Mage_Customer_AccountController::TOKEN_RATE_LIMIT_MAX_ATTEMPTS; $i++) {
        dispatchAccountAction('confirm', ['id' => $this->customer->getId(), 'key' => 'wrong-key-' . $i]);
    }

    dispatchAccountAction('confirm', ['id' => $this->customer->getId(), 'key' => $key]);

    $reloaded = Mage::getModel('customer/customer')->load($this->customer->getId());
    expect($reloaded->getConfirmation())->toBe($key)
        ->and(Mage::getSingleton('customer/session')->isLoggedIn())->toBeFalse();
});

it('confirms the account when the key is right and the limit is not reached', function () {
    $this->customer->setConfirmation($this->customer->getRandomConfirmationKey())->save();

    dispatchAccountAction('confirm', ['id' => $this->customer->getId(), 'key' => $this->customer->getConfirmation()]);

    $reloaded = Mage::getModel('customer/customer')->load($this->customer->getId());
    expect($reloaded->getConfirmation())->toBeNull();
});

it('rejects a valid magic link after too many failed attempts', function () {
    Mage::app()->getStore()->setConfig('customer/login/magic_link_enabled', '1');
    $token = $this->customer->generateMagicLinkToken();
    $this->customer->changeResetPasswordLinkToken($token);

    for ($i = 0; $i < Mage_Customer_AccountController::TOKEN_RATE_LIMIT_MAX_ATTEMPTS; $i++) {
        dispatchAccountAction('magicLinkLogin', ['token' => 'wrong-token-' . $i]);
    }

    dispatchAccountAction('magicLinkLogin', ['token' => $token]);

    expect(Mage::getSingleton('customer/session')->isLoggedIn())->toBeFalse();
});

it('rejects a valid reset link after too many failed attempts', function () {
    $token = Mage::helper('customer')->generateResetPasswordLinkToken();
    $this->customer->changeResetPasswordLinkToken($token);

    for ($i = 0; $i < Mage_Customer_AccountController::TOKEN_RATE_LIMIT_MAX_ATTEMPTS; $i++) {
        dispatchAccountAction('resetPassword', ['id' => $this->customer->getId(), 'token' => 'wrong-token-' . $i]);
    }

    dispatchAccountAction('resetPassword', ['id' => $this->customer->getId(), 'token' => $token]);

    $session = Mage::getSingleton('customer/session');
    expect($session->getData(Mage_Customer_AccountController::TOKEN_SESSION_NAME))->toBeNull();
});
