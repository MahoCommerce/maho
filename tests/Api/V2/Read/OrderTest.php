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

    it('returns orders in expected format', function (): void {
        $response = apiGet('/api/rest/v2/orders', adminToken());

        expect($response['status'])->toBe(200);

        $json = $response['json'];
        $items = $json['hydra:member'] ?? $json['member'] ?? $json;

        expect($items)->toBeArray();
    });

    it('returns one page of the size that the query asks for', function (string $query): void {
        $response = apiGet("/api/rest/v2/orders?{$query}", adminToken());

        expect($response['status'])->toBe(200);
        expect(getItems($response))->toHaveCount(1);
    })->with([
        'page' => ['page=1&itemsPerPage=1'],
        'itemsPerPage' => ['itemsPerPage=1'],
    ]);

    it('supports email exact match filter', function (): void {
        $email = (string) fixtures('customer_email');
        $response = apiGet('/api/rest/v2/orders?itemsPerPage=100&email=' . urlencode($email), adminToken());

        expect($response['status'])->toBe(200);
        $emails = array_column(getItems($response), 'customerEmail');
        expect($emails)->not->toBeEmpty();
        expect(array_unique($emails))->toBe([$email]);
    });

    it('supports emailLike partial match filter', function (): void {
        $email = (string) fixtures('customer_email');
        $part = substr($email, 1);
        $response = apiGet('/api/rest/v2/orders?itemsPerPage=100&emailLike=' . urlencode($part), adminToken());

        expect($response['status'])->toBe(200);
        $emails = array_column(getItems($response), 'customerEmail');
        expect($emails)->toContain($email);
        foreach ($emails as $match) {
            expect($match)->toContain($part);
        }
    });

    it('finds an order by part of its number with the free-text search', function (): void {
        $orderId = fixtures('order_id');
        if (!$orderId) {
            $this->markTestSkipped('No order_id configured in fixtures');
        }
        $incrementId = (string) \Mage::getModel('sales/order')->load($orderId)->getIncrementId();

        $response = apiGet('/api/rest/v2/orders?itemsPerPage=100&search=' . urlencode(substr($incrementId, -4)), adminToken());

        expect($response['status'])->toBe(200);
        expect(array_column($response['json']['member'] ?? [], 'incrementId'))->toContain($incrementId);
    });

    it('needs every word of the free-text search to match', function (): void {
        $response = apiGet('/api/rest/v2/orders?search=' . urlencode('zzqx-no-order zzqy-matches'), adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['member'] ?? [])->toBe([]);
    });

    it('uses only the first words of a long free-text search', function (): void {
        $orderId = fixtures('order_id');
        if (!$orderId) {
            $this->markTestSkipped('No order_id configured in fixtures');
        }
        $incrementId = (string) \Mage::getModel('sales/order')->load($orderId)->getIncrementId();
        $words = array_fill(0, \Mage_Core_Helper_String::MAX_SEARCH_WORDS, substr($incrementId, -4));
        $words[] = 'zzqx-no-order';

        $response = apiGet('/api/rest/v2/orders?itemsPerPage=100&search=' . urlencode(implode(' ', $words)), adminToken());

        expect($response['status'])->toBe(200);
        expect(array_column($response['json']['member'] ?? [], 'incrementId'))->toContain($incrementId);
    });

    it('rejects a list where a filter takes one value', function (string $query, string $name): void {
        $response = apiGet("/api/rest/v2/orders?{$query}", adminToken());

        expect($response['status'])->toBe(400);
        expect($response['json']['message'] ?? $response['json']['detail'] ?? '')->toContain($name);
    })->with([
        'search' => ['search[]=x', 'search'],
        'email' => ['email[]=a@example.com', 'email'],
        'emailLike' => ['emailLike[]=example', 'emailLike'],
        'status' => ['status[]=pending', 'status'],
        'incrementId' => ['incrementId[]=100000001', 'incrementId'],
        'storeId' => ['storeId[]=1', 'storeId'],
        'createdFrom' => ['createdFrom[]=2026-01-01', 'createdFrom'],
    ]);

    it('prevents a customer from reading another account\'s order by id', function (): void {
        // A guest/other-account order: the test customer must not be able to read
        // it, never a 200 leaking the order body (the IDOR property this needs).
        $orderId = fixtures('other_customer_order_id');
        if (!$orderId) {
            $this->markTestSkipped('No other_customer_order_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/orders/{$orderId}", customerToken());

        // 404 and not 403: a status differing from the missing-id response
        // would make the endpoint an order-id oracle. The body must match too,
        // or the oracle survives at the message level.
        expect($response['status'])->toBe(404);
        $missing = apiGet('/api/rest/v2/orders/99999999', customerToken());
        expect($missing['status'])->toBe($response['status']);
        expect($response['json']['error'] ?? null)->toBe($missing['json']['error'] ?? null);
        expect($response['json']['message'] ?? null)->toBe($missing['json']['message'] ?? null);
    });

    it('still answers 401 with WWW-Authenticate on an unauthenticated item read', function (): void {
        // The ownership clause is evaluated post-read, so this 401 rests solely
        // on the listener's missing-Bearer check; pin the affordance.
        $response = apiGetRaw('/api/rest/v2/orders/1');

        expect($response['status'])->toBe(401);
        expect($response['headers']['www-authenticate'][0] ?? '')->toContain('Bearer');
    });

    it('returns null (not an Access Denied error) for a foreign order via GraphQL', function (): void {
        $orderId = fixtures('other_customer_order_id');
        if (!$orderId) {
            $this->markTestSkipped('No other_customer_order_id configured in fixtures');
        }

        $foreign = gqlQuery("{ order(id: \"/api/rest/v2/orders/{$orderId}\") { _id } }", [], customerToken());
        $missing = gqlQuery('{ order(id: "/api/rest/v2/orders/99999999") { _id } }', [], customerToken());

        // Foreign row and nonexistent id must be indistinguishable: data.order
        // null in both, and no errors[] entry disclosing "Access Denied".
        foreach ([$foreign, $missing] as $response) {
            expect($response['status'])->toBe(200);
            expect($response['json'])->not->toHaveKey('errors');
            expect($response['json']['data'] ?? [])->toHaveKey('order');
            expect($response['json']['data']['order'])->toBeNull();
        }
    });

});

