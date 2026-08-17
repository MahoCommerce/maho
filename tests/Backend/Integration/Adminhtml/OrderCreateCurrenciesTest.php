<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The currency an admin order can be placed in has to be one the order's store can serve, which
 * is a question the store answers. What these pin is that answer, not the scope defect the
 * rebuild also carried: reading the base currency from the current store instead of the order's
 * only differs on an install whose stores have different base currencies, which needs a second
 * website to reproduce.
 *
 * The block reads its store from the admin quote session, not from block data, so that is where
 * the store under test has to be set.
 */
function orderCreateDataBlock(Mage_Core_Model_Store $store): Mage_Adminhtml_Block_Sales_Order_Create_Data
{
    Mage::getSingleton('adminhtml/session_quote')->setStoreId((int) $store->getId());

    return Mage::app()->getLayout()->createBlock('adminhtml/sales_order_create_data');
}

afterEach(function () {
    Mage::getSingleton('adminhtml/session_quote')->setStoreId(null);
    resetCurrencyState();
});

it('offers exactly the currencies the order store can serve', function () {
    $store = requireUsdBaseStore();
    setStoreDisplayCurrency('USD', 'USD,EUR');

    expect(orderCreateDataBlock($store)->getAvailableCurrencies())
        ->toBe(array_keys($store->getServeableCurrencyRates()));
});

/*
 * Booking an order is not displaying a catalog. A base currency left out of the allow list is off
 * the storefront switcher, which is what that setting says, and it is still the currency an order
 * can be recorded in: against itself it always has a rate. The storefront side of that answer is
 * unchanged, and StoreServeableCurrenciesTest still pins it.
 */
it('offers the order store its own base currency, allowed or not', function () {
    $store = requireUsdBaseStore();
    setStoreDisplayCurrency('EUR', 'EUR');
    if (!isset($store->getServeableCurrencyRates()['EUR'])) {
        test()->markTestSkipped('This install has no USD to EUR rate');
    }

    expect(orderCreateDataBlock($store)->getAvailableCurrencies())
        ->toBe(['EUR', (string) $store->getBaseCurrencyCode()]);
});

// The shape that made this the answer: with base excluded and nothing else convertible, the store
// serves no currency at all, and the select would render with no options in it.
it('offers the base currency when the store can serve nothing else', function () {
    $store = useNoRateDisplayCurrency('GBP', 'GBP');

    expect($store->getServeableCurrencyRates())->toBe([]);
    expect(orderCreateDataBlock($store)->getAvailableCurrencies())
        ->toBe([(string) $store->getBaseCurrencyCode()]);
});
