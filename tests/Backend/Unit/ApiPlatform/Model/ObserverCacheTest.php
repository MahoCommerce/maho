<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('Maho_ApiPlatform_Model_Observer Cache Invalidation', function () {
    beforeEach(function () {
        // Clean API products cache before each test
        Mage::app()->getCache()->clean(['API_PRODUCTS']);
    });

    describe('Cache Tag Cleaning', function () {
        it('cleans cache with API_PRODUCTS tag', function () {
            $cache = Mage::app()->getCache();

            // Store a value with the API_PRODUCTS tag
            $cache->save('test_value', 'api_products_test_key', ['API_PRODUCTS'], 300);

            // Verify it was stored
            expect($cache->load('api_products_test_key'))->toBe('test_value');

            // Clean by tag
            $cache->clean(['API_PRODUCTS']);

            // Verify it was cleaned
            expect($cache->load('api_products_test_key'))->toBeFalse();
        });

        it('does not clean cache with different tags', function () {
            $cache = Mage::app()->getCache();

            // Store values with different tags
            $cache->save('api_value', 'api_test_key', ['API_PRODUCTS'], 300);
            $cache->save('other_value', 'other_test_key', ['OTHER_TAG'], 300);

            // Clean only API_PRODUCTS tag
            $cache->clean(['API_PRODUCTS']);

            // Verify API cache was cleaned but other cache remains
            expect($cache->load('api_test_key'))->toBeFalse();
            expect($cache->load('other_test_key'))->toBe('other_value');

            // Cleanup
            $cache->remove('other_test_key');
        });
    });

    describe('Observer Methods', function () {
        it('invalidates cache on product save', function () {
            $cache = Mage::app()->getCache();
            $observer = new Maho_ApiPlatform_Model_Observer();

            // Store a cached value
            $cache->save('cached_products', 'api_products_category_5', ['API_PRODUCTS'], 300);
            expect($cache->load('api_products_category_5'))->toBe('cached_products');

            // Trigger the observer
            $eventObserver = new Varien_Event_Observer();
            $observer->invalidateProductCache($eventObserver);

            // Verify cache was cleaned
            expect($cache->load('api_products_category_5'))->toBeFalse();
        });

        it('invalidates cache on category save', function () {
            $cache = Mage::app()->getCache();
            $observer = new Maho_ApiPlatform_Model_Observer();

            // Store a cached value
            $cache->save('cached_products', 'api_products_category_10', ['API_PRODUCTS'], 300);
            expect($cache->load('api_products_category_10'))->toBe('cached_products');

            // Trigger the observer
            $eventObserver = new Varien_Event_Observer();
            $observer->invalidateCategoryCache($eventObserver);

            // Verify cache was cleaned
            expect($cache->load('api_products_category_10'))->toBeFalse();
        });

        it('invalidates cache on stock update', function () {
            $cache = Mage::app()->getCache();
            $observer = new Maho_ApiPlatform_Model_Observer();

            // Store a cached value
            $cache->save('cached_products', 'api_products_stock_test', ['API_PRODUCTS'], 300);
            expect($cache->load('api_products_stock_test'))->toBe('cached_products');

            // Trigger the observer
            $eventObserver = new Varien_Event_Observer();
            $observer->invalidateStockCache($eventObserver);

            // Verify cache was cleaned
            expect($cache->load('api_products_stock_test'))->toBeFalse();
        });

        it('invalidates cache on price rule application', function () {
            $cache = Mage::app()->getCache();
            $observer = new Maho_ApiPlatform_Model_Observer();

            // Store a cached value
            $cache->save('cached_products', 'api_products_price_test', ['API_PRODUCTS'], 300);
            expect($cache->load('api_products_price_test'))->toBe('cached_products');

            // Trigger the observer
            $eventObserver = new Varien_Event_Observer();
            $observer->invalidatePriceCache($eventObserver);

            // Verify cache was cleaned
            expect($cache->load('api_products_price_test'))->toBeFalse();
        });
    });

    describe('Multiple Cache Entries', function () {
        it('cleans all entries with API_PRODUCTS tag', function () {
            $cache = Mage::app()->getCache();
            $observer = new Maho_ApiPlatform_Model_Observer();

            // Store multiple cached values (simulating different category/page combinations)
            $cache->save('products_cat_5_page_1', 'api_products_cat5_p1', ['API_PRODUCTS'], 300);
            $cache->save('products_cat_5_page_2', 'api_products_cat5_p2', ['API_PRODUCTS'], 300);
            $cache->save('products_cat_10_page_1', 'api_products_cat10_p1', ['API_PRODUCTS'], 300);
            $cache->save('products_no_cat', 'api_products_all_p1', ['API_PRODUCTS'], 300);

            // Verify all were stored
            expect($cache->load('api_products_cat5_p1'))->toBe('products_cat_5_page_1');
            expect($cache->load('api_products_cat5_p2'))->toBe('products_cat_5_page_2');
            expect($cache->load('api_products_cat10_p1'))->toBe('products_cat_10_page_1');
            expect($cache->load('api_products_all_p1'))->toBe('products_no_cat');

            // Trigger invalidation
            $eventObserver = new Varien_Event_Observer();
            $observer->invalidateProductCache($eventObserver);

            // Verify all were cleaned
            expect($cache->load('api_products_cat5_p1'))->toBeFalse();
            expect($cache->load('api_products_cat5_p2'))->toBeFalse();
            expect($cache->load('api_products_cat10_p1'))->toBeFalse();
            expect($cache->load('api_products_all_p1'))->toBeFalse();
        });
    });
});

