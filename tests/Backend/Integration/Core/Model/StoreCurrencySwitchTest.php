<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A currency switch has to apply to the store instance that receives it, not
 * only to the next request: admin order creation sets the order currency and
 * then reads prices off the same instance.
 */

describe('Store currency switch', function (): void {

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('a switch takes effect even after the currency was already resolved', function (): void {
        requireUsdBaseStore();
        $store = setStoreDisplayCurrency('USD', 'USD,EUR');

        $rate = (float) $store->getBaseCurrency()->getRate('EUR');
        if ($rate <= 0 || $rate == 1.0) {
            test()->markTestSkipped('USD to EUR rate not available or trivially 1');
        }

        // Convert first, so the resolved currency is memoised.
        expect($store->getCurrentCurrency()->getCode())->toBe('USD');
        expect((float) $store->convertPrice(10.0, false))->toEqualWithDelta(10.0, 0.001);

        $store->setCurrentCurrencyCode('EUR');

        expect($store->getCurrentCurrency()->getCode())->toBe('EUR');
        expect($store->getCurrentCurrencyCode())->toBe('EUR');
        expect((float) $store->convertPrice(10.0, false))->toEqualWithDelta(10.0 * $rate, 0.011);
    });

    test('a switch also refreshes the price filter, not just the currency', function (): void {
        requireUsdBaseStore();
        $store = setStoreDisplayCurrency('USD', 'USD,EUR');

        $rate = (float) $store->getBaseCurrency()->getRate('EUR');
        if ($rate <= 0 || $rate == 1.0) {
            test()->markTestSkipped('USD to EUR rate not available or trivially 1');
        }

        // A second memo built from the same currency and rate.
        $before = $store->getPriceFilter()->filter(10.0);
        $store->setCurrentCurrencyCode('EUR');
        $after = $store->getPriceFilter()->filter(10.0);

        expect($after)->not->toBe($before);
    });

});
