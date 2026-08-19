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

});
