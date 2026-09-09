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

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
});

it('refuses to cancel partial authorizations without a form key', function () {
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/paygate/authorizenet_payment/cancel', 'POST', []),
    );
    $request->setRouteName('paygate')
        ->setControllerName('authorizenet_payment')
        ->setActionName('cancel')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    $controller = new Mage_Paygate_Authorizenet_PaymentController($request, new Mage_Core_Controller_Response_Http());
    $controller->cancelAction();

    $result = Mage::helper('core')->jsonDecode($controller->getResponse()->getBody());
    expect($result['success'])->toBeFalse();
    expect($result['error_message'])->toBe('Invalid form key. Please refresh the page.');
});
