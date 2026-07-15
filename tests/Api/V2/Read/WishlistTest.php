<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Wishlist Tests (REST - Read)
 *
 * Tests for wishlist endpoints - PROTECTED (auth required).
 *
 * @group read
 */

describe('API v2 Wishlist - Authentication', function (): void {

    it('rejects listing wishlist without token', function (): void {
        $response = apiGet('/api/rest/v2/customers/me/wishlist');

        expect($response['status'])->toBeUnauthorized();
    });

    it('returns 401 error body for unauthenticated request', function (): void {
        $response = apiGet('/api/rest/v2/customers/me/wishlist');

        expect($response['status'])->toBe(401);
        expect($response['json'])->toHaveKey('error');
        expect($response['json']['error'])->toBe('unauthorized');
    });

    it('rejects requests with malformed token', function (): void {
        $response = apiGet('/api/rest/v2/customers/me/wishlist', 'invalid-token');

        expect($response['status'])->toBeUnauthorized();
    });

    it('rejects requests with expired token', function (): void {
        $response = apiGet('/api/rest/v2/customers/me/wishlist', expiredToken());

        expect($response['status'])->toBeUnauthorized();
    });

    it('rejects requests with invalid signature', function (): void {
        $response = apiGet('/api/rest/v2/customers/me/wishlist', invalidToken());

        expect($response['status'])->toBeUnauthorized();
    });

});

describe('API v2 Wishlist - Listing', function (): void {

    it('returns 200 with valid customer token', function (): void {
        $response = apiGet('/api/rest/v2/customers/me/wishlist', customerToken());

        expect($response['status'])->toBe(200);
    });

    it('returns a valid collection response', function (): void {
        $response = apiGet('/api/rest/v2/customers/me/wishlist', customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('@type');
        expect($response['json']['@type'])->toBe('Collection');
        expect($response['json'])->toHaveKey('totalItems');
        expect($response['json'])->toHaveKey('member');
        expect($response['json']['member'])->toBeArray();
    });

    /**
     * Regression: totalItems was hardcoded to 0 in ArrayPaginator,
     * causing the REST listing to always return an empty collection
     * even when wishlist items existed in the database.
     */
    it('returns totalItems matching actual member count (regression: hardcoded totalItems=0)', function (): void {
        $response = apiGet('/api/rest/v2/customers/me/wishlist', customerToken());

        expect($response['status'])->toBe(200);
        $totalItems = $response['json']['totalItems'] ?? -1;
        $memberCount = count($response['json']['member'] ?? []);

        expect($totalItems)->toBe($memberCount);
    });

});

describe('API v2 Wishlist - cross-tenant isolation', function (): void {

    it('does not let another customer read or delete an item', function (): void {
        $ownerId = (int) fixtures('customer_id');
        $intruderId = $ownerId + 1;
        $productId = fixtures('product_id');
        if (!$productId) {
            $this->markTestSkipped('No product_id configured in fixtures');
        }

        // Customer A adds an item to their own wishlist.
        $add = apiPost('/api/rest/v2/customers/me/wishlist', [
            'productId' => $productId,
            'qty' => 1,
        ], customerToken($ownerId));
        if (!in_array($add['status'], [200, 201], true) || empty($add['json']['id'])) {
            $this->markTestSkipped('Could not add a wishlist item for the owning customer');
        }
        $itemId = (int) $add['json']['id'];
        trackCreated('wishlist_item', $itemId);

        // Customer B's own wishlist must not contain A's item.
        $intruderList = apiGet('/api/rest/v2/customers/me/wishlist', customerToken($intruderId));
        expect($intruderList['status'])->toBe(200);
        $intruderIds = array_map(
            static fn($m) => (int) ($m['id'] ?? 0),
            $intruderList['json']['member'] ?? [],
        );
        expect($intruderIds)->not->toContain($itemId);

        // Customer B must not be able to delete A's item.
        $delete = apiDelete("/api/rest/v2/customers/me/wishlist/{$itemId}", customerToken($intruderId));
        expect($delete['status'])->toBeIn([403, 404]);

        // The item must still exist for its owner.
        $ownerList = apiGet('/api/rest/v2/customers/me/wishlist', customerToken($ownerId));
        $ownerIds = array_map(
            static fn($m) => (int) ($m['id'] ?? 0),
            $ownerList['json']['member'] ?? [],
        );
        expect($ownerIds)->toContain($itemId);
    });

});

afterAll(function (): void {
    cleanupTestData();
});
