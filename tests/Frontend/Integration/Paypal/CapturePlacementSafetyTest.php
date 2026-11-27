<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal');

function paypalNonVirtualQuote(): ?Mage_Sales_Model_Quote
{
    $product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', 1)
        ->addAttributeToSelect(['price', 'name'])
        ->setPageSize(1)
        ->getFirstItem();
    if (!$product->getId()) {
        return null;
    }

    $quote = Mage::getModel('sales/quote');
    $quote->setStoreId(1);
    $quote->addProduct($product, 1);
    return $quote;
}

it('validate() rejects a non-virtual quote with no shipping method', function () {
    $quote = paypalNonVirtualQuote();
    if (!$quote) {
        $this->markTestSkipped('No simple product available for testing');
    }

    $address = ['firstname' => 'Jane', 'lastname' => 'Doe', 'street' => '1 St', 'city' => 'LA', 'region_id' => 12, 'postcode' => '90210', 'country_id' => 'US', 'telephone' => '0000000000'];
    $quote->getBillingAddress()->addData($address);
    $quote->getShippingAddress()->addData($address)->setSameAsBilling();
    $quote->getPayment()->setMethod('paypal_standard_checkout');
    $quote->collectTotals();

    // No shipping method assigned -> validation must throw before any capture
    $service = Mage::getModel('sales/service_quote', $quote);
    expect(fn() => $service->validate())
        ->toThrow(Mage_Core_Exception::class, 'Please specify a shipping method.');
});

it('prepareQuoteForPaypalOrder blocks an unplaceable express quote before capture', function () {
    $quote = paypalNonVirtualQuote();
    if (!$quote) {
        $this->markTestSkipped('No simple product available for testing');
    }
    $quote->save();

    // Express-style PayPal result: payer email but no usable shipping address,
    // so no valid shipping address/method can be resolved. prepare must throw
    // here (before capture) rather than let the flow reach captureOrder.
    $paypalResult = [
        'id' => 'TESTORDER1',
        'payer' => ['email_address' => 'buyer@example.com', 'name' => ['given_name' => 'Jane', 'surname' => 'Doe']],
        'purchase_units' => [[]],
    ];

    /** @var Maho_Paypal_Helper_Data $helper */
    $helper = Mage::helper('paypal');
    expect(fn() => $helper->prepareQuoteForPaypalOrder($quote, $paypalResult, 'paypal_standard_checkout'))
        ->toThrow(Mage_Core_Exception::class);
});

it('prepareQuoteForPaypalOrder accepts a placeable express quote and assigns a shipping method', function () {
    $quote = paypalNonVirtualQuote();
    if (!$quote) {
        $this->markTestSkipped('No simple product available for testing');
    }
    $quote->save();
    // prepare checks that the cart can use the method, as the payment step of the checkout does
    Mage::app()->getStore()->setConfig('paypal/credentials/client_id', 'pest-client');
    Mage::app()->getStore()->setConfig('paypal/credentials/client_secret', 'pest-secret');
    Mage::app()->getStore()->setConfig('payment/paypal_standard_checkout/active', '1');

    // Express-style PayPal result carrying a full shipping address. prepare must
    // import it, collect rates and assign the (active, default) flatrate method,
    // then pass validation without throwing — money is allowed to move.
    // GB has no required region, keeping this focused on the shipping-method gate.
    $paypalResult = [
        'id' => 'TESTORDER2',
        'payer' => ['email_address' => 'buyer@example.com', 'name' => ['given_name' => 'Jane', 'surname' => 'Doe']],
        'purchase_units' => [[
            'shipping' => [
                'name' => ['full_name' => 'Jane Doe'],
                'address' => [
                    'address_line_1' => '10 Downing St',
                    'admin_area_2' => 'London',
                    'postal_code' => 'SW1A 2AA',
                    'country_code' => 'GB',
                ],
            ],
        ]],
    ];

    /** @var Maho_Paypal_Helper_Data $helper */
    $helper = Mage::helper('paypal');
    $helper->prepareQuoteForPaypalOrder($quote, $paypalResult, 'paypal_standard_checkout');

    // Reaching here means validation passed before any capture; the shipping
    // method resolved from the imported address proves the gate is not over-strict.
    expect($quote->getShippingAddress()->getShippingMethod())->not->toBeEmpty();
});

it('prepareQuoteForPaypalOrder refuses a quote when the buyer did not accept the terms and conditions', function () {
    $quote = paypalNonVirtualQuote();
    if (!$quote) {
        $this->markTestSkipped('No simple product available for testing');
    }
    $quote->save();
    $store = Mage::app()->getStore();
    $store->setConfig('paypal/credentials/client_id', 'pest-client');
    $store->setConfig('paypal/credentials/client_secret', 'pest-secret');
    $store->setConfig('payment/paypal_standard_checkout/active', '1');
    $store->setConfig('checkout/options/enable_agreements', '1');
    $agreement = Mage::getModel('checkout/agreement')->setData([
        'name' => 'Pest terms', 'content' => 'Terms', 'checkbox_text' => 'I agree', 'is_active' => 1, 'stores' => [0],
    ])->save();
    // The checkout helper keeps the list of required agreements for the request
    Mage::unregister('_helper/checkout');

    $paypalResult = [
        'id' => 'TESTORDER3',
        'payer' => ['email_address' => 'buyer@example.com', 'name' => ['given_name' => 'Jane', 'surname' => 'Doe']],
        'purchase_units' => [[
            'shipping' => [
                'name' => ['full_name' => 'Jane Doe'],
                'address' => ['address_line_1' => '10 Downing St', 'admin_area_2' => 'London', 'postal_code' => 'SW1A 2AA', 'country_code' => 'GB'],
            ],
        ]],
    ];

    try {
        /** @var Maho_Paypal_Helper_Data $helper */
        $helper = Mage::helper('paypal');
        expect(fn() => $helper->prepareQuoteForPaypalOrder($quote, $paypalResult, 'paypal_standard_checkout', []))
            ->toThrow(Mage_Core_Exception_Input::class);

        $helper->prepareQuoteForPaypalOrder($quote, $paypalResult, 'paypal_standard_checkout', [(int) $agreement->getId()]);
        expect($quote->getShippingAddress()->getShippingMethod())->not->toBeEmpty();
    } finally {
        $store->setConfig('checkout/options/enable_agreements', '0');
        $agreement->delete();
        Mage::unregister('_helper/checkout');
    }
});
