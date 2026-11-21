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

beforeEach(function (): void {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
});

it('rejects an array login field as a missing field', function (array $login): void {
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/customer/account/loginPost', 'POST', ['login' => $login]),
    );
    $request->setRouteName('customer')
        ->setControllerName('account')
        ->setActionName('loginPost')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    new Mage_Customer_AccountController($request, new Mage_Core_Controller_Response_Http())->loginPostAction();

    $errors = array_map(
        fn($message) => $message->getText(),
        Mage::getSingleton('customer/session')->getMessages(true)->getItems(),
    );
    expect($errors)->toBe(['Login and password are required.']);
})->with([
    // Before the fix, setUsername(?string) got the array and threw a TypeError: a 500
    'username array' => [['username' => ['address@example.com'], 'password' => 'secret']],
    // Before the fix, the password "Array" was tried and counted as a failed login
    'password array' => [['username' => 'address@example.com', 'password' => ['secret']]],
]);
