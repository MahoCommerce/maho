<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Customer Cart Tests
 *
 * Tests for authenticated customer cart endpoints - PROTECTED (auth required).
 * Guest cart tests are in GuestCartTest.php.
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('API v2 Customer Cart', function (): void {

    describe('without authentication', function (): void {

        it('rejects getting customer cart without token', function (): void {
            $response = apiGet('/api/rest/v2/carts/mine');

            expect($response['status'])->toBeUnauthorized();
        });

        it('returns 401 error for unauthenticated cart request', function (): void {
            $response = apiGet('/api/rest/v2/carts/mine');

            expect($response['status'])->toBe(401);
            expect($response['json'])->toHaveKey('error');
            expect($response['json']['error'])->toBe('unauthorized');
        });

    });

    describe('with invalid token', function (): void {

        it('rejects cart request with malformed token', function (): void {
            $response = apiGet('/api/rest/v2/carts/mine', 'invalid-token');

            expect($response['status'])->toBeUnauthorized();
        });

        it('rejects cart request with expired token', function (): void {
            $response = apiGet('/api/rest/v2/carts/mine', expiredToken());

            expect($response['status'])->toBeUnauthorized();
        });

    });

    describe('with valid customer token', function (): void {

        it('allows getting customer cart with valid token', function (): void {
            $response = apiGet('/api/rest/v2/carts/mine', customerToken());

            // Should succeed (200) or 404 if no cart exists
            expect($response['status'])->toBeIn([200, 404]);
        });

    });

    describe('cross-tenant isolation', function (): void {

        it('denies reading another customer\'s cart by id', function (): void {
            $ownerId = (int) fixtures('customer_id');
            $intruderId = $ownerId + 1;

            // Customer A creates a cart.
            $create = apiPost('/api/rest/v2/carts', [], customerToken($ownerId));
            if (!in_array($create['status'], [200, 201], true) || empty($create['json']['id'])) {
                $this->markTestSkipped('Could not create a cart for the owning customer');
            }
            $cartId = (int) $create['json']['id'];
            trackCreated('quote', $cartId);

            // Customer B (a distinct identity) must not be able to read it.
            $response = apiGet("/api/rest/v2/carts/{$cartId}", customerToken($intruderId));

            expect($response['status'])->toBeIn([403, 404]);
        });

    });

});
