<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Helpers\RecordingCookie;

uses(Tests\MahoBackendTestCase::class);

/**
 * The currency cookie belongs to a shopper's explicit choice on the storefront.
 * Admin order creation picks an order currency, which is not that, and on a
 * shared domain the cookie it writes lands in the admin's own browser.
 */

describe('Admin order currency', function (): void {

    beforeEach(function (): void {
        $this->cookie = new RecordingCookie();
        Mage::unregister('_singleton/core/cookie');
        Mage::register('_singleton/core/cookie', $this->cookie);
    });

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('choosing an order currency does not write the storefront cookie', function (): void {
        requireUsdBaseStore();
        $store = setStoreDisplayCurrency('USD', 'USD,EUR');

        $rate = (float) $store->getBaseCurrency()->getRate('EUR');
        if ($rate <= 0 || $rate == 1.0) {
            test()->markTestSkipped('USD to EUR rate not available or trivially 1');
        }

        $session = new Mage_Adminhtml_Model_Session_Quote();
        $session->setStoreId(1);
        $session->setCurrencyId('EUR');

        // getStore() applies the admin's chosen order currency to the store.
        $adminStore = $session->getStore();

        // The currency must apply, so the order is priced as the admin asked...
        expect($adminStore->getCurrentCurrency()->getCode())->toBe('EUR');
        expect((float) $adminStore->convertPrice(10.0, false))->toEqualWithDelta(10.0 * $rate, 0.011);

        // ...without any of it reaching the browser making the request.
        expect($this->cookie->writes)->not->toContain(Mage_Core_Model_Store::COOKIE_CURRENCY);
    });

});
