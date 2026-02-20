<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Api2_Model_Route_Chain', function () {
    describe('Basic Route Chaining', function () {
        it('chains two simple routes', function () {
            $route1 = new Mage_Api2_Model_Route_Base('api/:api_type');
            $route2 = new Mage_Api2_Model_Route_Base('products/:id');

            $chain = new Mage_Api2_Model_Route_Chain($route1, '/', $route2);

            $result = $chain->match('api/rest/products/123');
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('rest');
            expect($result['id'])->toBe('123');
        });

        it('merges parameters from both routes', function () {
            $route1 = new Mage_Api2_Model_Route_Base('store/:store_code', ['locale' => 'en']);
            $route2 = new Mage_Api2_Model_Route_Base('category/:category_id', ['format' => 'json']);

            $chain = new Mage_Api2_Model_Route_Chain($route1, '/', $route2);

            $result = $chain->match('store/us/category/5');
            expect($result)->toBeArray();
            expect($result['store_code'])->toBe('us');
            expect($result['category_id'])->toBe('5');
            expect($result['locale'])->toBe('en');
            expect($result['format'])->toBe('json');
        });

        it('fails when first route does not match', function () {
            $route1 = new Mage_Api2_Model_Route_Base('api/v1');
            $route2 = new Mage_Api2_Model_Route_Base('products');

            $chain = new Mage_Api2_Model_Route_Chain($route1, '/', $route2);

            expect($chain->match('api/v2/products'))->toBeFalse();
        });

        it('fails when second route does not match', function () {
            $route1 = new Mage_Api2_Model_Route_Base('api');
            $route2 = new Mage_Api2_Model_Route_Base('products');

            $chain = new Mage_Api2_Model_Route_Chain($route1, '/', $route2);

            expect($chain->match('api/categories'))->toBeFalse();
        });
    });

    describe('Route Chaining with Variables', function () {
        it('chains routes with overlapping variable names', function () {
            $route1 = new Mage_Api2_Model_Route_Base('v/:version', ['id' => 'default1']);
            $route2 = new Mage_Api2_Model_Route_Base('items/:id', ['id' => 'default2']);

            $chain = new Mage_Api2_Model_Route_Chain($route1, '/', $route2);

            $result = $chain->match('v/1/items/123');
            expect($result)->toBeArray();
            expect($result['version'])->toBe('1');
            expect($result['id'])->toBe('123'); // Second route should override
        });

        it('chains routes with requirements', function () {
            $route1 = new Mage_Api2_Model_Route_Base(
                'year/:year',
                [],
                ['year' => '\d{4}'],
            );
            $route2 = new Mage_Api2_Model_Route_Base(
                'month/:month',
                [],
                ['month' => '\d{2}'],
            );

            $chain = new Mage_Api2_Model_Route_Chain($route1, '/', $route2);

            $result = $chain->match('year/2025/month/01');
            expect($result)->toBeArray();
            expect($result['year'])->toBe('2025');
            expect($result['month'])->toBe('01');

            expect($chain->match('year/25/month/01'))->toBeFalse();
            expect($chain->match('year/2025/month/1'))->toBeFalse();
        });
    });

    describe('Custom Separators', function () {
        it('chains routes with custom separator', function () {
            $route1 = new Mage_Api2_Model_Route_Base('api');
            $route2 = new Mage_Api2_Model_Route_Base('products');

            $chain = new Mage_Api2_Model_Route_Chain($route1, '-', $route2);

            $result = $chain->match('api-products');
            expect($result)->toBeArray();

            expect($chain->match('api/products'))->toBeFalse();
        });

        it('handles empty separator', function () {
            $route1 = new Mage_Api2_Model_Route_Base('api');
            $route2 = new Mage_Api2_Model_Route_Base('v1');

            $chain = new Mage_Api2_Model_Route_Chain($route1, '', $route2);

            $result = $chain->match('apiv1');
            expect($result)->toBeArray();
        });

        it('handles multi-character separator', function () {
            $route1 = new Mage_Api2_Model_Route_Base('module');
            $route2 = new Mage_Api2_Model_Route_Base('action');

            $chain = new Mage_Api2_Model_Route_Chain($route1, '::', $route2);

            $result = $chain->match('module::action');
            expect($result)->toBeArray();
        });
    });

    describe('Partial Matching', function () {
        it('supports partial matching on chained routes', function () {
            $route1 = new Mage_Api2_Model_Route_Base('api/:version');
            $route2 = new Mage_Api2_Model_Route_Base('products/:id');

            $chain = new Mage_Api2_Model_Route_Chain($route1, '/', $route2);

            $result = $chain->match('api/v1/products/123/extra/segments', true);
            expect($result)->toBeArray();
            expect($result['version'])->toBe('v1');
            expect($result['id'])->toBe('123');
            expect($chain->getMatchedPath())->toBe('api/v1/products/123');
        });

        it('returns matched path for partial matches', function () {
            $route1 = new Mage_Api2_Model_Route_Base('store/:code');
            $route2 = new Mage_Api2_Model_Route_Base('catalog');

            $chain = new Mage_Api2_Model_Route_Chain($route1, '/', $route2);

            $result = $chain->match('store/us/catalog/products/123', true);
            expect($result)->toBeArray();
            expect($chain->getMatchedPath())->toBe('store/us/catalog');
        });
    });

    describe('Complex Chaining Scenarios', function () {
        it('chains multiple levels of routes', function () {
            // Create a chain of chains
            $route1 = new Mage_Api2_Model_Route_Base('api');
            $route2 = new Mage_Api2_Model_Route_Base(':version');
            $chain1 = new Mage_Api2_Model_Route_Chain($route1, '/', $route2);

            $route3 = new Mage_Api2_Model_Route_Base('products/:id');
            $finalChain = new Mage_Api2_Model_Route_Chain($chain1, '/', $route3);

            $result = $finalChain->match('api/v1/products/123');
            expect($result)->toBeArray();
            expect($result['version'])->toBe('v1');
            expect($result['id'])->toBe('123');
        });

        it('handles API versioning patterns', function () {
            $apiRoute = new Mage_Api2_Model_Route_Base('api/:api_type');
            $resourceRoute = new Mage_Api2_Model_Route_Base(':resource/:id');

            $chain = new Mage_Api2_Model_Route_Chain($apiRoute, '/', $resourceRoute);

            $result = $chain->match('api/rest/customers/42');
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('rest');
            expect($result['resource'])->toBe('customers');
            expect($result['id'])->toBe('42');
        });

        it('handles store and locale patterns', function () {
            $storeRoute = new Mage_Api2_Model_Route_Base(
                'stores/:store_code',
                ['store_code' => 'default'],
            );
            $localeRoute = new Mage_Api2_Model_Route_Base(
                'locale/:locale',
                ['locale' => 'en_US'],
            );

            $chain = new Mage_Api2_Model_Route_Chain($storeRoute, '/', $localeRoute);

            $result = $chain->match('stores/german/locale/de_DE');
            expect($result)->toBeArray();
            expect($result['store_code'])->toBe('german');
            expect($result['locale'])->toBe('de_DE');
        });
    });

    describe('Chain Information Methods', function () {
        it('returns combined defaults from both routes', function () {
            $route1 = new Mage_Api2_Model_Route_Base('path1', ['key1' => 'value1']);
            $route2 = new Mage_Api2_Model_Route_Base('path2', ['key2' => 'value2']);

            $chain = new Mage_Api2_Model_Route_Chain($route1, '/', $route2);

            $defaults = $chain->getDefaults();
            expect($defaults)->toHaveKey('key1');
            expect($defaults)->toHaveKey('key2');
            expect($defaults['key1'])->toBe('value1');
            expect($defaults['key2'])->toBe('value2');
        });

        it('retrieves specific default values', function () {
            $route1 = new Mage_Api2_Model_Route_Base('test', ['controller' => 'index']);
            $route2 = new Mage_Api2_Model_Route_Base('action', ['action' => 'list']);

            $chain = new Mage_Api2_Model_Route_Chain($route1, '/', $route2);

            expect($chain->getDefault('controller'))->toBe('index');
            expect($chain->getDefault('action'))->toBe('list');
            expect($chain->getDefault('nonexistent'))->toBeNull();
        });
    });

    describe('Edge Cases', function () {
        it('handles empty first route', function () {
            $route1 = new Mage_Api2_Model_Route_Base('');
            $route2 = new Mage_Api2_Model_Route_Base('products');

            $chain = new Mage_Api2_Model_Route_Chain($route1, '/', $route2);

            $result = $chain->match('products');
            expect($result)->toBeArray();
        });

        it('handles empty second route', function () {
            $route1 = new Mage_Api2_Model_Route_Base('api');
            $route2 = new Mage_Api2_Model_Route_Base('');

            $chain = new Mage_Api2_Model_Route_Chain($route1, '/', $route2);

            $result = $chain->match('api/');
            expect($result)->toBeArray();
        });

        it('handles both routes being empty', function () {
            $route1 = new Mage_Api2_Model_Route_Base('');
            $route2 = new Mage_Api2_Model_Route_Base('');

            $chain = new Mage_Api2_Model_Route_Chain($route1, '/', $route2);

            $result = $chain->match('/');
            expect($result)->toBeArray();
        });

        it('preserves matched path through chain', function () {
            $route1 = new Mage_Api2_Model_Route_Base('start/:var1');
            $route2 = new Mage_Api2_Model_Route_Base('end/:var2');

            $chain = new Mage_Api2_Model_Route_Chain($route1, '-', $route2);

            $chain->match('start/test1-end/test2/extra', true);
            expect($chain->getMatchedPath())->toBe('start/test1-end/test2');
        });
    });
});
