<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Tests\PaypalSandbox;

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal', 'sandbox');

beforeEach(function () {
    if (!PaypalSandbox::isConfigured()) {
        test()->markTestSkipped('PayPal sandbox credentials not set (PAYPAL_SANDBOX_CLIENT_ID/SECRET)');
    }
});

it('creates a real PayPal sandbox order in the quote (EUR) currency', function () {
    // Base USD, store view displays EUR. Set the display values after save (the quote
    // resets currency from the store on save); buildFromQuote reads them in-memory.
    $quote = Mage::getModel('sales/quote');
    $quote->setStoreId(1);
    $quote->save();

    $quote->setBaseCurrencyCode('USD');
    $quote->setQuoteCurrencyCode('EUR');
    $quote->setBaseGrandTotal(45.00);
    $quote->setGrandTotal(49.99);

    // Exercises the #985 fix: buildFromQuote uses getQuoteCurrencyCode()/getGrandTotal().
    $request = Mage::getModel('paypal/api_orderBuilder')->buildFromQuote($quote);

    /** @var Maho_Paypal_Model_Api_Client $client */
    $client = Mage::getModel('paypal/api_client')
        ->setExplicitCredentials(PaypalSandbox::clientId(), PaypalSandbox::clientSecret(), true);

    $result = $client->createOrder(['body' => $request]);

    expect($result['status'] ?? null)->toBe('CREATED');
    expect($result['purchase_units'][0]['amount']['currency_code'] ?? null)->toBe('EUR');
    expect($result['purchase_units'][0]['amount']['value'] ?? null)->toBe('49.99');
});
