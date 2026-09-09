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

function wlfkCreateCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
        ->setStore(Mage::app()->getStore())
        ->setEmail('wishlist-' . uniqid() . '@example.com')
        ->setFirstname('Wishlist')
        ->setLastname('Tester')
        ->setForceConfirmed(true)
        ->setPassword('SomePassword123!');
    $customer->save();
    return $customer;
}

function wlfkRequest(string $controller, string $action, array $post): Mage_Core_Controller_Request_Http
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/wishlist/' . $controller . '/' . $action, 'POST', $post),
    );
    $request->setRouteName('wishlist')
        ->setControllerName($controller)
        ->setActionName($action)
        ->setDispatched(true);
    Mage::app()->setRequest($request);
    return $request;
}

function wlfkMessageCount(): int
{
    return Mage::getSingleton('checkout/session')->getMessages()->count()
        + Mage::getSingleton('customer/session')->getMessages()->count()
        + Mage::getSingleton('wishlist/session')->getMessages()->count()
        + Mage::getSingleton('catalog/session')->getMessages()->count();
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::getSingleton('customer/session')->logout();
    $this->customer = wlfkCreateCustomer();
    Mage::getSingleton('customer/session')->setCustomer($this->customer);
    foreach (['checkout/session', 'customer/session', 'wishlist/session', 'catalog/session'] as $type) {
        Mage::getSingleton($type)->getMessages(true);
    }
});

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    Mage::unregister('isSecureArea');
    Mage::getSingleton('customer/session')->logout();
});

it('refuses to move a cart item to the wishlist without a form key', function () {
    $request = wlfkRequest('index', 'fromcart', ['item' => 999999]);
    $controller = new Mage_Wishlist_IndexController($request, new Mage_Core_Controller_Response_Http());

    $controller->fromcartAction();

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect(wlfkMessageCount())->toBe(0);
});

it('refuses to update wishlist item options without a form key', function () {
    $request = wlfkRequest('index', 'updateItemOptions', ['product' => 999999, 'id' => 999999]);
    $controller = new Mage_Wishlist_IndexController($request, new Mage_Core_Controller_Response_Http());

    $controller->updateItemOptionsAction();

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect(wlfkMessageCount())->toBe(0);
});

it('refuses to add a shared wishlist item to the cart without a form key', function () {
    $request = wlfkRequest('shared', 'cart', ['item' => 999999, 'code' => 'nope']);
    $controller = new Mage_Wishlist_SharedController($request, new Mage_Core_Controller_Response_Http());

    $controller->cartAction();

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect(wlfkMessageCount())->toBe(0);
});
