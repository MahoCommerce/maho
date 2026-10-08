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

/**
 * The storefront forgot / reset password form keeps its behaviour: the request stores
 * a new link, and the finished reset clears it and confirms the email address.
 */
function dispatchPasswordResetPost(string $action, array $post): void
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/customer/account/' . $action, 'POST', $post),
    );
    $request->setRouteName('customer')
        ->setControllerName('account')
        ->setActionName($action)
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    $controller = new Mage_Customer_AccountController($request, new Mage_Core_Controller_Response_Http());
    $controller->{$action . 'Action'}();
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    Mage::getSingleton('customer/session')->logout();
    Mage::app()->getStore()->setConfig('customer/account/enabled_in_frontend', '1');
    Mage::app()->getStore()->setConfig('system/smtp/enabled', '');

    $this->customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
        ->setEmail('form-reset-' . bin2hex(random_bytes(4)) . '@example.com')
        ->setFirstname('Form')
        ->setLastname('Reset')
        ->setPassword('OldPassword1')
        ->save();
});

afterEach(function () {
    // The customer session outlives the test; a leftover token breaks later reset link tests
    Mage::getSingleton('customer/session')
        ->unsetData(Mage_Customer_AccountController::TOKEN_SESSION_NAME)
        ->unsetData(Mage_Customer_AccountController::CUSTOMER_ID_SESSION_NAME);
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    Mage::unregister('isSecureArea');
});

it('stores a new reset token and customer id when the form is submitted', function () {
    dispatchPasswordResetPost('forgotPasswordPost', ['email' => $this->customer->getEmail()]);

    $reloaded = Mage::getModel('customer/customer')->load($this->customer->getId());
    expect($reloaded->getRpToken())->toMatch('/^[0-9a-f]{32}$/')
        ->and($reloaded->getRpCustomerId())->toMatch('/^[0-9a-f]{32}$/')
        ->and($reloaded->isResetPasswordLinkTokenExpired())->toBeFalse();
});

it('clears the link and confirms the email when the new password is saved', function () {
    $helper = Mage::helper('customer');
    $token = $helper->generateResetPasswordLinkToken();
    $this->customer->setConfirmation($this->customer->getRandomConfirmationKey())->save();
    $this->customer->changeResetPasswordLinkCustomerId($helper->generateResetPasswordLinkCustomerId($this->customer->getId()));
    $this->customer->changeResetPasswordLinkToken($token);
    Mage::getSingleton('customer/session')
        ->setData(Mage_Customer_AccountController::CUSTOMER_ID_SESSION_NAME, (int) $this->customer->getId())
        ->setData(Mage_Customer_AccountController::TOKEN_SESSION_NAME, $token);
    $before = time();

    dispatchPasswordResetPost('resetPasswordPost', ['password' => 'NewPassword1', 'confirmation' => 'NewPassword1']);

    $reloaded = Mage::getModel('customer/customer')->load($this->customer->getId());
    expect($reloaded->validatePassword('NewPassword1'))->toBeTrue()
        ->and($reloaded->getRpToken())->toBeEmpty()
        ->and($reloaded->getRpTokenCreatedAt())->toBeEmpty()
        ->and($reloaded->getRpCustomerId())->toBeEmpty()
        ->and($reloaded->getConfirmation())->toBeNull()
        ->and($reloaded->getPasswordCreatedAt())->toBeGreaterThanOrEqual($before)
        ->and(Mage::getSingleton('customer/session')->getData(Mage_Customer_AccountController::TOKEN_SESSION_NAME))->toBeNull();
});

it('keeps the old password when the new one is too long', function () {
    $helper = Mage::helper('customer');
    $token = $helper->generateResetPasswordLinkToken();
    $this->customer->changeResetPasswordLinkCustomerId($helper->generateResetPasswordLinkCustomerId($this->customer->getId()));
    $this->customer->changeResetPasswordLinkToken($token);
    Mage::getSingleton('customer/session')
        ->setData(Mage_Customer_AccountController::CUSTOMER_ID_SESSION_NAME, (int) $this->customer->getId())
        ->setData(Mage_Customer_AccountController::TOKEN_SESSION_NAME, $token);
    $tooLong = str_repeat('a1', 150);

    dispatchPasswordResetPost('resetPasswordPost', ['password' => $tooLong, 'confirmation' => $tooLong]);

    $reloaded = Mage::getModel('customer/customer')->load($this->customer->getId());
    expect($reloaded->validatePassword('OldPassword1'))->toBeTrue()
        ->and($reloaded->getRpToken())->toBe($token);
});
