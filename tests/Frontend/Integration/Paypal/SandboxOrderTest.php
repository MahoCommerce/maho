<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\PaypalSandbox;

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal', 'sandbox');

beforeEach(function () {
    if (!PaypalSandbox::isConfigured()) {
        test()->markTestSkipped('PayPal sandbox credentials not set (PAYPAL_SANDBOX_CLIENT_ID/SECRET)');
    }
});

it('creates a real PayPal sandbox order in the base currency', function () {
    // PayPal transacts in the store base currency (USD). The display currency is
    // presentation only, so even a multi-currency quote builds a base-currency order.
    $quote = Mage::getModel('sales/quote');
    $quote->setStoreId(1);
    $quote->save();

    $quote->setBaseCurrencyCode('USD');
    $quote->setQuoteCurrencyCode('EUR');
    $quote->setBaseGrandTotal(45.00);
    $quote->setGrandTotal(49.99);

    $request = Mage::getModel('paypal/api_orderBuilder')->buildFromQuote($quote);

    /** @var Maho_Paypal_Model_Api_Client $client */
    $client = Mage::getModel('paypal/api_client')
        ->setExplicitCredentials(PaypalSandbox::clientId(), PaypalSandbox::clientSecret(), true);

    $result = $client->createOrder(['body' => $request]);

    expect($result['status'] ?? null)->toBe('CREATED');
    expect($result['purchase_units'][0]['amount']['currency_code'] ?? null)->toBe('USD');
    expect($result['purchase_units'][0]['amount']['value'] ?? null)->toBe('45.00');
});
