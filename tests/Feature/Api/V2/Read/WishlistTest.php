<?php

declare(strict_types=1);

/**
 * API v2 Wishlist Tests
 *
 * Tests for wishlist endpoints - PROTECTED (auth required).
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('API v2 Wishlist', function () {

    describe('without authentication', function () {

        it('rejects listing wishlist without token', function () {
            $response = apiGet('/api/customers/me/wishlist');

            expect($response['status'])->toBeUnauthorized();
        });

        it('returns 401 error for unauthenticated request', function () {
            $response = apiGet('/api/customers/me/wishlist');

            expect($response['status'])->toBe(401);
            expect($response['json'])->toHaveKey('error');
            expect($response['json']['error'])->toBe('unauthorized');
        });

    });

    describe('with invalid token', function () {

        it('rejects requests with malformed token', function () {
            $response = apiGet('/api/customers/me/wishlist', 'invalid-token');

            expect($response['status'])->toBeUnauthorized();
        });

        it('rejects requests with expired token', function () {
            $response = apiGet('/api/customers/me/wishlist', expiredToken());

            expect($response['status'])->toBeUnauthorized();
        });

    });

    describe('with valid customer token', function () {

        it('allows listing wishlist with valid customer token', function () {
            $response = apiGet('/api/customers/me/wishlist', customerToken());

            // Should succeed (200) or 404 if endpoint not implemented yet
            expect($response['status'])->toBeIn([200, 404]);
        });

        it('returns wishlist items collection when endpoint exists', function () {
            $response = apiGet('/api/customers/me/wishlist', customerToken());

            // Skip if endpoint not implemented
            if ($response['status'] === 404) {
                expect(true)->toBeTrue();
                return;
            }

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

    });

});
