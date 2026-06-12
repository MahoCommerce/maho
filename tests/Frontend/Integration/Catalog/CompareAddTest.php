<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoFrontendTestCase::class);

/**
 * Regression coverage for issue #1022 ("Add to Compare does not work").
 *
 * The frontend visitor is initialised by Mage_Log_Model_Visitor::initByRequest on
 * controller_action_predispatch. The compare controller then reads the *same*
 * Mage::getSingleton('log/visitor') to decide whether a guest may own a compare item.
 *
 * The #[Observer] attribute defaults to type 'model' (a fresh instance per dispatch),
 * whereas the original M1 XML observer had no <type> and therefore defaulted to
 * 'singleton'. Under 'model' the observer initialises a throwaway visitor, leaving the
 * shared singleton empty, so a guest's getId() is null and the add is silently dropped.
 */
function startGuestSession(): void
{
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register('symfony_session', $session);

    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    Mage::app()->getStore()->setConfig('catalog/recently_products/enabled_product_compare', '1');
    Mage::app()->getStore()->setConfig('system/log/enable_log', '2'); // log visitors
    Mage::app()->addEventArea('frontend');
}

function firstEnabledProduct(): Mage_Catalog_Model_Product
{
    return Mage::getModel('catalog/product')->getCollection()
        ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setPageSize(1)
        ->getFirstItem();
}

it('populates the shared log/visitor singleton on controller_action_predispatch', function () {
    startGuestSession();

    $request = Mage::app()->getRequest();
    $request->setRouteName('catalog');
    $controllerAction = new class ($request, new Mage_Core_Controller_Response_Http()) extends Mage_Core_Controller_Front_Action {};

    // Same event the front controller fires before dispatching any frontend action.
    Mage::dispatchEvent('controller_action_predispatch', ['controller_action' => $controllerAction]);

    // The visitor the rest of the request reads is the singleton. It must carry an id.
    expect(Mage::getSingleton('log/visitor')->getId())->not->toBeNull();
});

it('adds a product to a guest comparison list end to end', function () {
    startGuestSession();

    $product = firstEnabledProduct();
    $productId = (int) $product->getId();
    expect($productId)->toBeGreaterThan(0);

    $formKey = Mage::getSingleton('core/session')->getFormKey();

    $symfonyRequest = SymfonyRequest::create(
        '/catalog/product_compare/add/?product=' . $productId . '&uenc=' . Mage::helper('core')->urlEncode('http://localhost/'),
        'POST',
        ['form_key' => $formKey],
    );
    $request = new Mage_Core_Controller_Request_Http($symfonyRequest);
    $request->setPathInfo('/catalog/product_compare/add');
    $request->setRouteName('catalog');
    $request->setControllerName('product_compare');
    $request->setActionName('add');
    $request->setControllerModule('Mage_Catalog');
    $request->setDispatched(true);

    $controller = new Mage_Catalog_Product_CompareController($request, new Mage_Core_Controller_Response_Http());

    // Visitor is initialised by the predispatch observer, exactly as in a real request.
    Mage::dispatchEvent('controller_action_predispatch', ['controller_action' => $controller]);

    $visitorId = Mage::getSingleton('log/visitor')->getId();
    expect($visitorId)->not->toBeNull();

    $before = (int) Mage::getResourceModel('catalog/product_compare_item_collection')
        ->setVisitorId($visitorId)->getSize();

    $controller->addAction();

    $after = (int) Mage::getResourceModel('catalog/product_compare_item_collection')
        ->setVisitorId($visitorId)->getSize();

    expect($after)->toBe($before + 1);
});
