<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoFrontendTestCase::class);

function loginArrayDispatch(string $action, array $login): Mage_Core_Controller_Response_Http
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create(
        '/customer/account/' . $action,
        'POST',
        ['form_key' => Mage::getSingleton('core/session')->getFormKey(), 'login' => $login],
    ));
    $request->setPathInfo('/customer/account/' . $action)
        ->setRouteName('customer')
        ->setControllerName('account')
        ->setActionName($action)
        ->setControllerModule('Mage_Customer')
        ->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    new Mage_Customer_AccountController($request, $response)->{$action . 'Action'}();
    return $response;
}

beforeEach(function (): void {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::getSingleton('customer/session')->getMessages(true);
});

it('answers an array login field with an error instead of a server error', function (array $login): void {
    loginArrayDispatch('loginPost', $login);

    $errors = [];
    foreach (Mage::getSingleton('customer/session')->getMessages(true)->getItems() as $message) {
        $errors[] = $message->getText();
    }

    expect($errors)->toBe(['Login and password are required.']);
})->with([
    'username array' => [['username' => ['address@example.com'], 'password' => 'secret']],
    'password array' => [['username' => 'address@example.com', 'password' => ['secret']]],
    'both arrays' => [['username' => ['address@example.com'], 'password' => ['secret']]],
]);

it('ignores an array login field during the 2FA precheck', function (): void {
    $response = loginArrayDispatch('prelogin', [
        'username' => ['address@example.com'],
        'password' => ['secret'],
    ]);

    expect((string) $response->getBody())->toBe('[]');
});
