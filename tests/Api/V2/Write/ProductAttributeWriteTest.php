<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Product write extensions Tests (WRITE)
 *
 * Covers the attribute-set, tax-class, and generic EAV custom-attribute write
 * support added to the Product resource.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Product attribute-set / tax-class / custom attribute writes', function (): void {

    it('creates a product with an explicit attribute set and tax class', function (): void {
        $sets = apiGet('/api/rest/v2/attribute-sets', adminToken());
        $setId = (int) (getItems($sets)[0]['id'] ?? 0);
        expect($setId)->toBeGreaterThan(0);

        $suffix = substr(uniqid(), -6);
        $response = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-ATTR-{$suffix}",
            'name' => 'Pest Attr Product',
            'price' => 12.50,
            'websiteIds' => [1],
            'attributeSetId' => $setId,
            'taxClassId' => 0,
        ], adminToken());

        expect($response['status'])->toBeSuccessful();
        if (!empty($response['json']['id'])) {
            trackCreated('product', (int) $response['json']['id']);
        }
        expect($response['json']['attributeSetId'])->toBe($setId);
        expect($response['json']['taxClassId'])->toBe(0);
    });

    it('writes an arbitrary EAV attribute through customAttributesWrite', function (): void {
        $suffix = substr(uniqid(), -6);
        $metaTitle = "Generic Meta {$suffix}";

        // meta_title is a real catalog_product attribute. Setting it through the
        // generic bag must round-trip back via the dedicated metaTitle read field.
        $response = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-CATTR-{$suffix}",
            'name' => 'Pest Custom Attr Product',
            'price' => 5.00,
            'websiteIds' => [1],
            'customAttributesWrite' => [
                'meta_title' => $metaTitle,
            ],
        ], adminToken());

        expect($response['status'])->toBeSuccessful();
        if (!empty($response['json']['id'])) {
            trackCreated('product', (int) $response['json']['id']);
        }
        expect($response['json']['metaTitle'])->toBe($metaTitle);
    });

    it('rejects a denylisted system column in customAttributesWrite', function (): void {
        $suffix = substr(uniqid(), -6);
        $response = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-DENY-{$suffix}",
            'name' => 'Pest Deny Product',
            'price' => 5.00,
            'websiteIds' => [1],
            'customAttributesWrite' => [
                'type_id' => 'configurable',
            ],
        ], adminToken());

        // Denylisted keys are rejected with a 4xx, never silently applied.
        expect($response['status'])->toBeGreaterThanOrEqual(400);
        expect($response['status'])->toBeLessThan(500);

        if (!empty($response['json']['id'])) {
            trackCreated('product', (int) $response['json']['id']);
        }
    });

});
