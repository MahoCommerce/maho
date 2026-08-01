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

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    Mage::getSingleton('customer/session')->logout();

    Mage::app()->getStore()->setConfig('customer/account/enabled_in_frontend', '1');
    Mage::app()->getStore()->setConfig('web/cookie/remember_enabled', '1');
});

it('clears Remember Me when the credentials are rejected', function () {
    // Regression: the flag is set from the POST before the credentials are checked, so a failed
    // attempt left it on an unauthenticated session and stretched its lifetime
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/customer/account/loginPost', 'POST', [
            'form_key' => Mage::getSingleton('core/session')->getFormKey(),
            'remember_me' => '1',
            'login' => ['username' => 'no-such-customer@example.com', 'password' => 'wrong-password'],
        ]),
    );
    $request->setRouteName('customer')
        ->setControllerName('account')
        ->setActionName('loginPost')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    $controller = new Mage_Customer_AccountController($request, new Mage_Core_Controller_Response_Http());
    $controller->loginPostAction();

    $session = Mage::getSingleton('customer/session');

    expect($session->isLoggedIn())->toBeFalse()
        ->and($session->getRememberMe())->toBeFalsy();
});
