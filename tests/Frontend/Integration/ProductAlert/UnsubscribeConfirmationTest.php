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

function paCreateCustomer(): Mage_Customer_Model_Customer
{
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
        ->setStore(Mage::app()->getStore())
        ->setEmail('productalert-' . uniqid() . '@example.com')
        ->setFirstname('Product')
        ->setLastname('Alert')
        ->setForceConfirmed()
        ->setPassword('SomePassword123!');
    $customer->save();
    return $customer;
}

function paRequest(string $action, string $method, array $params): Mage_Core_Controller_Request_Http
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/productalert/unsubscribe/' . $action, $method, $params),
    );
    $request->setPathInfo('/productalert/unsubscribe/' . $action)
        ->setRouteName('productalert')
        ->setControllerName('unsubscribe')
        ->setActionName($action)
        ->setControllerModule('Mage_ProductAlert')
        ->setDispatched(true);
    Mage::app()->setRequest($request);
    return $request;
}

function paDispatch(string $action, string $method, array $params): Mage_Core_Controller_Response_Http
{
    $request = paRequest($action, $method, $params);
    $controller = new Mage_ProductAlert_UnsubscribeController($request, new Mage_Core_Controller_Response_Http());
    $controller->dispatch($action);
    return $controller->getResponse();
}

function paAlertCount(Mage_ProductAlert_Model_Type $type, int $customerId): int
{
    $model = match ($type) {
        Mage_ProductAlert_Model_Type::Price => 'productalert/price',
        Mage_ProductAlert_Model_Type::Stock => 'productalert/stock',
    };
    return (int) Mage::getModel($model)->getCollection()
        ->addFieldToFilter('customer_id', $customerId)
        ->addFieldToFilter('website_id', (int) Mage::app()->getStore()->getWebsiteId())
        ->getSize();
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::getSingleton('customer/session')->logout();
    Mage::getSingleton('core/session')->getMessages(true);
    Mage::getSingleton('customer/session')->getMessages(true);
    Mage::getSingleton('catalog/session')->getMessages(true);

    $this->customer = paCreateCustomer();
    Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($this->customer);
    $this->product = loadSimplePricedProduct();
    Mage::getModel('productalert/price')
        ->setCustomerId((int) $this->customer->getId())
        ->setProductId((int) $this->product->getId())
        ->setWebsiteId((int) Mage::app()->getStore()->getWebsiteId())
        ->setPrice((float) $this->product->getPrice())
        ->save();
    Mage::getModel('productalert/stock')
        ->setCustomerId((int) $this->customer->getId())
        ->setProductId((int) $this->product->getId())
        ->setWebsiteId((int) Mage::app()->getStore()->getWebsiteId())
        ->save();
});

afterEach(function () {
    Mage::register('isSecureArea', true, true);
    $this->customer->delete();
    Mage::unregister('isSecureArea');
    Mage::getSingleton('customer/session')->logout();
});

it('leaves every alert intact when an email unsubscribe link is opened with GET', function () {
    $customerId = (int) $this->customer->getId();

    $priceResponse = paDispatch('priceAll', 'GET', []);
    $stockResponse = paDispatch('stockAll', 'GET', []);

    expect($priceResponse->isRedirect())->toBeFalse()
        ->and($priceResponse->getBody())->toContain('Unsubscribe from all price alerts?')
        ->and($stockResponse->isRedirect())->toBeFalse()
        ->and($stockResponse->getBody())->toContain('Unsubscribe from all stock alerts?')
        ->and(paAlertCount(Mage_ProductAlert_Model_Type::Price, $customerId))->toBe(1)
        ->and(paAlertCount(Mage_ProductAlert_Model_Type::Stock, $customerId))->toBe(1);
});

it('leaves every alert intact when POST has no valid form key', function () {
    $customerId = (int) $this->customer->getId();

    $priceResponse = paDispatch('priceAll', 'POST', []);
    $stockResponse = paDispatch('stockAll', 'POST', []);

    expect($priceResponse->isRedirect())->toBeTrue()
        ->and($stockResponse->isRedirect())->toBeTrue()
        ->and(paAlertCount(Mage_ProductAlert_Model_Type::Price, $customerId))->toBe(1)
        ->and(paAlertCount(Mage_ProductAlert_Model_Type::Stock, $customerId))->toBe(1);
});

it('removes the confirmed alert only after a form-key-protected POST', function () {
    $customerId = (int) $this->customer->getId();
    $formKey = Mage::getSingleton('core/session')->getFormKey();

    $response = paDispatch('priceAll', 'POST', ['form_key' => $formKey]);

    expect($response->isRedirect())->toBeTrue()
        ->and(paAlertCount(Mage_ProductAlert_Model_Type::Price, $customerId))->toBe(0)
        ->and(paAlertCount(Mage_ProductAlert_Model_Type::Stock, $customerId))->toBe(1);
});

it('confirms a product alert before removing it', function () {
    $customerId = (int) $this->customer->getId();
    $productId = (int) $this->product->getId();
    $formKey = Mage::getSingleton('core/session')->getFormKey();

    $getResponse = paDispatch('price', 'GET', ['product' => $productId]);
    $invalidPostResponse = paDispatch('price', 'POST', ['product' => $productId]);

    expect($getResponse->isRedirect())->toBeFalse()
        ->and($invalidPostResponse->isRedirect())->toBeTrue()
        ->and(paAlertCount(Mage_ProductAlert_Model_Type::Price, $customerId))->toBe(1);

    $validPostResponse = paDispatch('price', 'POST', [
        'product' => $productId,
        'form_key' => $formKey,
    ]);

    expect($validPostResponse->isRedirect())->toBeTrue()
        ->and(paAlertCount(Mage_ProductAlert_Model_Type::Price, $customerId))->toBe(0);
});
