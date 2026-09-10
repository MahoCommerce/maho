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

function reorderCreateCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
        ->setStore(Mage::app()->getStore())
        ->setEmail('reorder-' . uniqid() . '@example.com')
        ->setFirstname('Reorder')
        ->setLastname('Tester')
        ->setForceConfirmed(true)
        ->setPassword('SomePassword123!');
    $customer->save();
    return $customer;
}

function reorderCreateOrder(int $customerId): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order')
        ->setIncrementId('REORDER-' . uniqid())
        ->setCustomerId($customerId)
        ->setCustomerIsGuest(0)
        ->setStoreId((int) Mage::app()->getStore()->getId())
        ->setSubtotal(0)
        ->setGrandTotal(0)
        ->setTotalQtyOrdered(0)
        ->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE)
        ->setData('status', Mage_Sales_Model_Order::STATE_COMPLETE);
    $order->save();
    return $order;
}

function reorderController(array $post): Mage_Sales_OrderController
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/sales/order/reorder', 'POST', $post),
    );
    $request->setRouteName('sales')
        ->setControllerName('order')
        ->setActionName('reorder')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    return new Mage_Sales_OrderController($request, new Mage_Core_Controller_Response_Http());
}

function reorderRedirectLocation(Mage_Core_Controller_Response_Http $response): ?string
{
    foreach ($response->getHeaders() as $header) {
        if (strcasecmp($header['name'], 'Location') === 0) {
            return $header['value'];
        }
    }
    return null;
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::getSingleton('customer/session')->logout();
    $this->customer = reorderCreateCustomer();
    $this->order = null;
    Mage::getSingleton('customer/session')->setCustomer($this->customer);
});

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    $this->order?->delete();
    $this->customer->delete();
    Mage::unregister('isSecureArea');
    Mage::getSingleton('customer/session')->logout();
});

it('refuses a reorder without a form key', function () {
    $controller = reorderController([]);

    $controller->reorderAction();

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect($controller->getRequest()->getActionName())->toBe('reorder');
});

it('refuses a reorder when reorders are disabled for the store', function () {
    $this->order = reorderCreateOrder((int) $this->customer->getId());
    Mage::app()->getStore()->setConfig(Mage_Sales_Helper_Reorder::XML_PATH_SALES_REORDER_ALLOW, '0');

    $controller = reorderController([
        'order_id' => $this->order->getId(),
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
    ]);

    $controller->reorderAction();

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect(reorderRedirectLocation($controller->getResponse()))->toContain('sales/order/view');
});
