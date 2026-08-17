<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * An order currency picked in the admin belongs to that order, not to the
 * operator's own browsing, so it applies for the request without being
 * recorded as an explicit choice.
 */

describe('Admin order currency', function (): void {

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('choosing an order currency does not record a shopper choice', function (): void {
        requireUsdBaseStore();
        $store = setStoreDisplayCurrency('USD', 'USD,EUR');

        $rate = (float) Mage::helper('directory')->getRate((string) $store->getBaseCurrencyCode(), 'EUR');
        if ($rate <= 0 || $rate == 1.0) {
            test()->markTestSkipped('USD to EUR rate not available or trivially 1');
        }

        $session = new Mage_Adminhtml_Model_Session_Quote();
        $session->setStoreId(1);
        $session->setCurrencyId('EUR');

        $adminStore = $session->getStore();

        // The currency applies to the order...
        expect($adminStore->getCurrentCurrency()->getCode())->toBe('EUR');
        expect((float) $adminStore->convertPrice(10.0, false))->toEqualWithDelta(10.0 * $rate, 0.011);

        // ...without landing in the session the storefront reads back.
        expect($_SESSION['store_' . $adminStore->getCode()]['currency_code'] ?? null)->toBeNull();
    });

    /*
     * An order has to be recorded in some currency, and the store's base is one it can always be
     * recorded in: against itself it needs no rate. The storefront switcher is a different
     * question, and keeping base out of currency/options/allow answers that one, not this one.
     */
    test('offers the order store its own base currency, allowed or not', function (): void {
        $store = useNoRateDisplayCurrency('GBP', 'GBP');
        Mage::getSingleton('adminhtml/session_quote')->setStoreId((int) $store->getId());

        // The premise: nothing the storefront may display here can be priced.
        expect($store->getServeableCurrencyRates())->toBe([]);

        $codes = Mage::app()->getLayout()->createBlock('adminhtml/sales_order_create_data')
            ->getAvailableCurrencies();

        expect($codes)->toContain((string) $store->getBaseCurrencyCode());
    });

});
