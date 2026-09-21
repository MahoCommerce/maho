<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Product QUERY Endpoint Tests
 *
 * Tests QUERY /api/rest/v2/products (RFC 10008): the same collection as GET,
 * with the filters carried in the request body. All tests are READ-ONLY.
 *
 * @group read
 */

describe('QUERY /api/rest/v2/products', function (): void {

    beforeEach(function (): void {
        // The PHP built-in server (CI and local dev) answers 501 to the QUERY method
        // before Maho runs. Apache, nginx and FrankenPHP pass it through. The in-process
        // ProductQueryOperationTest covers the operation where the server cannot.
        if (apiQuery('/api/rest/v2/products', ['pageSize' => 1])['status'] === 501) {
            $this->markTestSkipped('The web server does not route the HTTP QUERY method');
        }
    });

    it('returns the same page as the equivalent GET', function (): void {
        $get = apiGet('/api/rest/v2/products?pageSize=5&page=1&sortBy=sku&sortDir=asc');
        $query = apiQuery('/api/rest/v2/products', ['pageSize' => 5, 'page' => 1, 'sortBy' => 'sku', 'sortDir' => 'asc']);

        expect($get['status'])->toBe(200);
        expect($query['status'])->toBe(200);
        expect(array_column(getItems($query), 'sku'))->toBe(array_column(getItems($get), 'sku'));
    });

    it('filters by a search term carried in the body', function (): void {
        $get = apiGet('/api/rest/v2/products?search=shirt&pageSize=10');
        $query = apiQuery('/api/rest/v2/products', ['search' => 'shirt', 'pageSize' => 10]);

        expect($query['status'])->toBe(200);
        expect(array_column(getItems($query), 'id'))->toBe(array_column(getItems($get), 'id'));
    });

    it('accepts attributeFilters as a JSON object', function (): void {
        $sku = getItems(apiGet('/api/rest/v2/products?pageSize=1'))[0]['sku'] ?? null;
        expect($sku)->not->toBeNull();

        $get = apiGet('/api/rest/v2/products?attr_sku=' . rawurlencode((string) $sku));
        $query = apiQuery('/api/rest/v2/products', ['attributeFilters' => ['sku' => $sku]]);

        expect($query['status'])->toBe(200);
        expect(array_column(getItems($query), 'sku'))->toBe([$sku]);
        expect(array_column(getItems($query), 'id'))->toBe(array_column(getItems($get), 'id'));
    });

    it('rejects a body with an unsupported content type', function (): void {
        $response = apiQuery('/api/rest/v2/products', ['search' => 'shirt'], null, ['Content-Type' => 'text/plain']);

        expect($response['status'])->toBe(415);
    });

    it('is documented in the OpenAPI document', function (): void {
        $docs = apiGet('/api/docs.json');

        expect($docs['status'])->toBe(200);
        expect($docs['json']['paths']['/api/rest/v2/products'] ?? [])->toHaveKey('query');
    });
});
