<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * Status codes for endpoints that require a customer identity.
 *
 * requireCustomerId() must distinguish the two failure modes: an anonymous
 * caller is unauthenticated (401), while an authenticated admin or service
 * token that simply isn't a customer is authenticated but not allowed (403).
 *
 * @group write
 */

describe('Endpoints requiring a customer identity', function (): void {

    it('returns 403 for a service token holding wishlists/read on the wishlist listing', function (): void {
        $response = apiGet('/api/rest/v2/customers/me/wishlist', serviceToken(['wishlists/read']));

        expect($response['status'])->toBeForbidden();
    });

    it('returns 403 for an admin token on the newsletter status endpoint', function (): void {
        $response = apiGet('/api/rest/v2/newsletter/status', adminToken());

        expect($response['status'])->toBeForbidden();
    });

    it('keeps returning 401 for anonymous callers', function (): void {
        expect(apiGet('/api/rest/v2/customers/me/wishlist')['status'])->toBeUnauthorized();
        expect(apiGet('/api/rest/v2/newsletter/status')['status'])->toBeUnauthorized();
    });

    it('still serves a customer token', function (): void {
        $response = apiGet('/api/rest/v2/newsletter/status', customerToken());

        expect($response['status'])->toBe(200);
    });

});

afterAll(function (): void {
    cleanupTestData();
});
