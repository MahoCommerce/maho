<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 REST read-permission enforcement tests.
 *
 * Granular read permissions for API-user tokens are enforced by each read
 * operation's `security: is_granted('<resource>/read')` expression (evaluated
 * by ApiUserVoter) on REST GET requests, the read-side counterpart to the
 * requirePermission() checks in the Processors. A key must hold
 * `<resource>/read` (or `<resource>/all`, or `all`) to read a resource that is
 * gated to ROLE_API_USER. Public operations (security: 'true') stay reachable
 * regardless.
 *
 * @group write
 */

describe('REST read permission enforcement (API-user tokens)', function (): void {

    it('allows reading orders with orders/read', function (): void {
        $response = apiGet('/api/rest/v2/orders', serviceToken(['orders/read']));
        expect($response['status'])->toBe(200);
    });

    it('denies reading orders with only products/read', function (): void {
        $response = apiGet('/api/rest/v2/orders', serviceToken(['products/read']));
        expect($response['status'])->toBe(403);
    });

    it('allows reading orders with the all permission', function (): void {
        $response = apiGet('/api/rest/v2/orders', serviceToken(['all']));
        expect($response['status'])->toBe(200);
    });

    it('still allows public reads regardless of granted permissions', function (): void {
        // /products GET is security: 'true' (public); a narrowly-scoped key must
        // not be blocked from a public endpoint by the read listener.
        $response = apiGet('/api/rest/v2/products', serviceToken(['orders/read']));
        expect($response['status'])->toBe(200);
    });
});
