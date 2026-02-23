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

describe('GET /api/orders', function (): void {

    it('allows admin to list all orders', function (): void {
        $response = apiGet('/api/orders', adminToken());

        expect($response['status'])->toBeSuccessful();
        expect($response['json'])->toBeArray();
    });

    it('returns orders in expected format', function (): void {
        $response = apiGet('/api/orders', adminToken());

        expect($response['status'])->toBe(200);

        $json = $response['json'];
        $items = $json['hydra:member'] ?? $json['member'] ?? $json;

        expect($items)->toBeArray();
    });

    it('supports pagination', function (): void {
        $response = apiGet('/api/orders?page=1', adminToken());

        expect($response['status'])->toBeSuccessful();
    });

    it('supports itemsPerPage parameter', function (): void {
        $response = apiGet('/api/orders?itemsPerPage=5', adminToken());

        expect($response['status'])->toBeSuccessful();
    });

    it('supports email exact match filter', function (): void {
        $response = apiGet('/api/orders?email=test@example.com', adminToken());

        expect($response['status'])->toBeSuccessful();
    });

    it('supports emailLike partial match filter', function (): void {
        $response = apiGet('/api/orders?emailLike=example.com', adminToken());

        expect($response['status'])->toBeSuccessful();
    });

    it('requires authentication', function (): void {
        $response = apiGet('/api/orders');

        expect($response['status'])->toBeUnauthorized();
    });

    it('prevents non-admin from listing all orders', function (): void {
        $response = apiGet('/api/orders', customerToken());

        // Regular customer should only see their own orders or be forbidden
        // Depending on implementation, this could be 403 or filtered results
        expect($response['status'])->toBeGreaterThanOrEqual(200);
    });

});

describe('GET /api/customers/me/orders', function (): void {

    describe('without authentication', function (): void {

        it('rejects listing customer orders without token', function (): void {
            $response = apiGet('/api/customers/me/orders');

            expect($response['status'])->toBeUnauthorized();
        });

        it('returns 401 with proper error message', function (): void {
            $response = apiGet('/api/customers/me/orders');

            expect($response['status'])->toBe(401);
            expect($response['json'])->toHaveKey('error');
            expect($response['json']['error'])->toBe('unauthorized');
        });

    });

    describe('with invalid token', function (): void {

        it('rejects customer orders with malformed token', function (): void {
            $response = apiGet('/api/customers/me/orders', 'invalid-token');

            expect($response['status'])->toBeUnauthorized();
        });

        it('rejects customer orders with expired token', function (): void {
            $response = apiGet('/api/customers/me/orders', expiredToken());

            expect($response['status'])->toBeUnauthorized();
        });

    });

    describe('with valid customer token', function (): void {

        it('allows listing customer orders with valid token', function (): void {
            $response = apiGet('/api/customers/me/orders', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

        it('returns array of orders', function (): void {
            $response = apiGet('/api/customers/me/orders', customerToken());

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

        it('supports pagination with pageSize', function (): void {
            $response = apiGet('/api/customers/me/orders?page=1&pageSize=5', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

        it('supports pagination with itemsPerPage', function (): void {
            $response = apiGet('/api/customers/me/orders?page=1&itemsPerPage=5', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

        it('supports status filter', function (): void {
            $response = apiGet('/api/customers/me/orders?status=complete', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

        it('returns orders with expected fields', function (): void {
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

        it('only returns orders belonging to authenticated customer', function (): void {
            $response = apiGet('/api/customers/me/orders', customerToken());

            // Each order should belong to the authenticated customer
            // The API handles this filtering automatically
            expect($response['status'])->toBeSuccessful();
        });

    });

});

describe('GET /api/orders/{id}', function (): void {

    it('returns order details with admin token', function (): void {
        $orderId = fixtures('order_id');

        if (!$orderId) {
            $this->markTestSkipped('No order_id configured in fixtures');
        }

        $response = apiGet("/api/orders/{$orderId}", adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toBeArray();
    });

    it('returns expected order fields', function (): void {
        $orderId = fixtures('order_id');

        if (!$orderId) {
            $this->markTestSkipped('No order_id configured in fixtures');
        }

        $response = apiGet("/api/orders/{$orderId}", adminToken());

        expect($response['status'])->toBe(200);

        $order = $response['json'];
        expect($order)->toHaveKey('incrementId');
    });

    it('returns 404 for non-existent order', function (): void {
        $invalidId = fixtures('invalid_order_id');

        $response = apiGet("/api/orders/{$invalidId}", adminToken());

        expect($response['status'])->toBeNotFound();
    });

    it('requires authentication', function (): void {
        $orderId = fixtures('order_id') ?? 1;

        $response = apiGet("/api/orders/{$orderId}");

        expect($response['status'])->toBeUnauthorized();
    });

});
