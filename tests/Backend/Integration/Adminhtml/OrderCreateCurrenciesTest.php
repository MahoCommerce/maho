<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The currency an admin order can be placed in has to be one the order's store can serve. The
 * block rebuilt that set from the rate table, taking the codes from the order's store but the
 * base currency from the current one, which in the admin is store 0.
 */
function orderCreateDataBlock(Mage_Core_Model_Store $store): Mage_Adminhtml_Block_Sales_Order_Create_Data
{
    return Mage::app()->getLayout()
        ->createBlock('adminhtml/sales_order_create_data')
        ->setStore($store);
}

afterEach(function () {
    resetCurrencyState();
});

it('offers exactly the currencies the order store can serve', function () {
    $store = requireUsdBaseStore();
    setStoreDisplayCurrency('USD', 'USD,EUR');

    expect(orderCreateDataBlock($store)->getAvailableCurrencies())
        ->toBe(array_keys($store->getServeableCurrencyRates()));
});

// A base currency left out of the allow list is not one the store serves, so an order cannot be
// placed in it either.
it('does not offer a base currency the store is not allowed to serve', function () {
    $store = requireUsdBaseStore();
    setStoreDisplayCurrency('EUR', 'EUR');
    if (!isset($store->getServeableCurrencyRates()['EUR'])) {
        test()->markTestSkipped('This install has no USD to EUR rate');
    }

    expect(orderCreateDataBlock($store)->getAvailableCurrencies())->toBe(['EUR']);
});

// Unless the store is actually serving it: with no rate for the only allowed currency, the store
// falls back to base, and the form has to be able to show the currency the order is in.
it('offers the currency the store fell back to', function () {
    $store = requireUsdBaseStore();
    setStoreDisplayCurrency('GBP', 'GBP');
    if (isset($store->getServeableCurrencyRates()['GBP'])) {
        test()->markTestSkipped('This install has a USD to GBP rate, so there is no fallback');
    }

    expect($store->getCurrentCurrencyCode())->toBe('USD');
    expect(orderCreateDataBlock($store)->getAvailableCurrencies())->toContain('USD');
});
