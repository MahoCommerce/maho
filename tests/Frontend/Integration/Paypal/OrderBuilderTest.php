<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal');

it('can instantiate order builder', function () {
    $builder = Mage::getModel('paypal/api_orderBuilder');
    expect($builder)->toBeInstanceOf(Maho_Paypal_Model_Api_OrderBuilder::class);
});

it('can instantiate api client', function () {
    $client = Mage::getModel('paypal/api_client');
    expect($client)->toBeInstanceOf(Maho_Paypal_Model_Api_Client::class);
});

it('can instantiate helper', function () {
    $helper = Mage::helper('paypal');
    expect($helper)->toBeInstanceOf(Maho_Paypal_Helper_Data::class);
});

it('returns correct payment action source options', function () {
    $source = Mage::getModel('paypal/system_config_source_paymentAction');
    $options = $source->toOptionArray();

    expect($options)->toBeArray();
    expect($options)->toHaveCount(2);

    $values = array_column($options, 'value');
    expect($values)->toContain('authorize');
    expect($values)->toContain('capture');
});

it('builds the PayPal order in the quote (display) currency, not the base currency', function () {
    // Base USD, store view displays EUR with quote amounts distinct from base.
    // _beforeSave() resets the currency from the store, so set the display values
    // after saving (buildFromQuote reads them in-memory without re-saving).
    $quote = Mage::getModel('sales/quote');
    $quote->setStoreId(1);
    $quote->save();

    $quote->setBaseCurrencyCode('USD');
    $quote->setQuoteCurrencyCode('EUR');
    $quote->setBaseGrandTotal(100.00);
    $quote->setGrandTotal(200.00);

    $builder = Mage::getModel('paypal/api_orderBuilder');
    $order = $builder->buildFromQuote($quote);

    $amount = $order['purchase_units'][0]['amount'];
    expect($amount['currency_code'])->toBe('EUR');
    expect($amount['value'])->toBe('200.00');
});

it('emits line items and breakdown in the quote currency', function () {
    $product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', 1)
        ->addAttributeToSelect(['price', 'name'])
        ->setPageSize(1)
        ->getFirstItem();

    if (!$product->getId()) {
        test()->markTestSkipped('No simple product available for testing');
    }

    $quote = Mage::getModel('sales/quote');
    $quote->setStoreId(1);
    $quote->addProduct($product, 1);
    $quote->collectTotals();
    $quote->save();

    // Display in EUR (set after save, which would otherwise reset it from the
    // store). Amounts stay at the 1:1 base values so the breakdown reconciles and
    // the line items are included, and must carry the quote currency code.
    $quote->setQuoteCurrencyCode('EUR');

    $builder = Mage::getModel('paypal/api_orderBuilder');
    $order = $builder->buildFromQuote($quote);

    $purchaseUnit = $order['purchase_units'][0];
    expect($purchaseUnit['amount']['currency_code'])->toBe('EUR');

    if (isset($purchaseUnit['amount']['breakdown'])) {
        expect($purchaseUnit['amount']['breakdown']['item_total']['currency_code'])->toBe('EUR');
    }
    if (isset($purchaseUnit['items'])) {
        expect($purchaseUnit['items'][0]['unit_amount']['currency_code'])->toBe('EUR');
    }
});
