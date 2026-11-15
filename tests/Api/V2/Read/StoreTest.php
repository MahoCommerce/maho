<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Store Tests
 *
 * Tests for store/store view endpoints - PUBLIC ACCESS (no auth required).
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('API v2 Stores', function (): void {

    describe('public access (no auth)', function (): void {

        it('returns stores collection', function (): void {
            $response = apiGet('/api/rest/v2/stores');

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

        it('allows getting single store without authentication', function (): void {
            $response = apiGet('/api/rest/v2/stores/1');

            expect($response['status'])->toBe(200);
            expect($response['json']['id'] ?? null)->toBe(1);
        });

        it('returns 404 for non-existent store', function (): void {
            $response = apiGet('/api/rest/v2/stores/999999');

            expect($response['status'])->toBe(404);
        });

    });

    describe('response format', function (): void {

        it('includes expected store fields', function (): void {
            $response = apiGet('/api/rest/v2/stores');
            $stores = $response['json']['member'] ?? $response['json']['hydra:member'] ?? [];

            expect($stores)->not->toBeEmpty();
            expect($stores[0])->toHaveKeys(['id', 'code', 'name']);
        });

    });

});
