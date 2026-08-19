<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Pins that admin order creation offers the currencies the order's store can serve. The block
 * reads its store from the admin quote session, so that is where the store under test is set.
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
 * A base currency left out of the allow list can still record an order; the storefront side is
 * unchanged and StoreServeableCurrenciesTest pins it.
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

// With base excluded and nothing else convertible, the select would otherwise render empty.
it('offers the base currency when the store can serve nothing else', function () {
    $store = useNoRateDisplayCurrency('GBP', 'GBP');

    expect($store->getServeableCurrencyRates())->toBe([]);
    expect(orderCreateDataBlock($store)->getAvailableCurrencies())
        ->toBe([(string) $store->getBaseCurrencyCode()]);
});
