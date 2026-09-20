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

function tagfkCreateCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
        ->setStore(Mage::app()->getStore())
        ->setEmail('tag-' . uniqid() . '@example.com')
        ->setFirstname('Tag')
        ->setLastname('Tester')
        ->setForceConfirmed(true)
        ->setPassword('SomePassword123!');
    $customer->save();
    return $customer;
}

function tagfkController(array $post): Mage_Tag_CustomerController
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/tag/customer/remove', 'POST', $post),
    );
    $request->setRouteName('tag')
        ->setControllerName('customer')
        ->setActionName('remove')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    return new Mage_Tag_CustomerController($request, new Mage_Core_Controller_Response_Http());
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::getSingleton('customer/session')->logout();
    $this->customer = tagfkCreateCustomer();
    Mage::getSingleton('customer/session')->setCustomer($this->customer);
});

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    Mage::unregister('isSecureArea');
    Mage::getSingleton('customer/session')->logout();
});

it('refuses a tag removal without a form key', function () {
    $controller = tagfkController(['tagId' => 999999]);

    $controller->removeAction();

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect($controller->getRequest()->getActionName())->toBe('remove');
});
