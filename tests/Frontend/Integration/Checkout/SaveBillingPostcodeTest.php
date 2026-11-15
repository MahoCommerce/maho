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

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    // An earlier test can leave a quote or the cart_was_updated flag in $_SESSION, which the session singletons share
    $_SESSION = [];
    Mage::unregister('_singleton/checkout/session');
    Mage::unregister('_singleton/checkout/type_onepage');
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
});

it('rejects a billing postcode that does not match the format of the country', function () {
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
    Mage::getSingleton('checkout/session')->setQuoteId($quote->getId());

    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/checkout/onepage/saveBilling', 'POST', [
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
        'billing' => [
            'firstname' => 'Mario',
            'lastname' => 'Rossi',
            'email' => 'mario.rossi@example.com',
            'street' => ['Via Roma 1'],
            'city' => 'Roma',
            'country_id' => 'IT',
            'postcode' => '0014',
            'telephone' => '0000000000',
            'use_for_shipping' => '1',
        ],
    ]));
    $request->setRouteName('checkout')
        ->setControllerName('onepage')
        ->setActionName('saveBilling')
        ->setDispatched(true);
    Mage::app()->setRequest($request);
    $controller = new Mage_Checkout_OnepageController($request, new Mage_Core_Controller_Response_Http());

    $controller->dispatch('saveBilling');

    $result = Mage::helper('core')->jsonDecode($controller->getResponse()->getBody());
    expect($result['error'] ?? null)->toBe(1)
        ->and(implode(' ', (array) $result['message']))->toContain('(example: 00144)');

    $quote->delete();
});

it('keeps the email of a guest in the billing estimate, so that an order with no billing step has it', function () {
    $quote = Mage::getModel('sales/quote')->setStoreId((int) Mage::app()->getStore()->getId());
    $quote->addProduct(loadSimplePricedProduct(), 1);
    $quote->collectTotals()->save();
    Mage::getSingleton('checkout/session')->setQuoteId($quote->getId());

    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/checkout/onepage/estimateBilling', 'POST', [
        'form_key' => Mage::getSingleton('core/session')->getFormKey(),
        'billing' => [
            'firstname' => 'Test',
            'lastname' => 'Buyer',
            'email' => ' buyer-test@example.com ',
            'street' => ['1 Infinite Loop'],
            'city' => 'Cupertino',
            'country_id' => 'US',
            'region_id' => '12',
            'postcode' => '95014',
            'telephone' => '5551234567',
            'use_for_shipping' => '1',
            'customer_id' => '1',
        ],
    ]));
    $request->setRouteName('checkout')
        ->setControllerName('onepage')
        ->setActionName('estimateBilling')
        ->setDispatched(true);
    Mage::app()->setRequest($request);
    new Mage_Checkout_OnepageController($request, new Mage_Core_Controller_Response_Http())->dispatch('estimateBilling');

    $saved = Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId());
    try {
        Mage::getService('sales/order')->validateQuote($saved, Mage_Checkout_Model_Actor::Customer);
        $fields = [];
    } catch (Mage_Core_Exception_Input $e) {
        $fields = array_column($e->getErrors(), 'field');
    }

    expect($saved->getBillingAddress()->getEmail())->toBe('buyer-test@example.com')
        // A key of the POST that is not a field of the address form does not reach the address
        ->and($saved->getBillingAddress()->getCustomerId())->toBeNull()
        ->and($fields)->not->toContain('customer_email')
        ->and($fields)->not->toContain('customer_firstname');

    $quote->delete();
});
