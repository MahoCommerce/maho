<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Query;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\State\QueryBodyFiltersProvider;
use Symfony\Component\HttpFoundation\Request;

uses(Tests\MahoBackendTestCase::class);

/**
 * ReadProvider builds `$context['filters']` from the `_api_filters` request
 * attribute. The bridge must set it from the parsed QUERY body and leave every
 * other request alone.
 */
function queryBodyBridge(Request $request, Operation $operation): ?array
{
    $seen = null;
    $inner = new class ($seen) implements ProviderInterface {
        public function __construct(public ?array &$seen) {}

        #[\Override]
        public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
        {
            $this->seen = $context['request']->attributes->get('_api_filters');
            return null;
        }
    };

    (new QueryBodyFiltersProvider($inner))->provide($operation, [], ['request' => $request]);

    return $inner->seen;
}

it('copies the parsed QUERY body into the filters attribute', function (): void {
    $request = Request::create('/api/rest/v2/products', 'QUERY');
    $request->attributes->set('_api_query_parameters', ['search' => 'shirt', 'page' => 2]);

    expect(queryBodyBridge($request, new Query(uriTemplate: '/products')))
        ->toBe(['search' => 'shirt', 'page' => 2]);
});

it('leaves an empty QUERY body to the query-string fallback', function (): void {
    $request = Request::create('/api/rest/v2/products?search=shirt', 'QUERY');
    $request->attributes->set('_api_query_parameters', []);

    expect(queryBodyBridge($request, new Query(uriTemplate: '/products')))->toBeNull();
});

it('does not touch a GET collection', function (): void {
    $request = Request::create('/api/rest/v2/products', 'GET');
    $request->attributes->set('_api_query_parameters', ['search' => 'shirt']);

    expect(queryBodyBridge($request, new GetCollection(uriTemplate: '/products')))->toBeNull();
});

it('keeps filters another stage already set', function (): void {
    $request = Request::create('/api/rest/v2/products', 'QUERY');
    $request->attributes->set('_api_query_parameters', ['search' => 'shirt']);
    $request->attributes->set('_api_filters', ['search' => 'pinned']);

    expect(queryBodyBridge($request, new Query(uriTemplate: '/products')))->toBe(['search' => 'pinned']);
});
