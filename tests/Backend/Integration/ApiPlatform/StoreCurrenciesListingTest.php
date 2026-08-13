<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\GetCollection;
use Mage\Directory\Api\CurrencyProvider;

uses(Tests\MahoBackendTestCase::class);

/**
 * The currency listing is what a client picks from, and X-Currency-Code refuses
 * anything it cannot actually serve, so the two have to agree about which
 * currencies are on offer.
 */

describe('Store currency listing', function (): void {

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('a currency with no imported rate is not offered', function (): void {
        $store = useNoRateDisplayCurrency('GBP', 'USD,GBP');
        Mage::app()->setCurrentStore(1);

        expect($store->getAvailableCurrencyCodes(true))->toContain('GBP');

        $codes = array_map(
            fn(object $dto): string => (string) $dto->code,
            (new CurrencyProvider())->provide(new GetCollection()),
        );

        expect($codes)->toContain('USD');
        expect($codes)->not->toContain('GBP');
    });

    test('the base currency is always offered, rate row or not', function (): void {
        $store = useNoRateDisplayCurrency('GBP', 'USD,GBP');
        Mage::app()->setCurrentStore(1);

        $codes = array_map(
            fn(object $dto): string => (string) $dto->code,
            (new CurrencyProvider())->provide(new GetCollection()),
        );

        expect($codes)->toContain($store->getBaseCurrencyCode());
    });

});
