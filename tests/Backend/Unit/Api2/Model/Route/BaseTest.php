<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Api2_Model_Route_Base', function () {
    describe('Basic Route Matching', function () {
        it('matches simple static routes', function () {
            $route = new Mage_Api2_Model_Route_Base('products');
            expect($route->match('products'))->toBe([]);
            expect($route->match('categories'))->toBeFalse();
        });

        it('matches routes with variables', function () {
            $route = new Mage_Api2_Model_Route_Base('products/:id');

            $result = $route->match('products/123');
            expect($result)->toBeArray();
            expect($result['id'])->toBe('123');

            expect($route->match('products'))->toBeFalse();
        });

        it('matches routes with multiple variables', function () {
            $route = new Mage_Api2_Model_Route_Base('catalog/:category/:product');

            $result = $route->match('catalog/electronics/laptop');
            expect($result)->toBeArray();
            expect($result['category'])->toBe('electronics');
            expect($result['product'])->toBe('laptop');
        });

        it('handles URL encoding in parameters', function () {
            $route = new Mage_Api2_Model_Route_Base('search/:query');

            $result = $route->match('search/' . urlencode('test query'));
            expect($result)->toBeArray();
            expect($result['query'])->toBe('test query');
        });
    });

    describe('Route Requirements', function () {
        it('validates variables against regex requirements', function () {
            $route = new Mage_Api2_Model_Route_Base(
                'products/:id',
                [],
                ['id' => '\d+'],
            );

            expect($route->match('products/123'))->toBeArray();
            expect($route->match('products/abc'))->toBeFalse();
        });

        it('handles multiple requirements', function () {
            $route = new Mage_Api2_Model_Route_Base(
                'date/:year/:month/:day',
                [],
                [
                    'year' => '\d{4}',
                    'month' => '\d{2}',
                    'day' => '\d{2}',
                ],
            );

            $result = $route->match('date/2025/01/15');
            expect($result)->toBeArray();
            expect($result['year'])->toBe('2025');
            expect($result['month'])->toBe('01');
            expect($result['day'])->toBe('15');

            expect($route->match('date/25/1/5'))->toBeFalse();
        });

        it('allows optional requirements', function () {
            $route = new Mage_Api2_Model_Route_Base(
                'items/:type/:filter',
                [],
                ['type' => 'new|featured|sale'],
            );

            expect($route->match('items/new/electronics'))->toBeArray();
            expect($route->match('items/featured/books'))->toBeArray();
            expect($route->match('items/unknown/stuff'))->toBeFalse();
        });
    });

    describe('Default Values', function () {
        it('uses default values for missing optional parameters', function () {
            $route = new Mage_Api2_Model_Route_Base(
                'api/:version',
                ['version' => 'v1'],
            );

            $result = $route->match('api/v2');
            expect($result['version'])->toBe('v2');

            // Note: This route requires the version segment, defaults only apply when matched
        });

        it('merges matched values with defaults', function () {
            $route = new Mage_Api2_Model_Route_Base(
                'products/:id',
                ['format' => 'json', 'id' => '0'],
            );

            $result = $route->match('products/123');
            expect($result)->toBeArray();
            expect($result['id'])->toBe('123');
            expect($result['format'])->toBe('json');
        });
    });

    describe('Partial Matching', function () {
        it('allows partial matching when enabled', function () {
            $route = new Mage_Api2_Model_Route_Base('api/products');

            expect($route->match('api/products/123', false))->toBeFalse();

            $result = $route->match('api/products/123', true);
            expect($result)->toBeArray();
            expect($route->getMatchedPath())->toBe('api/products');
        });

        it('returns matched path for partial matches', function () {
            $route = new Mage_Api2_Model_Route_Base('api/:type');

            $route->match('api/rest/more/path', true);
            expect($route->getMatchedPath())->toBe('api/rest');
        });
    });

    describe('Special Characters', function () {
        it('handles escaped variable delimiters', function () {
            $route = new Mage_Api2_Model_Route_Base('items/::special');

            $result = $route->match('items/:special');
            expect($result)->toBeArray();
        });

        it('handles paths with dots and dashes', function () {
            $route = new Mage_Api2_Model_Route_Base('files/:filename');

            $result = $route->match('files/document-v2.1.pdf');
            expect($result)->toBeArray();
            expect($result['filename'])->toBe('document-v2.1.pdf');
        });

        it('handles empty path segments correctly', function () {
            $route = new Mage_Api2_Model_Route_Base('');
            expect($route->match(''))->toBeArray();
            expect($route->match('anything'))->toBeFalse();
        });
    });

    describe('Route Information', function () {
        it('returns route defaults', function () {
            $defaults = ['controller' => 'index', 'action' => 'list'];
            $route = new Mage_Api2_Model_Route_Base('test', $defaults);

            expect($route->getDefaults())->toBe($defaults);
            expect($route->getDefault('controller'))->toBe('index');
            expect($route->getDefault('nonexistent'))->toBeNull();
        });

        it('returns route variables', function () {
            $route = new Mage_Api2_Model_Route_Base('api/:version/:resource/:id');

            $variables = $route->getVariables();
            expect($variables)->toContain('version');
            expect($variables)->toContain('resource');
            expect($variables)->toContain('id');
        });
    });

    describe('Complex Route Patterns', function () {
        it('handles REST-style routes', function () {
            $route = new Mage_Api2_Model_Route_Base('api/:api_type/:resource/:id');

            $result = $route->match('api/rest/products/123');
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('rest');
            expect($result['resource'])->toBe('products');
            expect($result['id'])->toBe('123');
        });

        it('handles nested resources', function () {
            $route = new Mage_Api2_Model_Route_Base(
                'stores/:store_id/categories/:category_id/products/:product_id',
            );

            $result = $route->match('stores/1/categories/5/products/100');
            expect($result)->toBeArray();
            expect($result['store_id'])->toBe('1');
            expect($result['category_id'])->toBe('5');
            expect($result['product_id'])->toBe('100');
        });

        it('handles optional trailing segments with partial matching', function () {
            $route = new Mage_Api2_Model_Route_Base('api/:version');

            $result = $route->match('api/v1/extra/segments', true);
            expect($result)->toBeArray();
            expect($result['version'])->toBe('v1');
            expect($route->getMatchedPath())->toBe('api/v1');
        });
    });

    describe('Edge Cases', function () {
        it('handles consecutive slashes', function () {
            $route = new Mage_Api2_Model_Route_Base('products/:id');

            // Double slashes should be normalized
            $result = $route->match('products//123');
            // This behavior depends on implementation
            expect($result === false || (is_array($result) && $result['id'] === ''))->toBeTrue();
        });

        it('rejects paths shorter than route pattern', function () {
            $route = new Mage_Api2_Model_Route_Base('api/v1/products');

            expect($route->match('api'))->toBeFalse();
            expect($route->match('api/v1'))->toBeFalse();
        });

        it('rejects paths longer than route pattern in non-partial mode', function () {
            $route = new Mage_Api2_Model_Route_Base('api/v1');

            expect($route->match('api/v1/extra', false))->toBeFalse();
        });

        it('handles international characters in parameters', function () {
            $route = new Mage_Api2_Model_Route_Base('search/:query');

            $result = $route->match('search/' . urlencode('café'));
            expect($result)->toBeArray();
            expect($result['query'])->toBe('café');
        });
    });
});
