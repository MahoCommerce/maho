<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * getServeableCurrencyRates() is the single definition of which display currencies a store
 * offers. Base needs no rate row of its own, but the allow list is honored.
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
     * The allow list is configured text that can arrive as "USD, EUR"; unnormalised, the store
     * displays a currency the serveable map does not list.
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

        // Matching the storefront switcher, which never offered a disallowed base either
        expect(array_keys($store->getServeableCurrencyRates()))->toBe(['EUR']);
    });

    /*
     * Falling back to base is only honest where base is one of the currencies the store was
     * configured to sell in. A store allowing only currencies it has no rate for has nothing it
     * can price a sale in, and quietly charging in base would sell the wrong currency outright.
     */
    test('a store whose allowed currency has no rate and whose base is disallowed cannot serve', function (): void {
        $store = useNoRateDisplayCurrency('XTS', 'XTS');

        expect($store->getServeableCurrencyRates())->toBe([]);
        expect(fn() => $store->getCurrentCurrency())->toThrow(Mage_Core_Exception::class);
    });

    test('falls back to base when base is allowed as well', function (): void {
        $store = useNoRateDisplayCurrency('XTS', 'USD,XTS');

        expect($store->getCurrentCurrencyCode())->toBe('USD');
    });

    /*
     * The admin store reads the default scope's configuration, so a misconfigured allow list would
     * otherwise lock the merchant out of the very screen where the rate is entered. Nothing is
     * sold there, and amounts are shown in base, so it always has a currency.
     */
    test('the admin store keeps a currency when the default scope allows none it can serve', function (): void {
        $adminStore = useNoRateDisplayCurrency('XTS', 'XTS', Mage_Core_Model_App::ADMIN_STORE_ID);

        expect($adminStore->getCurrentCurrencyCode())->toBe($adminStore->getBaseCurrencyCode())
            ->and($adminStore->getCurrentCurrencyRate())->toBe(1.0);
    });

    /*
     * Only the storefront refuses: an admin request looks at orders, products and reports of a store
     * that cannot sell, and those screens show amounts in base rather than failing to render.
     */
    test('an admin request shows a store that cannot serve in its base currency', function (): void {
        $store = useNoRateDisplayCurrency('XTS', 'XTS');
        Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

        expect($store->getCurrentCurrencyCode())->toBe($store->getBaseCurrencyCode())
            ->and($store->getCurrentCurrencyRate())->toBe(1.0);
    });

});
