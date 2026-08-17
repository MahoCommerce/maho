<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * The stamp says which currency a quote's amounts are in and at what rate, and it is inherited by
 * the order, its invoices and its credit memos. A zero there is not "no rate": it is a rate that
 * converts every amount the customer sees to nothing, and Mage_Sales_Model_Order_Creditmemo
 * multiplies by it unguarded. No rate is stamped as no rate, and a forced currency without one is
 * refused where it is set.
 *
 * The currency is an ISO 4217 "X" code no real currency uses, so no install has a rate for it.
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
    // Written through the data key on purpose: the setter refuses this currency, and the stamp
    // still has to be honest about a rate that goes missing after the currency was chosen.
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
