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
    $quote->getShippingAddress()->addData($address)->setSameAsBilling(1);
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
