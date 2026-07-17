<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Inventory partial-update tests (WRITE)
 *
 * Regression: a PUT /inventory that omits `qty` (e.g. only flips isInStock) must
 * leave the stored quantity untouched. It used to coerce the missing qty to 0,
 * silently wiping the product's stock.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('PUT /api/rest/v2/inventory partial update', function (): void {

    /**
     * Create a simple product with a known stock qty via the API. Returns
     * [sku, id] or null when the environment can't seed it.
     */
    function seedProductWithStock(float $qty): ?array
    {
        $token = serviceToken(['products/write', 'products/delete', 'inventory/write']);
        $suffix = substr(uniqid(), -8);
        $sku = "PEST-STOCK-{$suffix}";

        $create = apiPost('/api/rest/v2/products', [
            'sku' => $sku,
            'name' => 'Pest Stock Product',
            'price' => 19.95,
            'websiteIds' => [1],
            'stockData' => ['qty' => $qty, 'isInStock' => true, 'manageStock' => true],
        ], $token);

        if (!in_array($create['status'], [200, 201], true) || empty($create['json']['id'])) {
            return null;
        }
        trackCreated('product', (int) $create['json']['id']);

        // Ensure the qty is set regardless of whether create accepted stockData.
        apiPut('/api/rest/v2/inventory', ['sku' => $sku, 'qty' => $qty, 'isInStock' => true], $token);

        return [$sku, (int) $create['json']['id']];
    }

    it('leaves qty untouched when only isInStock is sent', function (): void {
        $seed = seedProductWithStock(50.0);
        if ($seed === null) {
            $this->markTestSkipped('Could not seed a product with stock');
        }
        [$sku] = $seed;
        $token = serviceToken(['inventory/write']);

        // Flip availability only — no qty in the body.
        $response = apiPut('/api/rest/v2/inventory', ['sku' => $sku, 'isInStock' => false], $token);
        expect($response['status'])->toBeIn([200, 201]);

        // qty must be preserved, not zeroed.
        expect((float) $response['json']['qty'])->toBe(50.0);
        expect($response['json']['isInStock'])->toBeFalse();

        // Confirm against a fresh read of the row too.
        $verify = apiPut('/api/rest/v2/inventory', ['sku' => $sku, 'isInStock' => true], $token);
        expect((float) $verify['json']['qty'])->toBe(50.0);
    });

    it('still sets qty to zero when zero is sent explicitly', function (): void {
        $seed = seedProductWithStock(50.0);
        if ($seed === null) {
            $this->markTestSkipped('Could not seed a product with stock');
        }
        [$sku] = $seed;
        $token = serviceToken(['inventory/write']);

        $response = apiPut('/api/rest/v2/inventory', ['sku' => $sku, 'qty' => 0], $token);
        expect($response['status'])->toBeIn([200, 201]);
        expect((float) $response['json']['qty'])->toBe(0.0);
    });

    it('rejects an update with no qty, isInStock or manageStock', function (): void {
        $seed = seedProductWithStock(50.0);
        if ($seed === null) {
            $this->markTestSkipped('Could not seed a product with stock');
        }
        [$sku] = $seed;
        $token = serviceToken(['inventory/write']);

        $response = apiPut('/api/rest/v2/inventory', ['sku' => $sku], $token);
        expect($response['status'])->toBe(400);
    });
});
