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

function bafkCreateCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
        ->setStore(Mage::app()->getStore())
        ->setEmail('agreement-' . uniqid() . '@example.com')
        ->setFirstname('Agreement')
        ->setLastname('Tester')
        ->setForceConfirmed(true)
        ->setPassword('SomePassword123!');
    $customer->save();
    return $customer;
}

function bafkController(array $post): Mage_Sales_Billing_AgreementController
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/sales/billing_agreement/cancel', 'POST', $post),
    );
    $request->setRouteName('sales')
        ->setControllerName('billing_agreement')
        ->setActionName('cancel')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    return new Mage_Sales_Billing_AgreementController($request, new Mage_Core_Controller_Response_Http());
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::getSingleton('customer/session')->logout();
    $this->customer = bafkCreateCustomer();
    Mage::getSingleton('customer/session')->setCustomer($this->customer);
    Mage::getSingleton('customer/session')->getMessages(true);
});

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    Mage::unregister('isSecureArea');
    Mage::getSingleton('customer/session')->logout();
});

it('refuses a cancellation without a form key', function () {
    $controller = bafkController(['agreement' => 999999]);

    $controller->cancelAction();

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect(Mage::getSingleton('customer/session')->getMessages()->count())->toBe(0);
});

it('reports a missing agreement when the form key is valid', function () {
    $controller = bafkController([
        'agreement' => 999999,
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
    ]);

    $controller->cancelAction();

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect(Mage::getSingleton('customer/session')->getMessages()->count())->toBe(1);
});