describe('ProductProvider Cache Integration', function () {
    it('generates consistent cache keys for same parameters', function () {
        // Use reflection to test the private method
        $provider = new \Maho\ApiPlatform\State\Provider\ProductProvider();
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getCollectionCacheKey');
        $method->setAccessible(true);

        $filters1 = ['page' => '1', 'pageSize' => '12', 'categoryId' => '5'];
        $filters2 = ['page' => '1', 'pageSize' => '12', 'categoryId' => '5'];

        $key1 = $method->invoke($provider, $filters1);
        $key2 = $method->invoke($provider, $filters2);

        expect($key1)->toBe($key2);
    });

    it('generates different cache keys for different parameters', function () {
        $provider = new \Maho\ApiPlatform\State\Provider\ProductProvider();
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getCollectionCacheKey');
        $method->setAccessible(true);

        $filters1 = ['page' => '1', 'pageSize' => '12', 'categoryId' => '5'];
        $filters2 = ['page' => '2', 'pageSize' => '12', 'categoryId' => '5'];
        $filters3 = ['page' => '1', 'pageSize' => '12', 'categoryId' => '10'];

        $key1 = $method->invoke($provider, $filters1);
        $key2 = $method->invoke($provider, $filters2);
        $key3 = $method->invoke($provider, $filters3);

        expect($key1)->not->toBe($key2);
        expect($key1)->not->toBe($key3);
        expect($key2)->not->toBe($key3);
    });

    it('ignores empty filter values in cache key', function () {
        $provider = new \Maho\ApiPlatform\State\Provider\ProductProvider();
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getCollectionCacheKey');
        $method->setAccessible(true);

        $filters1 = ['page' => '1', 'pageSize' => '12', 'search' => ''];
        $filters2 = ['page' => '1', 'pageSize' => '12'];

        $key1 = $method->invoke($provider, $filters1);
        $key2 = $method->invoke($provider, $filters2);

        expect($key1)->toBe($key2);
    });

    it('sorts filter keys for consistent cache keys', function () {
        $provider = new \Maho\ApiPlatform\State\Provider\ProductProvider();
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getCollectionCacheKey');
        $method->setAccessible(true);

        // Same filters, different order
        $filters1 = ['categoryId' => '5', 'page' => '1', 'pageSize' => '12'];
        $filters2 = ['page' => '1', 'categoryId' => '5', 'pageSize' => '12'];

        $key1 = $method->invoke($provider, $filters1);
        $key2 = $method->invoke($provider, $filters2);

        expect($key1)->toBe($key2);
    });
});

