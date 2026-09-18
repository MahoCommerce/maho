<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/**
 * Drives the HTTP QUERY collection operation (RFC 10008) through the real API
 * kernel in-process. The PHP built-in server used by the live suite answers 501
 * to the QUERY method before Maho runs, so this is where the body-to-filters
 * bridge is proven end to end.
 */
function productQueryKernel(): Kernel
{
    static $kernel = null;
    if ($kernel === null) {
        Mage::app();
        $kernel = new Kernel('test', true);
        $kernel->boot();
    }
    // The protocol toggle reads the store config; flip it in memory only.
    Mage::app()->getStore()->setConfig('apiplatform/protocols/rest_v2', '1');
    return $kernel;
}

function productQueryRequest(string $method, string $uri, ?array $body = null, string $contentType = 'application/json'): Response
{
    $request = Request::create(
        $uri,
        $method,
        server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => $contentType],
        content: $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR),
    );

    return productQueryKernel()->handle($request);
}

/** @return list<string> */
function productQuerySkus(Response $response): array
{
    $json = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $members = $json['member'] ?? $json['hydra:member'] ?? $json;

    return array_column($members, 'sku');
}

it('returns the same page as the equivalent GET', function (): void {
    $get = productQueryRequest('GET', '/api/rest/v2/products?pageSize=3&sortBy=sku&sortDir=asc');
    $query = productQueryRequest('QUERY', '/api/rest/v2/products', ['pageSize' => 3, 'sortBy' => 'sku', 'sortDir' => 'asc']);

    expect($get->getStatusCode())->toBe(200);
    expect($query->getStatusCode())->toBe(200);
    expect(productQuerySkus($get))->toHaveCount(3);
    expect(productQuerySkus($query))->toBe(productQuerySkus($get));
});

it('applies attributeFilters sent as a JSON object', function (): void {
    $sku = productQuerySkus(productQueryRequest('GET', '/api/rest/v2/products?pageSize=1'))[0];
    $query = productQueryRequest('QUERY', '/api/rest/v2/products', ['attributeFilters' => ['sku' => $sku]]);

    expect($query->getStatusCode())->toBe(200);
    expect(productQuerySkus($query))->toBe([$sku]);
});

it('rejects a body with an unsupported content type', function (): void {
    $response = productQueryRequest('QUERY', '/api/rest/v2/products', ['pageSize' => 1], 'text/plain');

    expect($response->getStatusCode())->toBe(415);
});

it('documents the QUERY verb on the products path', function (): void {
    $docs = productQueryRequest('GET', '/api/docs.json');
    $json = json_decode((string) $docs->getContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($docs->getStatusCode())->toBe(200);
    expect($json['paths']['/api/rest/v2/products'] ?? [])->toHaveKey('query');
});
