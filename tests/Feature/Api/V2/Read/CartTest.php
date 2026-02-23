<?php

/**
 * Maho
 *
 * @package    Tests
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

describe('API v2 Customer Cart', function (): void {

    describe('without authentication', function (): void {

        it('rejects getting customer cart without token', function (): void {
            $response = apiGet('/api/carts/mine');

            expect($response['status'])->toBeUnauthorized();
        });

        it('returns 401 error for unauthenticated cart request', function (): void {
            $response = apiGet('/api/carts/mine');

            expect($response['status'])->toBe(401);
            expect($response['json'])->toHaveKey('error');
            expect($response['json']['error'])->toBe('unauthorized');
        });

    });

    describe('with invalid token', function (): void {

        it('rejects cart request with malformed token', function (): void {
            $response = apiGet('/api/carts/mine', 'invalid-token');

            expect($response['status'])->toBeUnauthorized();
        });

        it('rejects cart request with expired token', function (): void {
            $response = apiGet('/api/carts/mine', expiredToken());

            expect($response['status'])->toBeUnauthorized();
        });

    });

    describe('with valid customer token', function (): void {

        it('allows getting customer cart with valid token', function (): void {
            $response = apiGet('/api/carts/mine', customerToken());

            // Should succeed (200) or 404 if no cart exists
            expect($response['status'])->toBeIn([200, 404]);
        });

    });

});
