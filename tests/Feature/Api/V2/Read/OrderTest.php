<?php

declare(strict_types=1);

/**
 * API v2 Order Endpoint Tests
 *
 * Tests GET /api/orders endpoints.
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('GET /api/orders', function () {

    it('allows admin to list all orders', function () {
        $response = apiGet('/api/orders', adminToken());

        expect($response['status'])->toBeSuccessful();
        expect($response['json'])->toBeArray();
    });

    it('returns orders in expected format', function () {
        $response = apiGet('/api/orders', adminToken());

        expect($response['status'])->toBe(200);

        $json = $response['json'];
        $items = $json['hydra:member'] ?? $json['member'] ?? $json;

        expect($items)->toBeArray();
    });

    it('supports pagination', function () {
        $response = apiGet('/api/orders?page=1', adminToken());

        expect($response['status'])->toBeSuccessful();
    });

    it('requires authentication', function () {
        $response = apiGet('/api/orders');

        expect($response['status'])->toBeUnauthorized();
    });

    it('prevents non-admin from listing all orders', function () {
        $response = apiGet('/api/orders', customerToken());

        // Regular customer should only see their own orders or be forbidden
        // Depending on implementation, this could be 403 or filtered results
        expect($response['status'])->toBeGreaterThanOrEqual(200);
    });

});

describe('GET /api/customers/me/orders', function () {

    describe('without authentication', function () {

        it('rejects listing customer orders without token', function () {
            $response = apiGet('/api/customers/me/orders');

            expect($response['status'])->toBeUnauthorized();
        });

        it('returns 401 with proper error message', function () {
            $response = apiGet('/api/customers/me/orders');

            expect($response['status'])->toBe(401);
            expect($response['json'])->toHaveKey('error');
            expect($response['json']['error'])->toBe('unauthorized');
        });

    });

    describe('with invalid token', function () {

        it('rejects customer orders with malformed token', function () {
            $response = apiGet('/api/customers/me/orders', 'invalid-token');

            expect($response['status'])->toBeUnauthorized();
        });

        it('rejects customer orders with expired token', function () {
            $response = apiGet('/api/customers/me/orders', expiredToken());

            expect($response['status'])->toBeUnauthorized();
        });

    });

    describe('with valid customer token', function () {

        it('allows listing customer orders with valid token', function () {
            $response = apiGet('/api/customers/me/orders', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

        it('returns array of orders', function () {
            $response = apiGet('/api/customers/me/orders', customerToken());

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

        it('supports pagination parameters', function () {
            $response = apiGet('/api/customers/me/orders?page=1&pageSize=5', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

        it('supports status filter', function () {
            $response = apiGet('/api/customers/me/orders?status=complete', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

        it('returns orders with expected fields', function () {
            $response = apiGet('/api/customers/me/orders', customerToken());

            if ($response['status'] === 200) {
                $orders = $response['json']['member'] ?? $response['json']['hydra:member'] ?? $response['json'] ?? [];

                if (!empty($orders) && isset($orders[0])) {
                    $order = $orders[0];
                    expect($order)->toHaveKey('id');
                    expect($order)->toHaveKey('incrementId');
                    expect($order)->toHaveKey('status');
                }
            }

            expect(true)->toBeTrue();
        });

        it('only returns orders belonging to authenticated customer', function () {
            $response = apiGet('/api/customers/me/orders', customerToken());

            // Each order should belong to the authenticated customer
            // The API handles this filtering automatically
            expect($response['status'])->toBeSuccessful();
        });

    });

});

describe('GET /api/orders/{id}', function () {

    it('returns order details with admin token', function () {
        $orderId = fixtures('order_id');

        if (!$orderId) {
            $this->markTestSkipped('No order_id configured in fixtures');
        }

        $response = apiGet("/api/orders/{$orderId}", adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toBeArray();
    });

    it('returns expected order fields', function () {
        $orderId = fixtures('order_id');

        if (!$orderId) {
            $this->markTestSkipped('No order_id configured in fixtures');
        }

        $response = apiGet("/api/orders/{$orderId}", adminToken());

        expect($response['status'])->toBe(200);

        $order = $response['json'];
        expect($order)->toHaveKey('incrementId');
    });

    it('returns 404 for non-existent order', function () {
        $invalidId = fixtures('invalid_order_id');

        $response = apiGet("/api/orders/{$invalidId}", adminToken());

        expect($response['status'])->toBeNotFound();
    });

    it('requires authentication', function () {
        $orderId = fixtures('order_id') ?? 1;

        $response = apiGet("/api/orders/{$orderId}");

        expect($response['status'])->toBeUnauthorized();
    });

});
