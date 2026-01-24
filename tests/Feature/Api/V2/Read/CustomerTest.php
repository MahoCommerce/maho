<?php

declare(strict_types=1);

/**
 * API v2 Customer Endpoint Tests
 *
 * Tests GET /api/customers endpoints.
 * All tests are READ-ONLY (safe for synced database).
 */

describe('GET /api/customers/{id}', function () {

    it('returns customer data with valid token', function () {
        $customerId = fixtures('customer_id');

        if (!$customerId) {
            $this->markTestSkipped('No customer_id configured in fixtures');
        }

        $response = apiGet("/api/customers/{$customerId}", customerToken($customerId));

        expect($response['status'])->toBe(200);
        expect($response['json'])->toBeArray();
    });

    it('returns expected customer fields', function () {
        $customerId = fixtures('customer_id');

        if (!$customerId) {
            $this->markTestSkipped('No customer_id configured in fixtures');
        }

        $response = apiGet("/api/customers/{$customerId}", customerToken($customerId));

        expect($response['status'])->toBe(200);

        $customer = $response['json'];
        expect($customer)->toHaveKey('email');
    });

    it('returns 404 for non-existent customer', function () {
        $invalidId = fixtures('invalid_customer_id');

        $response = apiGet("/api/customers/{$invalidId}", adminToken());

        expect($response['status'])->toBeNotFound();
    });

    it('requires authentication', function () {
        $customerId = fixtures('customer_id') ?? 1;

        $response = apiGet("/api/customers/{$customerId}");

        expect($response['status'])->toBeUnauthorized();
    });

    it('prevents accessing other customers data without admin role', function () {
        $customerId = fixtures('customer_id') ?? 1;

        // Use a different customer's token
        $differentCustomerId = $customerId + 1;
        $response = apiGet("/api/customers/{$customerId}", customerToken($differentCustomerId));

        // Should be forbidden (not your data) or not found
        expect($response['status'])->toBeGreaterThanOrEqual(400);
    })->skip('Customer access control not yet enforced in provider');

});

describe('GET /api/customers', function () {

    it('allows admin to list customers', function () {
        $response = apiGet('/api/customers', adminToken());

        // Admin should be able to list customers
        expect($response['status'])->toBeSuccessful();
    });

    it('prevents non-admin from listing all customers', function () {
        $response = apiGet('/api/customers', customerToken());

        // Regular customer should not list all customers
        expect($response['status'])->toBeGreaterThanOrEqual(400);
    })->skip('Customer access control not yet enforced in provider');

    it('requires authentication', function () {
        $response = apiGet('/api/customers');

        expect($response['status'])->toBeUnauthorized();
    });

});
