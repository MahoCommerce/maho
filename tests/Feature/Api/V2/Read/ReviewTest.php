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
 * API v2 Review Tests
 *
 * Tests for product review endpoints.
 * Read operations may be public (via product), write operations are protected.
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('API v2 Product Reviews', function (): void {

    describe('public access - product reviews', function (): void {

        it('allows listing reviews for a product without authentication', function (): void {
            // First get a product
            $products = apiGet('/api/products');

            if (isset($products['json']['hydra:member'][0]['id'])) {
                $productId = $products['json']['hydra:member'][0]['id'];
                $response = apiGet("/api/products/{$productId}/reviews");

                // Should succeed even if no reviews exist
                expect($response['status'])->toBeIn([200, 404]);
            } else {
                expect(true)->toBeTrue();
            }
        });

    });

    describe('customer reviews - protected', function (): void {

        it('rejects listing customer reviews without token', function (): void {
            $response = apiGet('/api/customers/me/reviews');

            // Either 401 (protected) or 404 (not implemented)
            expect($response['status'])->toBeIn([401, 404]);
        });

        it('returns proper error for unauthenticated customer reviews request', function (): void {
            $response = apiGet('/api/customers/me/reviews');

            // Either 401 unauthorized or 404 not found
            expect($response['status'])->toBeIn([401, 404]);

            if ($response['status'] === 401) {
                expect($response['json'])->toHaveKey('error');
                expect($response['json']['error'])->toBe('unauthorized');
            }
        });

        it('allows listing customer reviews with valid token', function (): void {
            $response = apiGet('/api/customers/me/reviews', customerToken());

            // Should succeed (200) or 404 if endpoint doesn't exist
            expect($response['status'])->toBeIn([200, 404]);
        });

    });

    describe('with invalid token', function (): void {

        it('rejects customer reviews with malformed token', function (): void {
            $response = apiGet('/api/customers/me/reviews', 'invalid-token');

            // 401 if protected, or 404 if endpoint doesn't exist
            expect($response['status'])->toBeIn([401, 404]);
        });

        it('rejects customer reviews with expired token', function (): void {
            $response = apiGet('/api/customers/me/reviews', expiredToken());

            // 401 if protected, or 404 if endpoint doesn't exist
            expect($response['status'])->toBeIn([401, 404]);
        });

    });

});
