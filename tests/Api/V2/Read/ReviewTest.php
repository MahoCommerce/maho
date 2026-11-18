<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
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

    it('allows listing reviews for a product without authentication', function (): void {
        $productId = fixtures('product_id');
        if (!$productId) {
            $this->markTestSkipped('No product_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/products/{$productId}/reviews");

        expect($response['status'])->toBe(200);
    });

    it('allows listing customer reviews with valid token', function (): void {
        $response = apiGet('/api/rest/v2/customers/me/reviews', customerToken());

        expect($response['status'])->toBe(200);
    });

});