describe('GET /api/rest/v2/orders/{id} admin fields', function (): void {

    it('lists the actions the order model allows', function (): void {
        $orderId = fixtures('order_id');
        if (!$orderId) {
            $this->markTestSkipped('No order_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/orders/{$orderId}", adminToken());
        expect($response['status'])->toBe(200);

        $order = \Mage::getModel('sales/order')->load($orderId);
        $expected = array_keys(array_filter([
            'invoice' => $order->canInvoice(),
            'ship' => $order->canShip(),
            'creditmemo' => $order->canCreditmemo(),
            'hold' => $order->canHold(),
            'unhold' => $order->canUnhold(),
            'cancel' => $order->canCancel(),
            'comment' => $order->canComment(),
        ]));
        expect($response['json']['availableActions'])->toBe($expected);
    });

    it('lists the statuses a comment may set and the tracking carriers', function (): void {
        $orderId = fixtures('order_id');
        if (!$orderId) {
            $this->markTestSkipped('No order_id configured in fixtures');
        }

        $response = apiGet("/api/rest/v2/orders/{$orderId}", adminToken());
        expect($response['status'])->toBe(200);

        $order = \Mage::getModel('sales/order')->load($orderId);
        $statuses = $order->getConfig()->getStateStatuses($order->getState(), false);
        expect(array_column($response['json']['availableStatuses'], 'code'))->toBe($statuses);

        foreach ($response['json']['trackingCarriers'] as $carrier) {
            expect($carrier)->toHaveKeys(['code', 'title']);
            expect($carrier['code'])->not->toBe('custom');
        }
    });

    it('leaves them out of order lists', function (): void {
        $response = apiGet('/api/rest/v2/orders?itemsPerPage=1', adminToken());
        expect($response['status'])->toBe(200);

        $orders = $response['json']['member'] ?? [];
        if ($orders === []) {
            $this->markTestSkipped('No orders to list');
        }

        expect($orders[0]['availableActions'] ?? [])->toBe([]);
        expect($orders[0]['availableStatuses'] ?? [])->toBe([]);
        expect($orders[0]['trackingCarriers'] ?? [])->toBe([]);
    });
});

describe('GET /api/rest/v2/customers/me/orders', function (): void {

    it('returns array of orders', function (): void {
        $response = apiGet('/api/rest/v2/customers/me/orders', customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toBeArray();
    });

    it('returns one page of the size that the query asks for', function (string $query): void {
        $response = apiGet("/api/rest/v2/customers/me/orders?{$query}", customerToken());

        expect($response['status'])->toBe(200);
        expect(getItems($response))->toHaveCount(1);
    })->with([
        'pageSize' => ['page=1&pageSize=1'],
        'itemsPerPage' => ['page=1&itemsPerPage=1'],
    ]);

    it('supports status filter', function (): void {
        $orderId = fixtures('customer_order_id');
        if (!$orderId) {
            $this->markTestSkipped('No customer_order_id configured in fixtures');
        }
        $status = (string) \Mage::getModel('sales/order')->load($orderId)->getStatus();

        $response = apiGet('/api/rest/v2/customers/me/orders?status=' . urlencode($status), customerToken());

        expect($response['status'])->toBe(200);
        $statuses = array_column(getItems($response), 'status');
        expect($statuses)->not->toBeEmpty();
        expect(array_unique($statuses))->toBe([$status]);
    });

    it('returns orders with expected fields', function (): void {
        $response = apiGet('/api/rest/v2/customers/me/orders', customerToken());

        expect($response['status'])->toBe(200);

        $orders = getItems($response);
        expect($orders)->not->toBeEmpty();
        expect($orders[0])->toHaveKeys(['id', 'incrementId', 'status']);
    });

    it('only returns orders belonging to authenticated customer', function (): void {
        $response = apiGet('/api/rest/v2/customers/me/orders', customerToken());

        expect($response['status'])->toBe(200);

        $orders = getItems($response);
        expect($orders)->not->toBeEmpty();

        $email = fixtures('customer_email');
        foreach ($orders as $order) {
            expect($order)->toHaveKey('customerEmail');
            expect($order['customerEmail'])->toBe($email);
        }
    });

});

describe('GET /api/rest/v2/orders/{id}', function (): void {

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
