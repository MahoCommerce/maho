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

function cerController(string $action, string $method, array $params = []): Mage_Checkout_CartController
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('/checkout/cart/' . $action, $method, $params),
    );
    $request->setRouteName('checkout')
        ->setControllerName('cart')
        ->setActionName($action)
        ->setDispatched(true);
    Mage::app()->setRequest($request);

    return new Mage_Checkout_CartController($request, new Mage_Core_Controller_Response_Http());
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
});

it('estimates shipping for a country without regions when the region is empty', function () {
    $controller = cerController('estimatePost', 'POST', [
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
        'country_id' => 'GB',
        'estimate_postcode' => 'LN11 0FU',
        'region_id' => '',
        'region' => '',
    ]);

    $controller->dispatch('estimatePost');

    $checkoutSession = Mage::getSingleton('checkout/session');
    expect($controller->getResponse()->isRedirect())->toBeTrue()
        ->and($checkoutSession->getQuote()->getShippingAddress()->getData('region_id'))->toBeNull()
        ->and($checkoutSession->getEstimatedShippingAddressData()['region_id'])->toBeNull();

    $checkoutSession->getQuote()->delete();
});

it('opens the cart when the session holds an estimate with an empty region and a coupon', function () {
    $product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', 1)
        ->addAttributeToSelect(['price', 'name'])
        ->setPageSize(1)
        ->getFirstItem();
    if (!$product->getId()) {
        $this->markTestSkipped('No simple product available for testing');
    }

    $quote = Mage::getModel('sales/quote')->setStoreId((int) Mage::app()->getStore()->getId());
    $quote->addProduct($product, 1);
    $quote->collectTotals()->save();
    $checkoutSession = Mage::getSingleton('checkout/session');
    $checkoutSession->setQuoteId($quote->getId());
    $checkoutSession->setCartCouponCode('NOPE');
    // Earlier releases stored the region of the request as a string
    $checkoutSession->setEstimatedShippingAddressData([
        'country_id' => 'GB',
        'postcode' => 'LN11 0FU',
        'city' => '',
        'region_id' => '',
        'region' => '',
    ]);

    cerController('index', 'GET')->dispatch('index');

    expect($checkoutSession->getQuote()->getShippingAddress()->getData('region_id'))->toBeNull();

    $quote->delete();
});
