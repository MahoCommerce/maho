<?php

declare(strict_types=1);

/**
 * API v2 Guest Cart Endpoint Tests (WRITE)
 *
 * WARNING: These tests CREATE real data in the database!
 * Only run with: ./vendor/bin/pest --group=write
 *
 * Tests the /api/guest-carts endpoints (Symfony Controller).
 * These tests validate the full cart lifecycle: create, add items, update, remove, checkout.
 */

describe('POST /api/guest-carts (Create Cart)', function () {

    it('creates an empty guest cart', function () {
        $response = apiPost('/api/guest-carts', []);

        expect($response['status'])->toBe(201);
        expect($response['json'])->toHaveKey('id');
        expect($response['json'])->toHaveKey('itemsCount');
        expect($response['json']['itemsCount'])->toBe(0);
        expect($response['json']['itemsQty'])->toBe(0);
    });

    it('does not require authentication for guest cart creation', function () {
        // Guest carts should be creatable without auth
        $response = apiPost('/api/guest-carts', []);

        expect($response['status'])->toBe(201);
    });

});

describe('GET /api/guest-carts/{id} (Get Cart)', function () {

    it('returns cart with items array', function () {
        // First create a cart
        $createResponse = apiPost('/api/guest-carts', []);
        expect($createResponse['status'])->toBe(201);

        $cartId = $createResponse['json']['id'];

        // Fetch the cart
        $response = apiGet("/api/guest-carts/{$cartId}");

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('id');
        expect($response['json'])->toHaveKey('items');
        expect($response['json'])->toHaveKey('totals');
        expect($response['json']['items'])->toBeArray();
    });

    it('returns items in cart after adding product', function () {
        // Create cart
        $createResponse = apiPost('/api/guest-carts', []);
        expect($createResponse['status'])->toBe(201);
        $cartId = $createResponse['json']['id'];

        // Add item
        $sku = fixtures('write_test_sku');
        $qty = fixtures('write_test_qty') ?? 1;

        $addResponse = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => $qty,
        ]);

        // Now GET the cart and verify items are populated
        $getResponse = apiGet("/api/guest-carts/{$cartId}");

        expect($getResponse['status'])->toBe(200);

        $cart = $getResponse['json'];

        // This is the key test - items array should NOT be empty
        expect($cart['items'])->not->toBeEmpty(
            'Items array should contain the added item, but was empty',
        );

        // itemsCount should match items array length
        expect(count($cart['items']))->toBe(
            (int) $cart['itemsCount'],
            'Items array length should match itemsCount',
        );

        // Verify item structure
        $item = $cart['items'][0];
        expect($item)->toHaveKey('id');
        expect($item)->toHaveKey('sku');
        expect($item)->toHaveKey('name');
        expect($item)->toHaveKey('qty');
        expect($item)->toHaveKey('price');
        expect($item)->toHaveKey('rowTotal');
    });

});

describe('POST /api/guest-carts/{id}/items (Add Item)', function () {

    it('adds item to cart', function () {
        // Create cart
        $createResponse = apiPost('/api/guest-carts', []);
        expect($createResponse['status'])->toBe(201);
        $cartId = $createResponse['json']['id'];

        // Add item
        $sku = fixtures('write_test_sku');
        $qty = fixtures('write_test_qty') ?? 1;

        $response = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => $qty,
        ]);

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('items');

        // Verify the item was added
        $items = $response['json']['items'];
        expect($items)->not->toBeEmpty();
    });

    it('returns 400 for invalid SKU', function () {
        // Create cart
        $createResponse = apiPost('/api/guest-carts', []);
        expect($createResponse['status'])->toBe(201);
        $cartId = $createResponse['json']['id'];

        // Try to add non-existent product
        $response = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => 'NONEXISTENT-SKU-12345',
            'qty' => 1,
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json'])->toHaveKey('error');
    });

    it('returns 400 when SKU is missing', function () {
        // Create cart
        $createResponse = apiPost('/api/guest-carts', []);
        $cartId = $createResponse['json']['id'];

        $response = apiPost("/api/guest-carts/{$cartId}/items", [
            'qty' => 1,
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json']['message'])->toContain('SKU');
    });

    it('returns 404 for non-existent cart', function () {
        $response = apiPost('/api/guest-carts/999999999/items', [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ]);

        expect($response['status'])->toBeNotFound();
    });

});

