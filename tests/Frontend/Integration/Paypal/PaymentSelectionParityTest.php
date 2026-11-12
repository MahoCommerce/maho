<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal');

/**
 * The PayPal checkout selects the payment and the shipping method of the cart through
 * Mage_Checkout_Service_Cart::selectPaymentMethod() and selectShippingMethod(), as the checkout does (#1348).
 */

/**
 * @return array<string, mixed> a PayPal order of a buyer in London, in the status $status
 */
function paypalParityOrder(string $status): array
{
    return [
        'id' => 'PESTPARITY' . strtoupper(uniqid()),
        'status' => $status,
        'payer' => ['email_address' => 'buyer@example.com', 'name' => ['given_name' => 'Jane', 'surname' => 'Doe']],
        'purchase_units' => [[
            'shipping' => [
                'name' => ['full_name' => 'Jane Doe'],
                'address' => ['address_line_1' => '10 Downing St', 'admin_area_2' => 'London', 'postal_code' => 'SW1A 2AA', 'country_code' => 'GB'],
            ],
        ]],
    ];
}

beforeEach(function () {
    Mage::unregister(Mage_Core_Model_Session_Abstract::REGISTRY_KEY);
    $session = new Session(new MockArraySessionStorage());
    $session->start();
    Mage::register(Mage_Core_Model_Session_Abstract::REGISTRY_KEY, $session);
    Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    $store = Mage::app()->getStore();
    $store->setConfig('paypal/credentials/client_id', 'pest-client');
    $store->setConfig('paypal/credentials/client_secret', 'pest-secret');
    $store->setConfig('payment/paypal_standard_checkout/active', '1');
    $store->setConfig('payment/paypal_standard_checkout/allowspecific', '0');

    $this->product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku('pest-paypal-parity-' . uniqid())->setName('Pest PayPal parity product')->setPrice(10.0)->setWeight(1)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100]);
    $this->product->save();

    $cart = Mage::getService('checkout/cart');
    $this->quote = $cart->create(null, 1);
    $cart->addProduct($this->quote, $cart->getProductById((int) $this->product->getId(), 1), new Maho\DataObject(['qty' => 1]));
    $this->helper = Mage::helper('paypal');
});

afterEach(function () {
    $this->quote->delete();
    Mage::register('isSecureArea', true, true);
    Mage::getModel('catalog/product')->load($this->product->getId())->delete();
    Mage::unregister('isSecureArea');
});

it('selects the method and the first shipping rate of a cart that can use PayPal', function () {
    $this->helper->prepareQuoteForPaypalOrder($this->quote, paypalParityOrder('APPROVED'), 'paypal_standard_checkout');

    $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($this->quote->getId());
    expect($quote->getPayment()->getMethod())->toBe('paypal_standard_checkout')
        ->and($quote->getShippingAddress()->getShippingMethod())->toBe('flatrate_flatrate');
});

it('refuses a PayPal method that the cart cannot use before the capture, as the payment step of the checkout does', function () {
    Mage::app()->getStore()->setConfig('payment/paypal_standard_checkout/allowspecific', '1');
    Mage::app()->getStore()->setConfig('payment/paypal_standard_checkout/specificcountry', 'FR');
    $message = 'The requested Payment Method is not available.';

    expect(fn() => $this->helper->prepareQuoteForPaypalOrder($this->quote, paypalParityOrder('APPROVED'), 'paypal_standard_checkout'))
        ->toThrow(Mage_Core_Exception::class, $message);
    $onepage = Mage::getModel('checkout/type_onepage')->setQuote($this->quote);
    expect(fn() => $onepage->savePayment(['method' => 'paypal_standard_checkout']))->toThrow(Mage_Core_Exception::class, $message)
        ->and($this->quote->getBillingAddress()->getCountryId())->toBe('GB');
});

describe('kept difference: the buyer already paid the PayPal order', function () {
    it('gives the cart the method without the check, so that the paid order is placed', function () {
        Mage::app()->getStore()->setConfig('payment/paypal_standard_checkout/allowspecific', '1');
        Mage::app()->getStore()->setConfig('payment/paypal_standard_checkout/specificcountry', 'FR');

        $this->helper->prepareQuoteForPaypalOrder($this->quote, paypalParityOrder('COMPLETED'), 'paypal_standard_checkout');

        expect($this->quote->getPayment()->getMethod())->toBe('paypal_standard_checkout')
            ->and($this->quote->getShippingAddress()->getShippingMethod())->toBe('flatrate_flatrate');
    });
});
