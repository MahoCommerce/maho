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
 * API v2 Wishlist Tests (REST - Read)
 *
 * Tests for wishlist endpoints - PROTECTED (auth required).
 *
 * @group read
 */

describe('API v2 Wishlist - Authentication', function (): void {

    it('rejects listing wishlist without token', function (): void {
        $response = apiGet('/api/customers/me/wishlist');

        expect($response['status'])->toBeUnauthorized();
    });

    it('returns 401 error body for unauthenticated request', function (): void {
        $response = apiGet('/api/customers/me/wishlist');

        expect($response['status'])->toBe(401);
        expect($response['json'])->toHaveKey('error');
        expect($response['json']['error'])->toBe('unauthorized');
    });

    it('rejects requests with malformed token', function (): void {
        $response = apiGet('/api/customers/me/wishlist', 'invalid-token');

        expect($response['status'])->toBeUnauthorized();
    });

    it('rejects requests with expired token', function (): void {
        $response = apiGet('/api/customers/me/wishlist', expiredToken());

        expect($response['status'])->toBeUnauthorized();
    });

    it('rejects requests with invalid signature', function (): void {
        $response = apiGet('/api/customers/me/wishlist', invalidToken());

        expect($response['status'])->toBeUnauthorized();
    });

});

describe('API v2 Wishlist - Listing', function (): void {

    it('returns 200 with valid customer token', function (): void {
        $response = apiGet('/api/customers/me/wishlist', customerToken());

        expect($response['status'])->toBe(200);
    });

    it('returns a valid collection response', function (): void {
        $response = apiGet('/api/customers/me/wishlist', customerToken());

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
        $response = apiGet('/api/customers/me/wishlist', customerToken());

        expect($response['status'])->toBe(200);
        $totalItems = $response['json']['totalItems'] ?? -1;
        $memberCount = count($response['json']['member'] ?? []);

        expect($totalItems)->toBe($memberCount);
    });

});
