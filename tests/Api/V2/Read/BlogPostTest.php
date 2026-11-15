<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Blog Post Tests
 *
 * Tests for blog post endpoints - PUBLIC ACCESS (no auth required).
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('API v2 Blog Posts', function (): void {

    describe('public access (no auth)', function (): void {

        it('returns blog posts collection', function (): void {
            $response = apiGet('/api/rest/v2/blog-posts');

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

        it('returns 404 for non-existent blog post', function (): void {
            $response = apiGet('/api/rest/v2/blog-posts/999999');

            expect($response['status'])->toBe(404);
        });

    });

    describe('with authentication', function (): void {

        it('allows listing blog posts with admin token', function (): void {
            $response = apiGet('/api/rest/v2/blog-posts', adminToken());

            expect($response['status'])->toBeSuccessful();
        });

    });

});
