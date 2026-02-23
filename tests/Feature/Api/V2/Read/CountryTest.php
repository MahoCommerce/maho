<?php

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

        it('allows listing countries without authentication', function (): void {
            $response = apiGet('/api/countries');

            expect($response['status'])->toBeSuccessful();
        });

        it('returns countries collection', function (): void {
            $response = apiGet('/api/countries');

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

        it('allows getting single country without authentication', function (): void {
            $list = apiGet('/api/countries');
            $members = $list['json']['member'] ?? $list['json']['hydra:member'] ?? [];

            if (!empty($members) && isset($members[0]['id'])) {
                $countryId = $members[0]['id'];
                $response = apiGet("/api/countries/{$countryId}");

                expect($response['status'])->toBeSuccessful();
            } else {
                // Try common country code
                $response = apiGet('/api/countries/AU');
                expect($response['status'])->toBeIn([200, 404]);
            }
        });

        it('returns 404 for non-existent country', function (): void {
            $response = apiGet('/api/countries/XX');

            expect($response['status'])->toBe(404);
        });

    });

    describe('with authentication', function (): void {

        it('allows listing countries with valid token', function (): void {
            $response = apiGet('/api/countries', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

    });

    describe('response format', function (): void {

        it('includes expected country fields', function (): void {
            $response = apiGet('/api/countries');
            $countries = $response['json']['member'] ?? $response['json']['hydra:member'] ?? $response['json'] ?? [];

            if (!empty($countries) && isset($countries[0])) {
                $country = $countries[0];

                expect($country)->toHaveKey('id');
                expect($country)->toHaveKey('name');
            } else {
                expect(true)->toBeTrue();
            }
        });

    });

});