describe('ProductProvider DTO Serialization', function () {
    it('converts Product DTO to array correctly', function () {
        $provider = new \Maho\ApiPlatform\State\Provider\ProductProvider();
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('productDtoToArray');
        $method->setAccessible(true);

        $dto = new \Maho\ApiPlatform\ApiResource\Product();
        $dto->id = 123;
        $dto->sku = 'TEST-SKU';
        $dto->name = 'Test Product';
        $dto->price = 99.95;
        $dto->stockStatus = 'in_stock';
        $dto->categoryIds = [5, 10];

        $array = $method->invoke($provider, $dto);

        expect($array)->toBeArray();
        expect($array['id'])->toBe(123);
        expect($array['sku'])->toBe('TEST-SKU');
        expect($array['name'])->toBe('Test Product');
        expect($array['price'])->toBe(99.95);
        expect($array['stockStatus'])->toBe('in_stock');
        expect($array['categoryIds'])->toBe([5, 10]);
    });

    it('reconstructs Product DTO from array correctly', function () {
        $provider = new \Maho\ApiPlatform\State\Provider\ProductProvider();
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('arrayToProductDto');
        $method->setAccessible(true);

        $array = [
            'id' => 456,
            'sku' => 'CACHED-SKU',
            'name' => 'Cached Product',
            'price' => 149.99,
            'finalPrice' => 129.99,
            'stockStatus' => 'out_of_stock',
            'categoryIds' => [15, 20, 25],
            'reviewCount' => 5,
            'averageRating' => 4.5,
        ];

        $dto = $method->invoke($provider, $array);

        expect($dto)->toBeInstanceOf(\Maho\ApiPlatform\ApiResource\Product::class);
        expect($dto->id)->toBe(456);
        expect($dto->sku)->toBe('CACHED-SKU');
        expect($dto->name)->toBe('Cached Product');
        expect($dto->price)->toBe(149.99);
        expect($dto->finalPrice)->toBe(129.99);
        expect($dto->stockStatus)->toBe('out_of_stock');
        expect($dto->categoryIds)->toBe([15, 20, 25]);
        expect($dto->reviewCount)->toBe(5);
        expect($dto->averageRating)->toBe(4.5);
    });

    it('round-trips Product DTO through cache serialization', function () {
        $provider = new \Maho\ApiPlatform\State\Provider\ProductProvider();
        $reflection = new ReflectionClass($provider);
        $toArray = $reflection->getMethod('productDtoToArray');
        $toArray->setAccessible(true);
        $fromArray = $reflection->getMethod('arrayToProductDto');
        $fromArray->setAccessible(true);

        // Create original DTO
        $original = new \Maho\ApiPlatform\ApiResource\Product();
        $original->id = 789;
        $original->sku = 'ROUNDTRIP-SKU';
        $original->name = 'Roundtrip Product';
        $original->description = 'A product description';
        $original->price = 199.99;
        $original->specialPrice = 179.99;
        $original->finalPrice = 179.99;
        $original->stockStatus = 'in_stock';
        $original->stockQty = 50.0;
        $original->categoryIds = [1, 2, 3];
        $original->hasRequiredOptions = true;
        $original->reviewCount = 10;
        $original->averageRating = 4.2;

        // Convert to array (simulating cache save)
        $array = $toArray->invoke($provider, $original);

        // Simulate JSON serialization (what happens in cache)
        $json = json_encode($array);
        $decoded = json_decode($json, true);

        // Convert back to DTO (simulating cache load)
        $restored = $fromArray->invoke($provider, $decoded);

        // Verify all fields match
        expect($restored->id)->toBe($original->id);
        expect($restored->sku)->toBe($original->sku);
        expect($restored->name)->toBe($original->name);
        expect($restored->description)->toBe($original->description);
        expect($restored->price)->toBe($original->price);
        expect($restored->specialPrice)->toBe($original->specialPrice);
        expect($restored->finalPrice)->toBe($original->finalPrice);
        expect($restored->stockStatus)->toBe($original->stockStatus);
        expect($restored->stockQty)->toBe($original->stockQty);
        expect($restored->categoryIds)->toBe($original->categoryIds);
        expect($restored->hasRequiredOptions)->toBe($original->hasRequiredOptions);
        expect($restored->reviewCount)->toBe($original->reviewCount);
        expect($restored->averageRating)->toBe($original->averageRating);
    });
});
