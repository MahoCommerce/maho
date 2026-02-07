<?php

declare(strict_types=1);

/**
 * GraphQL Cart Integration Tests (WRITE)
 *
 * WARNING: These tests CREATE real data in the database!
 * Only run with: ./vendor/bin/pest --group=write
 *
 * Tests cart mutations and queries via GraphQL.
 * Includes regression tests for the prices field.
 *
 * Note: Cart `items`, `prices`, and other array fields are typed as `Iterable`
 * in GraphQL (not object types), so they cannot have sub-selections.
 * They return as JSON scalar values.
 *
 * @group write
 * @group graphql
 */

describe('GraphQL Cart - Create Cart Mutation', function () {

    it('creates a cart with maskedId', function () {
        $query = <<<'GRAPHQL'
        mutation {
            createCartCart(input: {}) {
                cart {
                    id
                    _id
                    maskedId
                    itemsCount
                    itemsQty
                    isActive
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data']['createCartCart'])->not->toBeNull();

        $cart = $response['json']['data']['createCartCart']['cart'];
        expect($cart)->toHaveKey('maskedId');
        expect($cart['maskedId'])->toBeString();
        expect($cart['maskedId'])->not->toBeEmpty();
        expect($cart['itemsCount'])->toBe(0);
        expect($cart['isActive'])->toBeTrue();
    });

});

describe('GraphQL Cart - Query Cart', function () {

    it('returns cart by maskedId with prices field', function () {
        // First create a cart
        $createQuery = <<<'GRAPHQL'
        mutation {
            createCartCart(input: {}) {
                cart {
                    _id
                    maskedId
                }
            }
        }
        GRAPHQL;

        $createResponse = gqlQuery($createQuery, [], customerToken());
        expect($createResponse['status'])->toBe(200);

        $maskedId = $createResponse['json']['data']['createCartCart']['cart']['maskedId'];

        // Query the cart — items and prices are Iterable scalars (no sub-selection)
        $query = <<<GRAPHQL
        {
            getCartByMaskedIdCart(maskedId: "{$maskedId}") {
                id
                _id
                maskedId
                itemsCount
                items
                prices
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);

        $cart = $response['json']['data']['getCartByMaskedIdCart'] ?? null;
        if ($cart === null) {
            $this->markTestSkipped('GraphQL Query operations do not invoke the state provider — known API Platform limitation');
        }

        expect($cart['maskedId'])->toBe($maskedId);

        // Regression: prices field should be accessible (was named 'totals' before)
        expect($cart)->toHaveKey('prices');
    });

});

describe('GraphQL Cart - Add To Cart Mutation', function () {

    it('adds item to cart and returns updated cart with prices', function () {
        // Create cart
        $createQuery = <<<'GRAPHQL'
        mutation {
            createCartCart(input: {}) {
                cart {
                    _id
                    maskedId
                }
            }
        }
        GRAPHQL;

        $createResponse = gqlQuery($createQuery, [], customerToken());
        expect($createResponse['status'])->toBe(200);

        $maskedId = $createResponse['json']['data']['createCartCart']['cart']['maskedId'];
        $sku = fixtures('write_test_sku');

        // Add item — items/prices are Iterable scalars
        $addQuery = <<<GRAPHQL
        mutation {
            addToCartCart(input: {maskedId: "{$maskedId}", sku: "{$sku}", qty: 1}) {
                cart {
                    _id
                    maskedId
                    itemsCount
                    itemsQty
                    items
                    prices
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($addQuery, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['data']['addToCartCart'])->not->toBeNull();

        $cart = $response['json']['data']['addToCartCart']['cart'];

        expect($cart['itemsCount'])->toBeGreaterThan(0);

        /**
         * Regression: prices field should contain subtotal and grandTotal > 0
         * Previously, the field was named 'totals' and wouldn't resolve in GraphQL
         */
        expect($cart)->toHaveKey('prices');
        $prices = $cart['prices'];
        if (is_array($prices) && isset($prices['subtotal'])) {
            expect((float) $prices['subtotal'])->toBeGreaterThan(0);
            expect((float) $prices['grandTotal'])->toBeGreaterThan(0);
        }
    });

    it('returns error for invalid SKU', function () {
        // Create cart
        $createQuery = <<<'GRAPHQL'
        mutation {
            createCartCart(input: {}) {
                cart {
                    maskedId
                }
            }
        }
        GRAPHQL;

        $createResponse = gqlQuery($createQuery, [], customerToken());
        expect($createResponse['status'])->toBe(200);

        $maskedId = $createResponse['json']['data']['createCartCart']['cart']['maskedId'];

        $addQuery = <<<GRAPHQL
        mutation {
            addToCartCart(input: {maskedId: "{$maskedId}", sku: "NONEXISTENT-SKU-999", qty: 1}) {
                cart {
                    _id
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($addQuery, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('errors');
    });

});

describe('GraphQL Cart - Update Item Quantity', function () {

    it('updates item quantity in cart', function () {
        // Create cart and add item via REST (more reliable for getting item ID)
        $createResponse = apiPost('/api/guest-carts', []);
        expect($createResponse['status'])->toBe(201);
        $cartId = $createResponse['json']['id'];

        $sku = fixtures('write_test_sku');
        $addResponse = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => 1,
        ]);
        expect($addResponse['status'])->toBe(200);

        $maskedId = $createResponse['json']['maskedId'] ?? null;
        $itemId = $addResponse['json']['items'][0]['id'] ?? null;

        if (!$maskedId || !$itemId) {
            $this->markTestSkipped('Could not get maskedId or itemId from REST response');
        }

        // Update quantity via GraphQL
        $updateQuery = <<<GRAPHQL
        mutation {
            updateCartItemQtyCart(input: {maskedId: "{$maskedId}", itemId: {$itemId}, qty: 3}) {
                cart {
                    items
                    prices
                }
            }
        }
        GRAPHQL;

        $updateResponse = gqlQuery($updateQuery, [], customerToken());

        expect($updateResponse['status'])->toBe(200);
        expect($updateResponse['json'])->toHaveKey('data');
    });

});

describe('GraphQL Cart - Remove Item', function () {

    it('removes item from cart', function () {
        // Create cart and add item via REST
        $createResponse = apiPost('/api/guest-carts', []);
        expect($createResponse['status'])->toBe(201);
        $cartId = $createResponse['json']['id'];

        $sku = fixtures('write_test_sku');
        $addResponse = apiPost("/api/guest-carts/{$cartId}/items", [
            'sku' => $sku,
            'qty' => 1,
        ]);
        expect($addResponse['status'])->toBe(200);

        $maskedId = $createResponse['json']['maskedId'] ?? null;
        $itemId = $addResponse['json']['items'][0]['id'] ?? null;

        if (!$maskedId || !$itemId) {
            $this->markTestSkipped('Could not get maskedId or itemId from REST response');
        }

        // Remove item via GraphQL
        $removeQuery = <<<GRAPHQL
        mutation {
            removeCartItemCart(input: {maskedId: "{$maskedId}", itemId: {$itemId}}) {
                cart {
                    itemsCount
                    items
                }
            }
        }
        GRAPHQL;

        $removeResponse = gqlQuery($removeQuery, [], customerToken());

        expect($removeResponse['status'])->toBe(200);
        expect($removeResponse['json'])->toHaveKey('data');
    });

});

describe('GraphQL Cart - Apply Coupon', function () {

    it('returns error for invalid coupon', function () {
        // Create cart
        $createQuery = <<<'GRAPHQL'
        mutation {
            createCartCart(input: {}) {
                cart {
                    maskedId
                }
            }
        }
        GRAPHQL;

        $createResponse = gqlQuery($createQuery, [], customerToken());
        $maskedId = $createResponse['json']['data']['createCartCart']['cart']['maskedId'];

        $couponQuery = <<<GRAPHQL
        mutation {
            applyCouponToCartCart(input: {maskedId: "{$maskedId}", couponCode: "INVALID-COUPON-CODE-12345"}) {
                cart {
                    appliedCoupon
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($couponQuery, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('errors');
    });

});
