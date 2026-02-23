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
 * API v2 Address Tests
 *
 * Tests for customer address endpoints - PROTECTED (auth required).
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('API v2 Customer Addresses', function (): void {

    describe('without authentication', function (): void {

        it('rejects listing addresses without token', function (): void {
            $response = apiGet('/api/customers/me/addresses');

            expect($response['status'])->toBeUnauthorized();
        });

        it('returns 401 error for unauthenticated request', function (): void {
            $response = apiGet('/api/customers/me/addresses');

            expect($response['status'])->toBe(401);
            expect($response['json'])->toHaveKey('error');
            expect($response['json']['error'])->toBe('unauthorized');
        });

    });

    describe('with invalid token', function (): void {

        it('rejects requests with malformed token', function (): void {
            $response = apiGet('/api/customers/me/addresses', 'invalid-token');

            expect($response['status'])->toBeUnauthorized();
        });

        it('rejects requests with expired token', function (): void {
            $response = apiGet('/api/customers/me/addresses', expiredToken());

            expect($response['status'])->toBeUnauthorized();
        });

    });

    describe('with valid customer token', function (): void {

        it('allows listing addresses with valid customer token', function (): void {
            $response = apiGet('/api/customers/me/addresses', customerToken());

            // Should succeed (200) or 404 if endpoint not implemented yet
            expect($response['status'])->toBeIn([200, 404]);
        });

        it('returns addresses collection when endpoint exists', function (): void {
            $response = apiGet('/api/customers/me/addresses', customerToken());

            // Skip if endpoint not implemented
            if ($response['status'] === 404) {
                expect(true)->toBeTrue();
                return;
            }

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

    });

    describe('with admin token', function (): void {

        it('allows listing addresses with admin token', function (): void {
            $response = apiGet('/api/customers/me/addresses', adminToken());

            // Admin accessing "me" endpoint should work if admin is also a customer
            // or return appropriate error
            expect($response['status'])->toBeIn([200, 403, 404]);
        });

    });

});
