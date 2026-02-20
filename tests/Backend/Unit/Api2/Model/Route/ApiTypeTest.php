<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

describe('Mage_Api2_Model_Route_ApiType', function () {
    describe('Basic API Type Routing', function () {
        it('matches api_type from path', function () {
            $route = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [],
            ]);

            // Create a mock Request object
            $symfonyRequest = SymfonyRequest::create('/api/rest');
            $request = new Mage_Api2_Model_Request($symfonyRequest);

            $result = $route->match($request);
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('rest');
        });

        it('matches different api types', function () {
            $route = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [],
            ]);

            $symfonyRequest = SymfonyRequest::create('/api/soap');
            $request = new Mage_Api2_Model_Request($symfonyRequest);

            $result = $route->match($request);
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('soap');
        });

        it('fails on invalid api type', function () {
            $route = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [],
            ]);

            $symfonyRequest = SymfonyRequest::create('/api/invalid_type');
            $request = new Mage_Api2_Model_Request($symfonyRequest);

            // Should still match the path, but validation happens elsewhere
            $result = $route->match($request);
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('invalid_type');
        });
    });

    describe('Query Parameter Fallback', function () {
        it('uses type query parameter as fallback', function () {
            $route = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [],
            ]);

            // Path doesn't match, but query param exists
            $symfonyRequest = SymfonyRequest::create('/some/path?type=rest');
            $request = new Mage_Api2_Model_Request($symfonyRequest);

            $result = $route->match($request);
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('rest');
        });

        it('prefers path over query parameter', function () {
            $route = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [],
            ]);

            // Both path and query param present
            $symfonyRequest = SymfonyRequest::create('/api/rest?type=soap');
            $request = new Mage_Api2_Model_Request($symfonyRequest);

            $result = $route->match($request);
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('rest'); // Path wins
        });

        it('validates query parameter api type', function () {
            $route = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [],
            ]);

            // Invalid type in query param
            $symfonyRequest = SymfonyRequest::create('/some/path?type=invalid');
            $request = new Mage_Api2_Model_Request($symfonyRequest);

            $result = $route->match($request);
            expect($result)->toBeFalse();
        });

        it('returns false when no match and no valid query param', function () {
            $route = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [],
            ]);

            $symfonyRequest = SymfonyRequest::create('/other/path');
            $request = new Mage_Api2_Model_Request($symfonyRequest);

            expect($route->match($request))->toBeFalse();
        });
    });

    describe('Route Chaining with ApiType', function () {
        it('chains with resource route', function () {
            $apiTypeRoute = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [],
            ]);

            $resourceRoute = new Mage_Api2_Model_Route_Base('products/:id');

            $chain = $apiTypeRoute->chain($resourceRoute);

            $symfonyRequest = SymfonyRequest::create('/api/rest/products/123');
            $request = new Mage_Api2_Model_Request($symfonyRequest);

            $result = $chain->match($request);
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('rest');
            expect($result['id'])->toBe('123');
        });

        it('chains with string route pattern', function () {
            $apiTypeRoute = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [],
            ]);

            // Passing a string route pattern
            $chain = $apiTypeRoute->chain('customers/:customer_id');

            $symfonyRequest = SymfonyRequest::create('/api/rest/customers/42');
            $request = new Mage_Api2_Model_Request($symfonyRequest);

            $result = $chain->match($request);
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('rest');
            expect($result['customer_id'])->toBe('42');
        });

        it('chains multiple routes', function () {
            $apiTypeRoute = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [],
            ]);

            $versionRoute = new Mage_Api2_Model_Route_Base('v/:version');
            $resourceRoute = new Mage_Api2_Model_Route_Base(':resource/:id');

            $chain = $apiTypeRoute->chain($versionRoute)->chain($resourceRoute);

            $symfonyRequest = SymfonyRequest::create('/api/rest/v/2/products/456');
            $request = new Mage_Api2_Model_Request($symfonyRequest);

            $result = $chain->match($request);
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('rest');
            expect($result['version'])->toBe('2');
            expect($result['resource'])->toBe('products');
            expect($result['id'])->toBe('456');
        });
    });

    describe('Partial Matching', function () {
        it('supports partial matching', function () {
            $route = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [],
            ]);

            $symfonyRequest = SymfonyRequest::create('/api/rest/extra/segments');
            $request = new Mage_Api2_Model_Request($symfonyRequest);

            $result = $route->match($request, true);
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('rest');
            expect($route->getMatchedPath())->toBe('api/rest');
        });
    });

    describe('Default Values', function () {
        it('applies default values', function () {
            $route = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [
                    'format' => 'json',
                    'version' => 'v1',
                ],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [],
            ]);

            $symfonyRequest = SymfonyRequest::create('/api/rest');
            $request = new Mage_Api2_Model_Request($symfonyRequest);

            $result = $route->match($request);
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('rest');
            expect($result['format'])->toBe('json');
            expect($result['version'])->toBe('v1');
        });

        it('merges defaults with query param fallback', function () {
            $route = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [
                    'format' => 'xml',
                ],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [],
            ]);

            $symfonyRequest = SymfonyRequest::create('/other/path?type=rest');
            $request = new Mage_Api2_Model_Request($symfonyRequest);

            $result = $route->match($request);
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('rest');
            expect($result['format'])->toBe('xml');
        });
    });

    describe('Requirements Validation', function () {
        it('validates api_type against requirements', function () {
            $route = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [
                    'api_type' => 'rest|soap', // Only allow rest or soap
                ],
            ]);

            $symfonyRequest1 = SymfonyRequest::create('/api/rest');
            $request1 = new Mage_Api2_Model_Request($symfonyRequest1);
            expect($route->match($request1))->toBeArray();

            $symfonyRequest2 = SymfonyRequest::create('/api/soap');
            $request2 = new Mage_Api2_Model_Request($symfonyRequest2);
            expect($route->match($request2))->toBeArray();

            $symfonyRequest3 = SymfonyRequest::create('/api/graphql');
            $request3 = new Mage_Api2_Model_Request($symfonyRequest3);
            expect($route->match($request3))->toBeFalse();
        });
    });

    describe('Complex Scenarios', function () {
        it('handles complete API endpoint routing', function () {
            $route = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [
                    'module' => 'api',
                    'controller' => 'index',
                ],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [],
            ]);

            $resourceRoute = new Mage_Api2_Model_Route_Base(
                ':resource/:id',
                ['action' => 'view'],
            );

            $chain = $route->chain($resourceRoute);

            $symfonyRequest = SymfonyRequest::create('/api/rest/orders/789');
            $request = new Mage_Api2_Model_Request($symfonyRequest);

            $result = $chain->match($request);
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('rest');
            expect($result['resource'])->toBe('orders');
            expect($result['id'])->toBe('789');
            expect($result['module'])->toBe('api');
            expect($result['controller'])->toBe('index');
            expect($result['action'])->toBe('view');
        });

        it('handles subresources', function () {
            $apiRoute = new Mage_Api2_Model_Route_ApiType([
                Mage_Api2_Model_Route_Abstract::PARAM_ROUTE => 'api/:api_type',
                Mage_Api2_Model_Route_Abstract::PARAM_DEFAULTS => [],
                Mage_Api2_Model_Route_Abstract::PARAM_REQS => [],
            ]);

            $subresourceRoute = new Mage_Api2_Model_Route_Base(
                'customers/:customer_id/addresses/:address_id',
            );

            $chain = $apiRoute->chain($subresourceRoute);

            $symfonyRequest = SymfonyRequest::create('/api/rest/customers/100/addresses/5');
            $request = new Mage_Api2_Model_Request($symfonyRequest);

            $result = $chain->match($request);
            expect($result)->toBeArray();
            expect($result['api_type'])->toBe('rest');
            expect($result['customer_id'])->toBe('100');
            expect($result['address_id'])->toBe('5');
        });
    });
});
