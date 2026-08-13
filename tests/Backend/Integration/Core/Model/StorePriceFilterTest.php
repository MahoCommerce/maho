<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The price filter converts and formats in one step, so a filter carrying the
 * wrong rate prints base-currency numbers under the display currency's symbol.
 * Checkout renders through it more than once per request (the progress sidebar
 * and the shipping rates), which is where a memo that rebuilds itself shows.
 */

describe('Store price filter', function (): void {

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('it converts on every read, not only the first', function (): void {
        $rate = useEurDisplayCurrency();
        $store = Mage::app()->getStore(1);

        $converted = 100.0 * $rate;

        foreach ([1, 2, 3] as $read) {
            expect((float) preg_replace('/[^0-9.]/', '', $store->getPriceFilter()->filter(100)))
                ->toEqualWithDelta($converted, 0.011, "read {$read} lost the rate");
        }
    });

    test('a switch rebuilds it at the new rate', function (): void {
        useEurDisplayCurrency();
        $store = Mage::app()->getStore(1);

        $store->getPriceFilter();
        $store->setCurrentCurrencyCode('USD');

        expect((float) preg_replace('/[^0-9.]/', '', $store->getPriceFilter()->filter(100)))
            ->toEqualWithDelta(100.0, 0.011);
    });

});
