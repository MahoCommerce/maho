<?php

declare(strict_types=1);

/**
 * API v2 Product Endpoint Tests
 *
 * Tests GET /api/products endpoints.
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

/**
 * Helper to extract items from API response
 * Handles both Hydra format and plain array format
 */
function getItems(array $response): array
{
    $json = $response['json'] ?? [];
    // API Platform Hydra format
    if (isset($json['member'])) {
        return $json['member'];
    }
    if (isset($json['hydra:member'])) {
        return $json['hydra:member'];
    }
    // Plain array format (when called without proper Accept header)
    if (is_array($json) && (empty($json) || isset($json[0]))) {
        return $json;
    }
    return [];
}

describe('GET /api/products - Basic', function () {

    it('returns a list of products', function () {
        $response = apiGet('/api/products');

        expect($response['status'])->toBe(200);
        expect($response['json'])->toBeArray();
    });

    it('returns products in expected format', function () {
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

    it('returns product images', function () {
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

    it('supports pagination', function () {
        $response = apiGet('/api/products?page=1&pageSize=5');

        expect($response['status'])->toBe(200);

        $items = getItems($response);

        expect(count($items))->toBeLessThanOrEqual(5);
    });

    it('respects pageSize parameter', function () {
        $response = apiGet('/api/products?pageSize=3');

        expect($response['status'])->toBe(200);

        $items = getItems($response);

        // Should return at most 3 items
        expect(count($items))->toBeLessThanOrEqual(3);
        // Should have items (unless DB is empty)
        expect(count($items))->toBeGreaterThan(0);
    });

});

describe('GET /api/products - Sorting by Name', function () {

    it('sorts products by name ascending (A-Z)', function () {
        $response = apiGet('/api/products?pageSize=10&sortBy=name&sortDir=asc');

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        $names = array_column($items, 'name');
        $sortedNames = $names;
        sort($sortedNames, SORT_STRING | SORT_FLAG_CASE);

        expect($names)->toBe($sortedNames);
    });

    it('sorts products by name descending (Z-A)', function () {
        $response = apiGet('/api/products?pageSize=10&sortBy=name&sortDir=desc');

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        $names = array_column($items, 'name');
        $sortedNames = $names;
        rsort($sortedNames, SORT_STRING | SORT_FLAG_CASE);

        expect($names)->toBe($sortedNames);
    });

    it('returns different first product for asc vs desc name sort', function () {
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

describe('GET /api/products - Sorting by Price', function () {

    it('sorts products by price ascending (low to high)', function () {
        // Use priceMin to filter out products with zero/null prices
        $response = apiGet('/api/products?pageSize=10&sortBy=price&sortDir=asc&priceMin=1');

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        // Filter out any products with null or 0 prices for comparison
        $prices = array_filter(
            array_map(fn($p) => (float) ($p['price'] ?? 0), $items),
            fn($p) => $p > 0
        );
        $prices = array_values($prices);

        // Check prices are in ascending order
        for ($i = 1; $i < count($prices); $i++) {
            expect($prices[$i])->toBeGreaterThanOrEqual($prices[$i - 1]);
        }
    });

    it('sorts products by price descending (high to low)', function () {
        $response = apiGet('/api/products?pageSize=10&sortBy=price&sortDir=desc');

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        $prices = array_map(fn($p) => (float) ($p['price'] ?? 0), $items);

        // Check prices are in descending order
        for ($i = 1; $i < count($prices); $i++) {
            expect($prices[$i])->toBeLessThanOrEqual($prices[$i - 1]);
        }
    });

    it('returns different first product for asc vs desc price sort', function () {
        $ascResponse = apiGet('/api/products?pageSize=1&sortBy=price&sortDir=asc');
        $descResponse = apiGet('/api/products?pageSize=1&sortBy=price&sortDir=desc');

        expect($ascResponse['status'])->toBe(200);
        expect($descResponse['status'])->toBe(200);

        $ascItems = getItems($ascResponse);
        $descItems = getItems($descResponse);

        expect($ascItems)->not->toBeEmpty();
        expect($descItems)->not->toBeEmpty();

        // First product's price should be different (lowest vs highest)
        expect($ascItems[0]['price'])->toBeLessThan($descItems[0]['price']);
    });

});

describe('GET /api/products - Category Filtering', function () {

    it('filters products by categoryId', function () {
        // Category 4 = Racquets (parent category)
        $response = apiGet('/api/products?pageSize=10&categoryId=4');

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        // Products should belong to category 4 or its subcategories (5, 6, 7, 8, 45, 287, 289)
        $validCategoryIds = [4, 5, 6, 7, 8, 45, 287, 289];
        foreach ($items as $product) {
            $hasValidCategory = !empty(array_intersect($product['categoryIds'], $validCategoryIds));
            expect($hasValidCategory)->toBeTrue();
        }
    });

    it('returns different products for different categories', function () {
        // Category 4 = Racquets, Category 9 = Tennis String
        $racquetsResponse = apiGet('/api/products?pageSize=5&categoryId=4');
        $stringsResponse = apiGet('/api/products?pageSize=5&categoryId=9');

        expect($racquetsResponse['status'])->toBe(200);
        expect($stringsResponse['status'])->toBe(200);

        $racquetItems = getItems($racquetsResponse);
        $stringItems = getItems($stringsResponse);

        expect($racquetItems)->not->toBeEmpty();
        expect($stringItems)->not->toBeEmpty();

        // Product SKUs should be different
        $racquetSkus = array_column($racquetItems, 'sku');
        $stringSkus = array_column($stringItems, 'sku');

        // No overlap between the two sets
        expect(array_intersect($racquetSkus, $stringSkus))->toBeEmpty();
    });

    it('returns products from subcategory', function () {
        // Category 5 = Adult Tennis Racquets (subcategory of 4)
        $response = apiGet('/api/products?pageSize=5&categoryId=5');

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        // Each product should have categoryId 5 in its categoryIds array
        foreach ($items as $product) {
            expect($product['categoryIds'])->toContain(5);
        }
    });

    it('can combine category filter with sorting', function () {
        // Use priceMin to filter out $0 products for cleaner price sorting
        $response = apiGet('/api/products?pageSize=10&categoryId=4&sortBy=price&sortDir=asc&priceMin=1');

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        // Check category filter is applied (products belong to category 4 or its subcategories)
        $validCategoryIds = [4, 5, 6, 7, 8, 45, 287, 289];
        foreach ($items as $product) {
            $hasValidCategory = !empty(array_intersect($product['categoryIds'], $validCategoryIds));
            expect($hasValidCategory)->toBeTrue();
        }

        // Check price sorting
        $prices = array_filter(
            array_map(fn($p) => (float) ($p['price'] ?? 0), $items),
            fn($p) => $p > 0
        );
        $prices = array_values($prices);
        for ($i = 1; $i < count($prices); $i++) {
            expect($prices[$i])->toBeGreaterThanOrEqual($prices[$i - 1]);
        }
    });

});

describe('GET /api/products/{id}', function () {

    it('returns a single product by ID', function () {
        $productId = fixtures('product_id');

        if (!$productId) {
            $this->markTestSkipped('No product_id configured in fixtures');
        }

        $response = apiGet("/api/products/{$productId}");

        expect($response['status'])->toBe(200);
        expect($response['json'])->toBeArray();
        expect($response['json'])->toHaveKey('sku');
    });

    it('returns product with all expected fields', function () {
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

    it('returns 404 for non-existent product', function () {
        $invalidId = fixtures('invalid_product_id');

        $response = apiGet("/api/products/{$invalidId}");

        expect($response['status'])->toBeNotFound();
    });

});

describe('GET /api/products - Price Filtering', function () {

    it('filters products by minimum price', function () {
        $minPrice = 100;
        $response = apiGet("/api/products?pageSize=10&priceMin={$minPrice}");

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        foreach ($items as $product) {
            expect((float) $product['price'])->toBeGreaterThanOrEqual($minPrice);
        }
    });

    it('filters products by maximum price', function () {
        $maxPrice = 50;
        $response = apiGet("/api/products?pageSize=10&priceMax={$maxPrice}");

        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        foreach ($items as $product) {
            expect((float) $product['price'])->toBeLessThanOrEqual($maxPrice);
        }
    });

    it('filters products by price range', function () {
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
