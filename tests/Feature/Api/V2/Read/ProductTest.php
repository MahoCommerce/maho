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
 * API v2 Product Endpoint Tests
 *
 * Tests GET /api/products endpoints.
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('GET /api/products - Basic', function (): void {

    it('returns a list of products', function (): void {
        $response = apiGet('/api/products');

        expect($response['status'])->toBe(200);
        expect($response['json'])->toBeArray();
    });

    it('returns products in expected format', function (): void {
        $response = apiGet('/api/products?pageSize=5');

        expect($response['status'])->toBe(200);

        $items = getItems($response);

        expect($items)->toBeArray();
        expect($items)->not->toBeEmpty();

        $product = $items[0];
        expect($product)->toHaveKey('sku');
        expect($product)->toHaveKey('name');
        expect($product)->toHaveKey('price');
        expect($product)->toHaveKey('finalPrice');
        expect($product)->toHaveKey('stockStatus');
    });

    it('returns product images', function (): void {
        $response = apiGet('/api/products?pageSize=10');

        expect($response['status'])->toBe(200);

        $items = getItems($response);

        // Find a product with an image
        $productWithImage = null;
        foreach ($items as $p) {
            if (!empty($p['imageUrl'])) {
                $productWithImage = $p;
                break;
            }
        }

        if ($productWithImage) {
            expect($productWithImage['imageUrl'])->toContain('media/catalog/product');
        }
    });

    it('supports pagination', function (): void {
        $response = apiGet('/api/products?page=1&pageSize=5');

        expect($response['status'])->toBe(200);

        $items = getItems($response);

        expect(count($items))->toBeLessThanOrEqual(5);
    });

    it('respects pageSize parameter', function (): void {
        $response = apiGet('/api/products?pageSize=3');

        expect($response['status'])->toBe(200);

        $items = getItems($response);

        // Should return at most 3 items
        expect(count($items))->toBeLessThanOrEqual(3);
        // Should have items (unless DB is empty)
        expect(count($items))->toBeGreaterThan(0);
    });

});

describe('GET /api/products - Sorting by Name', function (): void {

    it('sorts products by name ascending (A-Z)', function (): void {
        $response = apiGet('/api/products?pageSize=10&sortBy=name&sortDir=asc');

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        $names = array_column($items, 'name');
        $sortedNames = $names;
        sort($sortedNames, SORT_STRING | SORT_FLAG_CASE);

        expect($names)->toBe($sortedNames);
    });

    it('sorts products by name descending (Z-A)', function (): void {
        $response = apiGet('/api/products?pageSize=10&sortBy=name&sortDir=desc');

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        $names = array_column($items, 'name');
        $sortedNames = $names;
        rsort($sortedNames, SORT_STRING | SORT_FLAG_CASE);

        expect($names)->toBe($sortedNames);
    });

    it('returns different first product for asc vs desc name sort', function (): void {
        $ascResponse = apiGet('/api/products?pageSize=1&sortBy=name&sortDir=asc');
        $descResponse = apiGet('/api/products?pageSize=1&sortBy=name&sortDir=desc');

        expect($ascResponse['status'])->toBe(200);
        expect($descResponse['status'])->toBe(200);

        $ascItems = getItems($ascResponse);
        $descItems = getItems($descResponse);

        expect($ascItems)->not->toBeEmpty();
        expect($descItems)->not->toBeEmpty();

        // First product should be different
        expect($ascItems[0]['name'])->not->toBe($descItems[0]['name']);
    });

});

describe('GET /api/products - Sorting by Price', function (): void {

    it('sorts products by price ascending (low to high)', function (): void {
        $response = apiGet('/api/products?pageSize=10&sortBy=price&sortDir=asc&priceMin=1');

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        $prices = array_filter(
            array_map(fn($p) => (float) ($p['price'] ?? 0), $items),
            fn($p) => $p > 0,
        );
        $prices = array_values($prices);

        $sorted = $prices;
        sort($sorted);
        expect($prices)->toBe($sorted);
    });

    it('sorts products by price descending (high to low)', function (): void {
        $response = apiGet('/api/products?pageSize=10&sortBy=price&sortDir=desc');

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        $prices = array_map(fn($p) => (float) ($p['price'] ?? 0), $items);

        $sorted = $prices;
        rsort($sorted);
        expect($prices)->toBe($sorted);
    });

    it('returns different first product for asc vs desc price sort', function (): void {
        $ascResponse = apiGet('/api/products?pageSize=1&sortBy=price&sortDir=asc&priceMin=1');
        $descResponse = apiGet('/api/products?pageSize=1&sortBy=price&sortDir=desc&priceMin=1');

        expect($ascResponse['status'])->toBe(200);
        expect($descResponse['status'])->toBe(200);

        $ascItems = getItems($ascResponse);
        $descItems = getItems($descResponse);

        expect($ascItems)->not->toBeEmpty();
        expect($descItems)->not->toBeEmpty();

        expect($ascItems[0]['price'])->toBeLessThan($descItems[0]['price']);
    });

});

