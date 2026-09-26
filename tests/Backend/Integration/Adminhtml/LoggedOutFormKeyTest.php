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

it('answers an AJAX request with an old form key in JSON, so the login page can show the message', function (string $action) {
    $controller = loggedOutAdminController($action, ['login' => ['username' => 'admin'], 'form_key' => 'old']);
    $controller->getRequest()->setParam('isAjax', 'true');

    $controller->preDispatch();

    $contentType = '';
    foreach ($controller->getResponse()->getHeaders() as $header) {
        if (strcasecmp($header['name'], 'Content-Type') === 0) {
            $contentType = $header['value'];
        }
    }
    expect($contentType)->toBe('application/json')
        ->and(Mage::helper('core')->jsonDecode($controller->getResponse()->getBody()))
        ->toBe(['error' => true, 'message' => 'Invalid Form Key. Please refresh the page.']);
})->with(['prelogin', 'passkeyloginstart']);

it('sends no reset email for a GET to forgotpassword', function () {
    $suffix = substr(md5(uniqid()), 0, 8);
    $user = Mage::getModel('admin/user')
        ->setUsername('fpget_' . $suffix)
        ->setFirstname('Forgot')
        ->setLastname('Get')
        ->setEmail("fpget_{$suffix}@example.com")
        ->setPassword('Fp-Get-P4ssword-1')
        ->setIsActive()
        ->save();

    try {
        $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/admin/index/forgotpassword', 'GET', [
            'email' => $user->getEmail(),
            'form_key' => Mage::getSingleton('core/session')->getFormKey(),
        ]));
        $request->setRouteName('adminhtml')->setControllerName('index')->setActionName('forgotpassword')->setDispatched(true);
        Mage::app()->setRequest($request);
        $controller = new Mage_Adminhtml_IndexController($request, new Mage_Core_Controller_Response_Http());

        $controller->dispatch('forgotpassword');

        expect(Mage::getModel('admin/user')->load($user->getId())->getRpToken())->toBeNull();
    } finally {
        $user->delete();
    }
});
