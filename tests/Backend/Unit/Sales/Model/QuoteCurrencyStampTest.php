<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * A missing rate is stamped as null, never as zero, and a forced currency without one is refused.
 * XTN is an ISO 4217 "X" code no real currency uses, so no install has a rate for it.
 */
const QUOTE_STAMP_CURRENCY = 'XTN';

function quoteStampCurrency(): Mage_Directory_Model_Currency
{
    /** @var Mage_Directory_Model_Currency $currency */
    $currency = Mage::getModel('directory/currency');

    return $currency->setData('currency_code', QUOTE_STAMP_CURRENCY);
}

function quoteToStamp(): Mage_Sales_Model_Quote
{
    /** @var Mage_Sales_Model_Quote $quote */
    $quote = Mage::getModel('sales/quote');

    return $quote->setStoreId(1);
}

it('stamps no rate rather than a rate of zero on a quote whose currency has none', function () {
    // Through the data key: the setter refuses this currency, the stamp still has to cope
    $quote = quoteToStamp()->setData('forced_currency', quoteStampCurrency());

    $quote->refreshCurrencyStamp();

    expect($quote->getQuoteCurrencyCode())->toBe(QUOTE_STAMP_CURRENCY);
    expect($quote->getBaseToQuoteRate())->toBeNull();
    expect($quote->getStoreToQuoteRate())->toBeNull();
});

it('refuses a forced currency the quote cannot be priced in', function () {
    $quote = quoteToStamp();

    expect(fn() => $quote->setForcedCurrency(quoteStampCurrency()))
        ->toThrow(Mage_Core_Exception::class);
    expect($quote->hasForcedCurrency())->toBeFalse();
});

it('stamps the rate of a currency the store can price in', function () {
    $store = requireUsdBaseStore();
    $quote = quoteToStamp()->setForcedCurrency($store->getBaseCurrency());

    $quote->refreshCurrencyStamp();

    expect($quote->getQuoteCurrencyCode())->toBe($store->getBaseCurrencyCode());
    expect((float) $quote->getBaseToQuoteRate())->toBe(1.0);
});
