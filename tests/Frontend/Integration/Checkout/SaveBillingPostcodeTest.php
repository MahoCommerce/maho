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
