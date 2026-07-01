<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Customer Endpoint Tests
 *
 * Tests GET /api/rest/v2/customers endpoints.
 * All tests are READ-ONLY (safe for synced database).
 */

describe('GET /api/rest/v2/customers/{id}', function (): void {

    it('returns customer data with valid token', function (): void {
        $customerId = fixtures('customer_id');

        if (!$customerId) {
            $this->markTestSkipped('No customer_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/customers/{$customerId}", customerToken($customerId));

        expect($response['status'])->toBe(200);
        expect($response['json'])->toBeArray();
    });

    it('returns expected customer fields', function (): void {
        $customerId = fixtures('customer_id');

        if (!$customerId) {
            $this->markTestSkipped('No customer_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/customers/{$customerId}", customerToken($customerId));

        expect($response['status'])->toBe(200);

        $customer = $response['json'];
        expect($customer)->toHaveKey('email');
    });

    it('returns 404 for non-existent customer', function (): void {
        $invalidId = fixtures('invalid_customer_id');

        $response = apiGet("/api/rest/v2/customers/{$invalidId}", adminToken());

        expect($response['status'])->toBeNotFound();
    });

    it('requires authentication', function (): void {
        $customerId = fixtures('customer_id') ?? 1;

        $response = apiGet("/api/rest/v2/customers/{$customerId}");

        expect($response['status'])->toBeUnauthorized();
    });

    it('prevents accessing other customers data without admin role', function (): void {
        $customerId = fixtures('customer_id') ?? 1;

        // Use a different customer's token
        $differentCustomerId = $customerId + 1;
        $response = apiGet("/api/rest/v2/customers/{$customerId}", customerToken($differentCustomerId));

        // CustomerProvider::provide() calls authorizeCustomerAccess(), which
        // throws AccessDeniedHttpException unless the caller is the owner or
        // an admin. Expect 403 (or 404 if the requested ID doesn't exist).
        expect($response['status'])->toBeIn([403, 404]);
    });

});

describe('GET /api/rest/v2/customers', function (): void {

    it('allows admin to list customers', function (): void {
        $response = apiGet('/api/rest/v2/customers', adminToken());

        // Admin should be able to list customers
        expect($response['status'])->toBeSuccessful();
    });

    it('prevents non-admin from listing all customers', function (): void {
        $response = apiGet('/api/rest/v2/customers', customerToken());

        // CustomerProvider's collection branch enforces admin-or-api-user via
        // requireAdmin(); a ROLE_CUSTOMER token gets 403.
        expect($response['status'])->toBe(403);
    });

    it('requires authentication', function (): void {
        $response = apiGet('/api/rest/v2/customers');

        expect($response['status'])->toBeUnauthorized();
    });

});
