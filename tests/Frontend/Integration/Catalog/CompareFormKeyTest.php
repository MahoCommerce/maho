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

function cmpfkController(string $action, array $post): Mage_Catalog_Product_CompareController
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/catalog/product_compare/' . $action, 'POST', $post),
    );
    $request->setPathInfo('/catalog/product_compare/' . $action);
    $request->setRouteName('catalog')
        ->setControllerName('product_compare')
        ->setActionName($action)
        ->setControllerModule('Mage_Catalog')
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    $controller = new Mage_Catalog_Product_CompareController($request, new Mage_Core_Controller_Response_Http());
    Mage::dispatchEvent('controller_action_predispatch', ['controller_action' => $controller]);

    return $controller;
}

function cmpfkCompareCount(): int
{
    return (int) Mage::getResourceModel('catalog/product_compare_item_collection')
        ->setVisitorId(Mage::getSingleton('log/visitor')->getId())
        ->getSize();
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);

    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::app()->getStore()->setConfig('catalog/recently_products/enabled_product_compare', '1');
    Mage::app()->getStore()->setConfig('system/log/enable_log', '2');
    Mage::app()->addEventArea('frontend');
    Mage::getSingleton('customer/session')->logout();
    Mage::getSingleton('catalog/session')->getMessages(true);
    Mage::getSingleton('core/session')->getMessages(true);
});

it('refuses to clear the comparison list without a form key', function () {
    $controller = cmpfkController('clear', []);

    $controller->dispatch('clear');

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect(Mage::getSingleton('catalog/session')->getMessages()->count())->toBe(0);
});

it('refuses to remove a compared product without a form key', function () {
    $controller = cmpfkController('remove', ['product' => 1]);

    $controller->dispatch('remove');

    expect($controller->getResponse()->isRedirect())->toBeTrue();
    expect(Mage::getSingleton('catalog/session')->getMessages()->count())->toBe(0);
    expect(Mage::getSingleton('core/session')->getMessages()->getLastAddedMessage()?->getText())
        ->toBe('Invalid form key. Please refresh the page.');
});

it('clears the comparison list when the form key is valid', function () {
    $product = Mage::getModel('catalog/product')->getCollection()
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setPageSize(1)
        ->getFirstItem();

    $addController = cmpfkController('add', [
        'product' => $product->getId(),
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
    ]);
    $addController->dispatch('add');
    expect(cmpfkCompareCount())->toBeGreaterThan(0);

    $controller = cmpfkController('clear', ['form_key' => Mage::getSingleton('core/session')->getFormKey()]);
    $controller->dispatch('clear');

    expect(cmpfkCompareCount())->toBe(0);
});