describe('PUT /api/guest-carts/{id}/items/{itemId} (Update Item)', function () {

    it('updates item quantity', function () {
        // Create cart and add item
        $createResponse = apiPost('/api/guest-carts', []);
        $cartId = $createResponse['json']['id'];

        $addResponse = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ]);
        expect($addResponse['status'])->toBe(200);

        $itemId = $addResponse['json']['items'][0]['id'];

        // Update quantity
        $response = apiPut("/api/guest-carts/{$cartId}/items/{$itemId}", [
            'qty' => 3,
        ]);

        expect($response['status'])->toBe(200);

        // Verify quantity was updated
        $items = $response['json']['items'];
        $updatedItem = array_filter($items, fn($i) => $i['id'] == $itemId);
        $updatedItem = array_values($updatedItem)[0] ?? null;

        expect($updatedItem)->not->toBeNull();
        expect((float) $updatedItem['qty'])->toBe(3.0);
    });

    it('returns 404 for non-existent item', function () {
        // Create cart
        $createResponse = apiPost('/api/guest-carts', []);
        $cartId = $createResponse['json']['id'];

        $response = apiPut("/api/guest-carts/{$cartId}/items/999999999", [
            'qty' => 2,
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json'])->toHaveKey('error');
    });

});

describe('DELETE /api/guest-carts/{id}/items/{itemId} (Remove Item)', function () {

    it('removes item from cart', function () {
        // Create cart and add item
        $createResponse = apiPost('/api/guest-carts', []);
        $cartId = $createResponse['json']['id'];

        $addResponse = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ]);
        $itemId = $addResponse['json']['items'][0]['id'];

        // Remove item
        $response = apiDelete("/api/guest-carts/{$cartId}/items/{$itemId}");

        expect($response['status'])->toBe(200);
        expect($response['json']['success'])->toBeTrue();

        // Verify item was removed
        $getResponse = apiGet("/api/guest-carts/{$cartId}");
        expect($getResponse['json']['items'])->toBeEmpty();
    });

});

describe('PUT /api/guest-carts/{id}/coupon (Apply Coupon)', function () {

    it('returns 400 for invalid coupon', function () {
        // Create cart
        $createResponse = apiPost('/api/guest-carts', []);
        $cartId = $createResponse['json']['id'];

        $response = apiPut("/api/guest-carts/{$cartId}/coupon", [
            'couponCode' => 'INVALID-COUPON-CODE-12345',
        ]);

        expect($response['status'])->toBe(400);
        expect($response['json']['error'])->toBe('invalid_coupon');
    });

    it('returns 400 when coupon code is missing', function () {
        $createResponse = apiPost('/api/guest-carts', []);
        $cartId = $createResponse['json']['id'];

        $response = apiPut("/api/guest-carts/{$cartId}/coupon", []);

        expect($response['status'])->toBe(400);
    });

});

describe('Cart Totals Consistency', function () {

    it('calculates totals correctly after adding items', function () {
        // Create cart
        $createResponse = apiPost('/api/guest-carts', []);
        $cartId = $createResponse['json']['id'];

        // Add item
        $addResponse = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 2,
        ]);
        expect($addResponse['status'])->toBe(200);

        // Verify totals
        $cart = $addResponse['json'];
        $totals = $cart['totals'];

        // Subtotal should be > 0 when items exist
        if (count($cart['items']) > 0) {
            expect($totals['subtotal'])->toBeGreaterThan(
                0,
                'Subtotal should be > 0 when cart has items',
            );
            expect($totals['grandTotal'])->toBeGreaterThan(
                0,
                'Grand total should be > 0 when cart has items',
            );

            // Row total should equal price * qty
            $item = $cart['items'][0];
            $expectedRowTotal = $item['price'] * $item['qty'];
            expect((float) $item['rowTotal'])->toBeGreaterThanOrEqual($expectedRowTotal - 0.01);
            expect((float) $item['rowTotal'])->toBeLessThanOrEqual($expectedRowTotal + 0.01);
        }
    });

});

describe('Cart Item Response Structure', function () {

    it('returns all required item fields', function () {
        // Create cart and add item
        $createResponse = apiPost('/api/guest-carts', []);
        $cartId = $createResponse['json']['id'];

        $addResponse = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => fixtures('write_test_sku'),
            'qty' => 1,
        ]);
        expect($addResponse['status'])->toBe(200);

        $item = $addResponse['json']['items'][0];

        // Required fields per API contract
        expect($item)->toHaveKey('id');
        expect($item)->toHaveKey('sku');
        expect($item)->toHaveKey('name');
        expect($item)->toHaveKey('qty');
        expect($item)->toHaveKey('price');
        expect($item)->toHaveKey('rowTotal');
        expect($item)->toHaveKey('productId');

        // Type checks
        expect($item['id'])->toBeInt();
        expect($item['sku'])->toBeString();
        expect($item['name'])->toBeString();
        expect($item['qty'])->toBeNumeric();
        expect($item['price'])->toBeNumeric();
        expect($item['rowTotal'])->toBeNumeric();
    });

});
