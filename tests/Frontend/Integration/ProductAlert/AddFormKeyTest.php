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

function pafkCreateCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
        ->setStore(Mage::app()->getStore())
        ->setEmail('alert-' . uniqid() . '@example.com')
        ->setFirstname('Alert')
        ->setLastname('Tester')
        ->setForceConfirmed(true)
        ->setPassword('SomePassword123!');
    $customer->save();
    return $customer;
}

function pafkController(string $action, array $post): Mage_ProductAlert_AddController
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/productalert/add/' . $action, 'POST', $post),
    );
    $request->setRouteName('productalert')
        ->setControllerName('add')
        ->setActionName($action)
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    return new Mage_ProductAlert_AddController($request, new Mage_Core_Controller_Response_Http());
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::getSingleton('customer/session')->logout();
    $this->customer = pafkCreateCustomer();
    Mage::getSingleton('customer/session')->setCustomer($this->customer);
    Mage::getSingleton('catalog/session')->getMessages(true);
});

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    foreach (['productalert/stock', 'productalert/price'] as $type) {
        $alerts = Mage::getModel($type)->getCollection()->addFieldToFilter('customer_id', $this->customer->getId());
        foreach ($alerts as $alert) {
            $alert->delete();
        }
    }
    $this->customer->delete();
    Mage::unregister('isSecureArea');
    Mage::getSingleton('customer/session')->logout();
});

it('refuses a stock alert signup without a form key', function () {
    $controller = pafkController('stock', [
        'product_id' => 1,
        Mage_Core_Controller_Front_Action::PARAM_NAME_URL_ENCODED => Mage::helper('core')->urlEncode('http://localhost/'),
    ]);

    $controller->stockAction();

    $alerts = Mage::getModel('productalert/stock')->getCollection()
        ->addFieldToFilter('customer_id', $this->customer->getId());

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect(Mage::getSingleton('catalog/session')->getMessages()->count())->toBe(0);
    expect($alerts->getSize())->toBe(0);
});

it('refuses a price alert signup without a form key', function () {
    $controller = pafkController('price', [
        'product_id' => 1,
        Mage_Core_Controller_Front_Action::PARAM_NAME_URL_ENCODED => Mage::helper('core')->urlEncode('http://localhost/'),
    ]);

    $controller->priceAction();

    $alerts = Mage::getModel('productalert/price')->getCollection()
        ->addFieldToFilter('customer_id', $this->customer->getId());

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect(Mage::getSingleton('catalog/session')->getMessages()->count())->toBe(0);
    expect($alerts->getSize())->toBe(0);
});
