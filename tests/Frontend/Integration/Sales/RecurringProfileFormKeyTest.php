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

function rpfkCreateCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
        ->setStore(Mage::app()->getStore())
        ->setEmail('recurring-' . uniqid() . '@example.com')
        ->setFirstname('Recurring')
        ->setLastname('Tester')
        ->setForceConfirmed(true)
        ->setPassword('SomePassword123!');
    $customer->save();
    return $customer;
}

function rpfkController(string $action, array $post): Mage_Sales_Recurring_ProfileController
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/sales/recurring_profile/' . $action, 'POST', $post),
    );
    $request->setRouteName('sales')
        ->setControllerName('recurring_profile')
        ->setActionName($action)
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    return new class ($request, new Mage_Core_Controller_Response_Http()) extends Mage_Sales_Recurring_ProfileController {
        public function __construct(Mage_Core_Controller_Request_Http $request, Mage_Core_Controller_Response_Http $response)
        {
            parent::__construct($request, $response);
            $this->_session = Mage::getSingleton('customer/session');
        }
    };
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::getSingleton('customer/session')->logout();
    $this->customer = rpfkCreateCustomer();
    Mage::getSingleton('customer/session')->setCustomer($this->customer);
    Mage::getSingleton('customer/session')->getMessages(true);
});

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    Mage::unregister('isSecureArea');
    Mage::getSingleton('customer/session')->logout();
});

it('refuses a profile state change without a form key', function () {
    $controller = rpfkController('updateState', ['profile' => 999999, 'action' => 'cancel']);

    $controller->updateStateAction();

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect(Mage::getSingleton('customer/session')->getMessages()->count())->toBe(0);
});

it('refuses a profile update without a form key', function () {
    $controller = rpfkController('updateProfile', ['profile' => 999999]);

    $controller->updateProfileAction();

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect(Mage::getSingleton('customer/session')->getMessages()->count())->toBe(0);
});

it('reports a missing profile when the form key is valid', function () {
    $controller = rpfkController('updateState', [
        'profile' => 999999,
        'action' => 'cancel',
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
    ]);

    $controller->updateStateAction();

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect(Mage::getSingleton('customer/session')->getMessages()->count())->toBe(1);
});
