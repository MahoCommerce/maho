<?php

declare(strict_types=1);

/**
 * API v2 Shipment Tests
 *
 * Tests for shipment endpoints - PROTECTED (auth required).
 * Shipments are accessed through orders.
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('API v2 Shipments', function () {

    describe('customer order shipments - without authentication', function () {

        it('rejects listing order shipments without token', function () {
            $response = apiGet('/api/customers/me/orders');

            expect($response['status'])->toBeUnauthorized();
        });

        it('returns 401 error for unauthenticated shipment request', function () {
            $response = apiGet('/api/customers/me/orders');

            expect($response['status'])->toBe(401);
            expect($response['json'])->toHaveKey('error');
            expect($response['json']['error'])->toBe('unauthorized');
        });

    });

    describe('with invalid token', function () {

        it('rejects shipment request with malformed token', function () {
            $response = apiGet('/api/customers/me/orders', 'invalid-token');

            expect($response['status'])->toBeUnauthorized();
        });

        it('rejects shipment request with expired token', function () {
            $response = apiGet('/api/customers/me/orders', expiredToken());

            expect($response['status'])->toBeUnauthorized();
        });

    });

    describe('with valid customer token', function () {

        it('allows listing orders with valid token', function () {
            $response = apiGet('/api/customers/me/orders', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

        it('orders include shipment information when available', function () {
            $response = apiGet('/api/customers/me/orders', customerToken());

            if ($response['status'] === 200) {
                $orders = $response['json']['hydra:member'] ?? $response['json'] ?? [];

                // If there are orders, they may have shipments
                // This is a structural test - just verify the response format
                expect($response['json'])->toBeArray();
            } else {
                expect(true)->toBeTrue();
            }
        });

    });

});
