<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Currency Tests
 *
 * Tests for the currency endpoint - PUBLIC ACCESS (no auth required).
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('API v2 Currencies', function (): void {

    describe('public access (no auth)', function (): void {

        // directory_currency_rate is seeded at install (EUR/USD, including the
        // self-rates), so this collection always carries at least one rate. On
        // MySQL and PostgreSQL the DECIMAL column comes back as a PHP string,
        // which used to hit the ?float DTO property and 500 the whole endpoint.
        it('allows listing currencies without authentication', function (): void {
            $response = apiGet('/api/rest/v2/stores/currencies');

            expect($response['status'])->toBe(200);
        });

        it('returns currencies collection', function (): void {
            $response = apiGet('/api/rest/v2/stores/currencies');

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

    });

    describe('response format', function (): void {

        it('includes expected currency fields', function (): void {
            $response = apiGet('/api/rest/v2/stores/currencies');
            $currencies = $response['json']['member'] ?? $response['json']['hydra:member'] ?? $response['json'] ?? [];

            expect($currencies)->not->toBeEmpty();

            foreach ($currencies as $currency) {
                expect($currency)->toHaveKey('code');
                expect($currency)->toHaveKey('symbol');
                expect($currency)->toHaveKey('exchangeRate');
            }
        });

        // Guards both directions: a string rate means the DTO cast was dropped,
        // and a string in the payload would also mean someone "fixed" the 500
        // by widening the property to ?string instead.
        it('exposes the exchange rate as a number, never a string', function (): void {
            $response = apiGet('/api/rest/v2/stores/currencies');
            $currencies = $response['json']['member'] ?? $response['json']['hydra:member'] ?? $response['json'] ?? [];

            expect($currencies)->not->toBeEmpty();

            foreach ($currencies as $currency) {
                if ($currency['exchangeRate'] === null) {
                    continue;
                }
                expect($currency['exchangeRate'])->not->toBeString();
                expect($currency['exchangeRate'])->toBeNumeric();
            }
        });

        it('reports the base currency self-rate as 1', function (): void {
            $baseCode = \Mage::app()->getStore()->getBaseCurrencyCode();

            $response = apiGet('/api/rest/v2/stores/currencies');
            $currencies = $response['json']['member'] ?? $response['json']['hydra:member'] ?? $response['json'] ?? [];

            $base = null;
            foreach ($currencies as $currency) {
                if ($currency['code'] === $baseCode) {
                    $base = $currency;
                    break;
                }
            }

            expect($base)->not->toBeNull();
            expect((float) $base['exchangeRate'])->toBe(1.0);
        });

    });

});
