<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Product Attribute / Attribute Set metadata Tests (READ)
 *
 * Covers /product-attributes and /attribute-sets, scoped to catalog_product.
 * Attribute metadata is admin-or-grant only.
 *
 * @group read
 */

describe('GET /api/rest/v2/product-attributes', function (): void {

    it('rejects anonymous access', function (): void {
        expect(apiGet('/api/rest/v2/product-attributes')['status'])->toBeUnauthorized();
    });

    it('lists product attributes for an admin', function (): void {
        $response = apiGet('/api/rest/v2/product-attributes', adminToken());

        expect($response['status'])->toBe(200);
        $items = getItems($response);
        expect($items)->toBeArray()->not->toBeEmpty();
        expect($items[0])->toHaveKey('attributeCode');
        expect($items[0])->toHaveKey('frontendInput');
    });

    it('returns a single attribute with metadata fields', function (): void {
        $list = apiGet('/api/rest/v2/product-attributes', adminToken());
        $first = getItems($list)[0] ?? null;
        expect($first)->not->toBeNull();

        $response = apiGet('/api/rest/v2/product-attributes/' . (int) $first['id'], adminToken());

        expect($response['status'])->toBe(200);
        foreach (['attributeCode', 'frontendInput', 'backendType', 'isRequired', 'scope'] as $key) {
            expect($response['json'])->toHaveKey($key);
        }
    });

    it('returns 404 for a non-existent attribute', function (): void {
        expect(apiGet('/api/rest/v2/product-attributes/999999', adminToken())['status'])->toBeNotFound();
    });

});

describe('GET /api/rest/v2/attribute-sets', function (): void {

    it('rejects anonymous access', function (): void {
        expect(apiGet('/api/rest/v2/attribute-sets')['status'])->toBeUnauthorized();
    });

    it('lists product attribute sets and exposes attribute codes', function (): void {
        $response = apiGet('/api/rest/v2/attribute-sets', adminToken());

        expect($response['status'])->toBe(200);
        $items = getItems($response);
        expect($items)->toBeArray()->not->toBeEmpty();
        expect($items[0])->toHaveKey('attributeSetName');

        $detail = apiGet('/api/rest/v2/attribute-sets/' . (int) $items[0]['id'], adminToken());
        expect($detail['status'])->toBe(200);
        expect($detail['json'])->toHaveKey('attributeCodes');
        expect($detail['json']['attributeCodes'])->toBeArray();
    });

});

describe('GraphQL product attribute metadata', function (): void {

    it('resolves an attribute by code', function (): void {
        // `name` is a core product attribute present in every install.
        // ApiPlatform names a custom item-query field {name}{ShortName}.
        $query = <<<'GRAPHQL'
        query GetAttr($code: String!) {
            productAttributeProductAttribute(code: $code) {
                attributeCode
                frontendInput
                backendType
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, ['code' => 'name'], adminToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->not->toHaveKey('errors');
        expect($response['json']['data']['productAttributeProductAttribute']['attributeCode'])->toBe('name');
    });

});
