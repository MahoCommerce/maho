<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * getCurrentCurrency() falls back to base when the display currency has no
 * imported rate. The code accessor must report that same fallback, and the
 * fallback must leave the shopper's chosen currency alone.
 */

describe('Store currency fallback', function (): void {

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('the code accessor reports the fallback currency, whichever is read first', function (): void {
        $store = useNoRateDisplayCurrency('GBP', 'USD,GBP');

        // Nothing has resolved the currency object yet; they must still agree.
        expect($store->getCurrentCurrencyCode())->toBe('USD');
        expect($store->getCurrentCurrency()->getCode())->toBe('USD');
    });

    test('the code accessor reports the fallback without a session to lean on', function (): void {
        $store = useNoRateDisplayCurrency('GBP', 'USD,GBP');

        // The API has no store session to lean on: init() gets no session name
        // and start() returns early, so nothing written there survives.
        $session = new ReflectionProperty(Mage_Core_Model_Store::class, '_session');
        $session->setValue($store, null);
        $store->unsetData('current_currency');

        expect($store->getCurrentCurrencyCode())->toBe('USD');
    });

    test('the fallback leaves an explicit currency choice intact', function (): void {
        $store = useNoRateDisplayCurrency('GBP', 'USD,GBP');

        // Resolving must not rewrite the choice, or importing the rate later
        // would not restore it.
        $store->setCurrentCurrencyCode('GBP');
        expect($store->getCurrentCurrency()->getCode())->toBe('USD');

        expect($_SESSION['store_' . $store->getCode()]['currency_code'] ?? null)->toBe('GBP');
    });

    test('the fallback is memoised on the instance, so prices convert at parity', function (): void {
        $store = useNoRateDisplayCurrency('GBP', 'USD,GBP');

        $resolved = $store->getCurrentCurrency();
        expect($resolved->getCode())->toBe('USD');

        expect($store->getCurrentCurrency())->toBe($resolved);
        expect((float) $store->convertPrice(10.0, false))->toEqualWithDelta(10.0, 0.001);
    });

    test('an explicit currency switch is recorded for the next request', function (): void {
        requireUsdBaseStore();
        $store = setStoreDisplayCurrency('USD', 'USD,EUR');

        if ((float) Mage::helper('directory')->getRate((string) $store->getBaseCurrencyCode(), 'EUR') <= 0) {
            test()->markTestSkipped('USD to EUR rate not available');
        }

        $store->setCurrentCurrencyCode('EUR');

        expect($_SESSION['store_' . $store->getCode()]['currency_code'] ?? null)->toBe('EUR');
    });

});