describe('GET /api/products - Category Filtering', function (): void {

    it('filters products by categoryId', function (): void {
        // Category 8 = Sale (leaf category — products have 8 in their categoryIds)
        $response = apiGet('/api/products?pageSize=10&categoryId=8');

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        // Products should belong to category 8
        foreach ($items as $product) {
            expect($product['categoryIds'])->toContain(8);
        }
    });

    it('returns different products for different categories', function (): void {
        // Category 8 = Sale, Category 9 = VIP
        $saleResponse = apiGet('/api/products?pageSize=5&categoryId=8');
        $vipResponse = apiGet('/api/products?pageSize=5&categoryId=9');

        expect($saleResponse['status'])->toBe(200);
        expect($vipResponse['status'])->toBe(200);

        $saleItems = getItems($saleResponse);
        $vipItems = getItems($vipResponse);

        expect($saleItems)->not->toBeEmpty();
        expect($vipItems)->not->toBeEmpty();

        // Product SKUs should be different between Sale and VIP
        $saleSkus = array_column($saleItems, 'sku');
        $vipSkus = array_column($vipItems, 'sku');

        expect(array_intersect($saleSkus, $vipSkus))->toBeEmpty();
    });

    it('returns products from parent category including subcategories', function (): void {
        // Category 4 = Women (children: 10=New Arrivals, 11=Tops, 12=Pants, 13=Dresses)
        // The API filter includes descendants, but products' categoryIds contain leaf IDs
        $response = apiGet('/api/products?pageSize=5&categoryId=4');

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        // Products should have at least one of Women's subcategory IDs
        $womenSubcategoryIds = [4, 10, 11, 12, 13];
        foreach ($items as $product) {
            $hasValidCategory = !empty(array_intersect($product['categoryIds'], $womenSubcategoryIds));
            expect($hasValidCategory)->toBeTrue(
                "Product {$product['name']} should belong to Women or its subcategories, has: " . implode(',', $product['categoryIds']),
            );
        }
    });

    it('can combine category filter with price filter', function (): void {
        $response = apiGet('/api/products?pageSize=10&categoryId=8&priceMin=1');

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        // Check category filter is applied
        foreach ($items as $product) {
            expect($product['categoryIds'])->toContain(8);
        }

        // Check price filter is applied
        foreach ($items as $product) {
            expect((float) $product['price'])->toBeGreaterThanOrEqual(1);
        }
    });

});

describe('GET /api/products/{id}', function (): void {

    it('returns a single product by ID', function (): void {
        $productId = fixtures('product_id');

        if (!$productId) {
            $this->markTestSkipped('No product_id configured in fixtures');
        }

        $response = apiGet("/api/products/{$productId}");

        expect($response['status'])->toBe(200);
        expect($response['json'])->toBeArray();
        expect($response['json'])->toHaveKey('sku');
    });

    it('returns product with all expected fields', function (): void {
        $productId = fixtures('product_id');

        if (!$productId) {
            $this->markTestSkipped('No product_id configured in fixtures');
        }

        $response = apiGet("/api/products/{$productId}");

        expect($response['status'])->toBe(200);

        $product = $response['json'];
        expect($product)->toHaveKey('id');
        expect($product)->toHaveKey('sku');
        expect($product)->toHaveKey('name');
        expect($product)->toHaveKey('price');
        expect($product)->toHaveKey('finalPrice');
        expect($product)->toHaveKey('stockStatus');
        expect($product)->toHaveKey('categoryIds');
    });

    it('returns 404 for non-existent product', function (): void {
        $invalidId = fixtures('invalid_product_id');

        $response = apiGet("/api/products/{$invalidId}");

        expect($response['status'])->toBeNotFound();
    });

});

describe('GET /api/products - Price Filtering', function (): void {

    it('filters products by minimum price', function (): void {
        $minPrice = 100;
        $response = apiGet("/api/products?pageSize=10&priceMin={$minPrice}");

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        foreach ($items as $product) {
            expect((float) $product['price'])->toBeGreaterThanOrEqual($minPrice);
        }
    });

    it('filters products by maximum price', function (): void {
        $maxPrice = 50;
        $response = apiGet("/api/products?pageSize=10&priceMax={$maxPrice}");

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        foreach ($items as $product) {
            expect((float) $product['price'])->toBeLessThanOrEqual($maxPrice);
        }
    });

    it('filters products by price range', function (): void {
        $minPrice = 50;
        $maxPrice = 100;
        $response = apiGet("/api/products?pageSize=10&priceMin={$minPrice}&priceMax={$maxPrice}");

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        foreach ($items as $product) {
            $price = (float) $product['price'];
            expect($price)->toBeGreaterThanOrEqual($minPrice);
            expect($price)->toBeLessThanOrEqual($maxPrice);
        }
    });

});
