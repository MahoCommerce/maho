<?php

declare(strict_types=1);

/**
 * API v2 Store Tests
 *
 * Tests for store/store view endpoints - PUBLIC ACCESS (no auth required).
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('API v2 Stores', function () {

    describe('public access (no auth)', function () {

        it('allows listing stores without authentication', function () {
            $response = apiGet('/api/stores');

            expect($response['status'])->toBeSuccessful();
        });

        it('returns stores collection', function () {
            $response = apiGet('/api/stores');

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

        it('allows getting single store without authentication', function () {
            $list = apiGet('/api/stores');
            $members = $list['json']['member'] ?? $list['json']['hydra:member'] ?? [];

            if (!empty($members) && isset($members[0]['id'])) {
                $storeId = $members[0]['id'];
                $response = apiGet("/api/stores/{$storeId}");

                expect($response['status'])->toBeSuccessful();
            } elseif (isset($list['json'][0]['id'])) {
                $storeId = $list['json'][0]['id'];
                $response = apiGet("/api/stores/{$storeId}");

                expect($response['status'])->toBeSuccessful();
            } else {
                expect(true)->toBeTrue();
            }
        });

        it('returns 404 for non-existent store', function () {
            $response = apiGet('/api/stores/999999');

            expect($response['status'])->toBe(404);
        });

    });

    describe('with authentication', function () {

        it('allows listing stores with valid token', function () {
            $response = apiGet('/api/stores', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

        it('allows listing stores with admin token', function () {
            $response = apiGet('/api/stores', adminToken());

            expect($response['status'])->toBeSuccessful();
        });

    });

    describe('response format', function () {

        it('includes expected store fields', function () {
            $response = apiGet('/api/stores');
            $stores = $response['json']['member'] ?? $response['json']['hydra:member'] ?? $response['json'] ?? [];

            if (!empty($stores) && isset($stores[0])) {
                $store = $stores[0];

                expect($store)->toHaveKey('id');
                expect($store)->toHaveKey('code')
                    ->or->toHaveKey('name');
            } else {
                expect(true)->toBeTrue();
            }
        });

    });

});
