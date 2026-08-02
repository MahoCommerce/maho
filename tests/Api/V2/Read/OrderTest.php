<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Order Endpoint Tests
 *
 * Tests GET /api/rest/v2/orders endpoints.
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('GET /api/rest/v2/orders', function (): void {

    it('allows admin to list all orders', function (): void {
        $response = apiGet('/api/rest/v2/orders', adminToken());

        expect($response['status'])->toBeSuccessful();
        expect($response['json'])->toBeArray();
    });

    it('returns orders in expected format', function (): void {
        $response = apiGet('/api/rest/v2/orders', adminToken());

        expect($response['status'])->toBe(200);

        $json = $response['json'];
        $items = $json['hydra:member'] ?? $json['member'] ?? $json;

        expect($items)->toBeArray();
    });

    it('supports pagination', function (): void {
        $response = apiGet('/api/rest/v2/orders?page=1', adminToken());

        expect($response['status'])->toBeSuccessful();
    });

    it('supports itemsPerPage parameter', function (): void {
        $response = apiGet('/api/rest/v2/orders?itemsPerPage=5', adminToken());

        expect($response['status'])->toBeSuccessful();
    });

    it('supports email exact match filter', function (): void {
        $response = apiGet('/api/rest/v2/orders?email=test@example.com', adminToken());

        expect($response['status'])->toBeSuccessful();
    });

    it('supports emailLike partial match filter', function (): void {
        $response = apiGet('/api/rest/v2/orders?emailLike=example.com', adminToken());

        expect($response['status'])->toBeSuccessful();
    });

    it('requires authentication', function (): void {
        $response = apiGet('/api/rest/v2/orders');

        expect($response['status'])->toBeUnauthorized();
    });

    it('prevents a customer from reading another account\'s order by id', function (): void {
        // A guest/other-account order: the test customer must not be able to read
        // it, never a 200 leaking the order body (the IDOR property this needs).
        $orderId = fixtures('other_customer_order_id');
        if (!$orderId) {
            $this->markTestSkipped('No other_customer_order_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/orders/{$orderId}", customerToken());

        expect($response['status'])->toBeIn([403, 404]);
    });

});

describe('GET /api/rest/v2/customers/me/orders', function (): void {

    describe('without authentication', function (): void {

        it('rejects listing customer orders without token', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/orders');

            expect($response['status'])->toBeUnauthorized();
        });

        it('returns 401 with proper error message', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/orders');

            expect($response['status'])->toBe(401);
            expect($response['json'])->toHaveKey('error');
            expect($response['json']['error'])->toBe('unauthorized');
        });

    });

    describe('with invalid token', function (): void {

        it('rejects customer orders with malformed token', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/orders', 'invalid-token');

            expect($response['status'])->toBeUnauthorized();
        });

        it('rejects customer orders with expired token', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/orders', expiredToken());

            expect($response['status'])->toBeUnauthorized();
        });

    });

    describe('with valid customer token', function (): void {

        it('allows listing customer orders with valid token', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/orders', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

        it('returns array of orders', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/orders', customerToken());

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

        it('supports pagination with pageSize', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/orders?page=1&pageSize=5', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

        it('supports pagination with itemsPerPage', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/orders?page=1&itemsPerPage=5', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

        it('supports status filter', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/orders?status=complete', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

        it('returns orders with expected fields', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/orders', customerToken());

            expect($response['status'])->toBeSuccessful();

            $orders = $response['json']['member'] ?? $response['json']['hydra:member'] ?? $response['json'] ?? [];

            if (empty($orders) || !isset($orders[0])) {
                $this->markTestSkipped('No orders available to verify field structure');
            }

            $order = $orders[0];
            expect($order)->toHaveKey('id');
            expect($order)->toHaveKey('incrementId');
            expect($order)->toHaveKey('status');
        });

        it('only returns orders belonging to authenticated customer', function (): void {
            $response = apiGet('/api/rest/v2/customers/me/orders', customerToken());

            expect($response['status'])->toBeSuccessful();

            $orders = $response['json']['member'] ?? $response['json']['hydra:member'] ?? $response['json'] ?? [];
            if (empty($orders) || !isset($orders[0])) {
                $this->markTestSkipped('No orders available to verify ownership');
            }

            // Every returned order must belong to the authenticated customer.
            $email = fixtures('customer_email');
            foreach ($orders as $order) {
                if (isset($order['customerEmail'])) {
                    expect($order['customerEmail'])->toBe($email);
                }
            }
        });

    });

});

describe('GET /api/rest/v2/orders/{id}', function (): void {

    it('returns order details with admin token', function (): void {
        $orderId = fixtures('order_id');

        if (!$orderId) {
            $this->markTestSkipped('No order_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/orders/{$orderId}", adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toBeArray();
    });

    it('returns expected order fields', function (): void {
        $orderId = fixtures('order_id');

        if (!$orderId) {
            $this->markTestSkipped('No order_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/orders/{$orderId}", adminToken());

        expect($response['status'])->toBe(200);

        $order = $response['json'];
        expect($order)->toHaveKey('incrementId');
    });

    it('returns 404 for non-existent order', function (): void {
        $invalidId = fixtures('invalid_order_id');

        $response = apiGet("/api/rest/v2/orders/{$invalidId}", adminToken());

        expect($response['status'])->toBeNotFound();
    });

    it('requires authentication', function (): void {
        $orderId = fixtures('order_id') ?? 1;

        $response = apiGet("/api/rest/v2/orders/{$orderId}");

        expect($response['status'])->toBeUnauthorized();
    });

    it('exposes the expanded order read surface to admin', function (): void {
        $orderId = fixtures('order_id');

        if (!$orderId) {
            $this->markTestSkipped('No order_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/orders/{$orderId}", adminToken());

        expect($response['status'])->toBe(200);

        $order = $response['json'];
        expect($order)->toHaveKeys([
            'customerIsGuest', 'isVirtual',
            'quoteId', 'weight', 'emailSent',
            'storeName', 'baseCurrencyCode', 'globalCurrencyCode',
            'appliedRuleIds', 'giftcardCodes',
        ]);
        expect($order['customerIsGuest'])->toBeBool();

        // sales_flat_order.customer_group_id is nullable with no default, unlike the
        // quote column it is copied from, so an order can legitimately carry no group.
        if (array_key_exists('customerGroupId', $order)) {
            expect($order['customerGroupId'])->toBeInt();
        }
        expect($order['appliedRuleIds'])->toBeArray();
        expect($order['giftcardCodes'])->toBeArray();
        expect($order['storeName'])->not->toBeEmpty();
        expect($order['baseCurrencyCode'])->not->toBeEmpty();

        // Null fields are omitted from REST responses (skip_null_values). The
        // fixture order is a plain store checkout, so everything an external
        // system, a coupon, a hold or a full customer profile would fill stays
        // unset.
        foreach ([
            'customerNote', 'extOrderId', 'extCustomerId', 'couponRuleName',
            'holdBeforeState', 'holdBeforeStatus',
            'customerMiddlename', 'customerPrefix', 'customerSuffix',
            'customerTaxvat', 'customerDob', 'customerGender',
            'remoteIp', 'xForwardedFor',
        ] as $key) {
            expect($order[$key] ?? null)->toBeNull();
        }
        expect($order['discountDescription'] ?? null)->toBeEmpty();

        expect($order['prices'])->toHaveKeys([
            'baseGrandTotal', 'baseSubtotal', 'baseTaxAmount', 'baseShippingAmount',
            'baseDiscountAmount', 'baseTotalPaid', 'baseTotalRefunded', 'baseTotalDue',
            'shippingTaxAmount', 'hiddenTaxAmount', 'shippingDiscountAmount',
            'adjustmentPositive', 'adjustmentNegative',
            'totalCanceled', 'totalInvoiced',
            'subtotalCanceled', 'subtotalInvoiced', 'subtotalRefunded',
        ]);

        foreach ($order['statusHistory'] ?? [] as $entry) {
            expect($entry)->toHaveKeys(['note', 'status', 'createdAt']);
        }

        if (!empty($order['items'])) {
            $item = $order['items'][0];
            expect($item)->toHaveKeys([
                'productOptions', 'qtyInvoiced', 'originalPrice', 'rowWeight',
                'isVirtual', 'basePrice', 'baseRowTotal', 'storeId', 'createdAt',
            ]);
            expect($item['productOptions'])->toBeArray();
            expect($item['basePrice'])->toBeGreaterThan(0);

            // Purchase cost and the external item reference are never written by
            // a store checkout, so both stay null and are omitted.
            expect($item['baseCost'] ?? null)->toBeNull();
            expect($item['extOrderItemId'] ?? null)->toBeNull();
        }
    });

});
