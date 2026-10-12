<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 product stock quantity visibility (WRITE)
 *
 * A caller without product access gets stockQty only at or below the
 * "Only X Left" threshold, as the product page shows it. A token with
 * product access gets the exact quantity.
 *
 * @group write
 */

const STOCK_QTY_THRESHOLD_PATH = 'cataloginventory/options/stock_threshold_qty';

function setStockThresholdQty(?string $value): void
{
    $config = \Mage::getModel('core/config');
    if ($value === null) {
        $config->deleteConfig(STOCK_QTY_THRESHOLD_PATH);
    } else {
        $config->saveConfig(STOCK_QTY_THRESHOLD_PATH, $value);
    }
    \Mage::app()->getCache()->cleanType('config');
    \Mage::app()->cleanCache(['API_PRODUCTS']);
}

beforeAll(function (): void {
    \Mage::app();
});

afterAll(function (): void {
    setStockThresholdQty(null);
    cleanupTestData();
});

describe('Product stockQty for callers without product access', function (): void {

    it('shows the quantity only at or below the threshold', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-STOCKQTY-{$suffix}",
            'name' => 'Pest Stock Qty Product',
            'price' => 10.00,
            'stockQty' => 100,
            'visibility' => 'catalog_search',
            'isActive' => true,
            'websiteIds' => [1],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $productId = (int) $create['json']['id'];
        trackCreated('product', $productId);

        setStockThresholdQty('5');

        $public = apiGet("/api/rest/v2/products/{$productId}");
        expect($public['status'])->toBe(200);
        expect($public['json']['stockQty'] ?? null)->toBeNull();
        expect($public['json']['stockStatus'])->toBe('in_stock');

        expect(apiGet("/api/rest/v2/products/{$productId}", customerToken())['json']['stockQty'] ?? null)->toBeNull();
        expect((float) apiGet("/api/rest/v2/products/{$productId}", $token)['json']['stockQty'])->toBe(100.0);

        $update = apiPut("/api/rest/v2/products/{$productId}", ['stockQty' => 5], $token);
        expect($update['status'])->toBe(200);
        \Mage::app()->cleanCache(['API_PRODUCTS']);

        expect((float) apiGet("/api/rest/v2/products/{$productId}")['json']['stockQty'])->toBe(5.0);

        $iri = "/api/rest/v2/products/{$productId}";
        $graphql = gqlQuery("{ product(id: \"{$iri}\") { stockQty } }", [], customerToken());
        expect($graphql['status'])->toBe(200);
        expect((float) $graphql['json']['data']['product']['stockQty'])->toBe(5.0);

        setStockThresholdQty('0');

        expect(apiGet("/api/rest/v2/products/{$productId}")['json']['stockQty'] ?? null)->toBeNull();
        $graphql = gqlQuery("{ product(id: \"{$iri}\") { stockQty } }", [], customerToken());
        expect($graphql['json']['data']['product']['stockQty'] ?? null)->toBeNull();
        expect((float) apiGet("/api/rest/v2/products/{$productId}", $token)['json']['stockQty'])->toBe(5.0);
    });

    it('hides the quantity of each configurable variant above the threshold', function (): void {
        $sku = fixtures('configurable_sku');
        if (!$sku) {
            $this->markTestSkipped('No configurable product in this install');
        }
        $productId = (int) \Mage::getModel('catalog/product')->getIdBySku($sku);

        setStockThresholdQty('0');

        $public = apiGet("/api/rest/v2/products/{$productId}");
        expect($public['status'])->toBe(200);
        expect($public['json']['variants'])->not->toBeEmpty();
        foreach ($public['json']['variants'] as $variant) {
            expect($variant)->toHaveKey('stockQty');
            expect($variant['stockQty'])->toBeNull();
        }

        $backend = apiGet("/api/rest/v2/products/{$productId}", serviceToken(['products/read']));
        foreach ($backend['json']['variants'] as $variant) {
            expect($variant['stockQty'])->toBeNumeric();
        }
    });
});
