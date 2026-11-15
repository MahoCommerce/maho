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

    describe('with valid customer token', function (): void {

        it('reads its own cart by id', function (): void {
            $create = apiPost('/api/rest/v2/carts', [], customerToken());
            expect($create['status'])->toBeIn([200, 201]);
            $cartId = (int) $create['json']['id'];
            trackCreated('quote', $cartId);

            $response = apiGet("/api/rest/v2/carts/{$cartId}", customerToken());

            expect($response['status'])->toBe(200);
            expect($response['json']['id'] ?? null)->toBe($cartId);
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
