<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Country Tests
 *
 * Tests for country and region endpoints - PUBLIC ACCESS (no auth required).
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('API v2 Countries', function (): void {

    describe('public access (no auth)', function (): void {

        it('returns countries collection', function (): void {
            $response = apiGet('/api/rest/v2/countries');

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

        it('allows getting single country without authentication', function (): void {
            $response = apiGet('/api/rest/v2/countries/AU');

            expect($response['status'])->toBe(200);
        });

        it('returns 404 for non-existent country', function (): void {
            $response = apiGet('/api/rest/v2/countries/XX');

            expect($response['status'])->toBe(404);
        });

    });

    describe('response format', function (): void {

        it('includes expected country fields', function (): void {
            $response = apiGet('/api/rest/v2/countries');
            $countries = $response['json']['member'] ?? $response['json']['hydra:member'] ?? [];

            expect($countries)->not->toBeEmpty();
            expect($countries[0])->toHaveKey('id');
            expect($countries[0])->toHaveKey('name');
        });

    });

});
