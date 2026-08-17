<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * getServeableCurrencyRates() is the single definition of which display
 * currencies a store offers. The base currency needs no rate row of its own,
 * but the allow list is honored: a store that excludes base does not offer
 * it, even though the no-rate fallback still resolves to it.
 */

describe('Store serveable currencies', function (): void {

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('an allowed base needs no rate row of its own', function (): void {
        requireUsdBaseStore();
        $store = setStoreDisplayCurrency('USD', 'USD,EUR');

        expect($store->getServeableCurrencyRates()['USD'] ?? null)->toBeGreaterThan(0.0);
    });

    test('a single-currency store offers base alone', function (): void {
        requireUsdBaseStore();
        $store = setStoreDisplayCurrency('USD', 'USD');

        expect($store->getServeableCurrencyRates())->toBe(['USD' => 1.0]);
    });

    test('a rate imported after the map was answered changes the answer', function (): void {
        requireUsdBaseStore();
        $store = setStoreDisplayCurrency('EUR', 'USD,EUR');

        $originalRate = Mage::helper('directory')->getRate('USD', 'EUR');
        if ($originalRate === null) {
            test()->markTestSkipped('USD to EUR rate not available');
        }
        $newRate = $originalRate === 2.5 ? 3.5 : 2.5;

        // Seed every memo the rate table feeds, the way a long-lived process would have.
        $store->getServeableCurrencyRates();
        $store->getCurrentCurrencyRate();

        try {
            Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => $newRate]]);

            expect($store->getServeableCurrencyRates()['EUR'])->toBe($newRate)
                ->and($store->getCurrentCurrencyRate())->toBe($newRate);
        } finally {
            Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => $originalRate]]);
        }
    });

    /*
     * The rate table answers on trimmed, uppercased codes; currency/options/allow is a configured
     * string that a CLI, an import or a config.xml can write as "USD, EUR". When the two spellings
     * differ, the store displays a currency the serveable map does not list, and every price on
     * the page carries that currency's label with the base currency's number.
     */
    test('displays a currency at its own rate when the allow list is not normalised', function (): void {
        requireUsdBaseStore();
        $rate = Mage::helper('directory')->getRate('USD', 'EUR');
        if ($rate === null) {
            test()->markTestSkipped('USD to EUR rate not available');
        }

        $store = setStoreDisplayCurrency(' EUR', 'USD, EUR');

        expect($store->getAvailableCurrencyCodes())->toBe(['USD', 'EUR']);
        expect($store->getCurrentCurrencyCode())->toBe('EUR');
        expect($store->getCurrentCurrencyRate())->toBe($rate);
    });

    test('a base excluded from the allow list is not offered', function (): void {
        requireUsdBaseStore();
        $store = setStoreDisplayCurrency('EUR', 'EUR');

        if ((float) Mage::helper('directory')->getRate((string) $store->getBaseCurrencyCode(), 'EUR') <= 0) {
            test()->markTestSkipped('USD to EUR rate not available');
        }

        // Clients cannot pick base here, matching the storefront switcher,
        // which never offered a disallowed base either. The no-rate fallback
        // can still serve it; serving and offering are different questions.
        expect(array_keys($store->getServeableCurrencyRates()))->toBe(['EUR']);
    });

});
