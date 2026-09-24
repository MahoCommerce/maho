<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoBackendTestCase::class);

/**
 * The admin actions that run before login, such as forgotpassword, get the form key check
 * from the base class, the same as every action after login.
 */

function loggedOutAdminController(string $action, array $post): Mage_Adminhtml_IndexController
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/admin/index/' . $action, 'POST', $post));
    $request->setRouteName('adminhtml')
        ->setControllerName('index')
        ->setActionName($action)
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    return new Mage_Adminhtml_IndexController($request, new Mage_Core_Controller_Response_Http());
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
});

it('refuses a POST to an action that runs before login when the form key is missing', function () {
    $controller = loggedOutAdminController('forgotpassword', ['email' => 'nobody@example.com']);

    $controller->preDispatch();

    expect(Mage::getSingleton('admin/session')->isLoggedIn())->toBeFalse()
        ->and($controller->getFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH))->toBeTrue();
});

it('runs a POST to an action that runs before login when the form key is valid', function () {
    $controller = loggedOutAdminController('forgotpassword', [
        'email' => 'nobody@example.com',
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
    ]);

    $controller->preDispatch();

    expect($controller->getFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH))->toBeFalsy();
});

it('leaves a failed login that the observer forwarded to the login page alone', function () {
    // The observer renews the form key after a login attempt, so the posted key is old here
    $controller = loggedOutAdminController('login', ['login' => [], 'form_key' => 'renewed-since']);
    $controller->getRequest()->setInternallyForwarded();

    $controller->preDispatch();

    expect($controller->getFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH))->toBeFalsy();
});
