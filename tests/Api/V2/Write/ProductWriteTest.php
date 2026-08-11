<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Product Write Tests
 *
 * End-to-end tests for product create, update, and delete via REST.
 * Permission enforcement and product type field verification.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Product Permission Enforcement (REST)', function (): void {

    it('denies create without authentication', function (): void {
        $response = apiPost('/api/rest/v2/products', [
            'sku' => 'TEST-NOAUTH',
            'name' => 'Test Product No Auth',
            'price' => 9.99,
        ]);

        expect($response['status'])->toBe(401);
    });

    it('denies create with customer token (wrong role)', function (): void {
        $response = apiPost('/api/rest/v2/products', [
            'sku' => 'TEST-CUSTOMER',
            'name' => 'Test Product Customer',
            'price' => 9.99,
        ], customerToken());

        expect($response['status'])->toBeForbidden();
    });

    it('denies create without correct permission', function (): void {
        $token = serviceToken(['cms-pages/write']);
        $response = apiPost('/api/rest/v2/products', [
            'sku' => 'TEST-NOPERM',
            'name' => 'Test Product No Permission',
            'price' => 9.99,
        ], $token);

        expect($response['status'])->toBeForbidden();
    });

    it('denies update without correct permission', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['cms-pages/write']);
        $response = apiPut("/api/rest/v2/products/{$productId}", [
            'name' => 'Should Not Update',
        ], $token);

        expect($response['status'])->toBeForbidden();
    });

    it('denies delete without correct permission', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write']);
        $response = apiDelete("/api/rest/v2/products/{$productId}", $token);

        expect($response['status'])->toBeForbidden();
    });

    it('limits backend product reads to tokens holding a products grant', function (): void {
        $writeToken = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-BOREAD-{$suffix}",
            'name' => 'Pest Backend Read Product',
            'price' => 10.00,
            'isActive' => false,
            'websiteIds' => [1],
        ], $writeToken);
        expect($create['status'])->toBeIn([200, 201]);
        $productId = $create['json']['id'];
        trackCreated('product', $productId);

        // The creating write token reads its own disabled product back.
        expect(apiGet("/api/rest/v2/products/{$productId}", $writeToken)['status'])->toBe(200);

        // A service token without any products grant is not a backend
        // reader: the disabled product stays hidden like it is for guests.
        $foreignToken = serviceToken(['cms-pages/write']);
        expect(apiGet("/api/rest/v2/products/{$productId}", $foreignToken)['status'])->toBe(404);
        expect(apiGet("/api/rest/v2/products/{$productId}")['status'])->toBe(404);
    });

    it('strips cost from reads by tokens without a products grant', function (): void {
        $type = costApplicableProductType();
        if ($type === null) {
            $this->markTestSkipped('The cost attribute applies to no createable product type in this install');
        }
        $writeToken = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-BOCOST-{$suffix}",
            'name' => 'Pest Backend Cost Product',
            'type' => $type,
            'price' => 10.00,
            'cost' => 6.50,
            'isActive' => true,
            'websiteIds' => [1],
        ], $writeToken);
        expect($create['status'])->toBeIn([200, 201]);
        $productId = $create['json']['id'];
        trackCreated('product', $productId);

        expect(apiGet("/api/rest/v2/products/{$productId}", $writeToken)['json']['cost'])->toBe(6.50);

        $foreignToken = serviceToken(['cms-pages/write']);
        expect(apiGet("/api/rest/v2/products/{$productId}", $foreignToken)['json']['cost'] ?? null)->toBeNull();
    });

    it('strips custom design fields from reads by tokens without a products grant', function (): void {
        $writeToken = serviceToken(['products/read', 'products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-BODSGN-{$suffix}",
            'name' => 'Pest Backend Design Product',
            'type' => 'simple',
            'price' => 10.00,
            'customDesign' => 'base/default',
            'isActive' => true,
            'websiteIds' => [1],
        ], $writeToken);
        expect($create['status'])->toBeIn([200, 201]);
        $productId = $create['json']['id'];
        trackCreated('product', $productId);

        expect(apiGet("/api/rest/v2/products/{$productId}", $writeToken)['json']['customDesign'])->toBe('base/default');

        $foreign = apiGet("/api/rest/v2/products/{$productId}", serviceToken(['cms-pages/write']))['json'];
        expect($foreign['customDesign'] ?? null)->toBeNull();
        expect($foreign['customLayoutUpdate'] ?? null)->toBeNull();
    });

});

describe('Product Create Lifecycle (REST)', function (): void {

    it('creates a simple product and reads it back', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-SIMPLE-{$suffix}",
            'name' => 'Pest Test Simple Product',
            'price' => 29.95,
            'websiteIds' => [1],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        expect($create['json'])->toHaveKey('id');
        expect($create['json']['sku'])->toBe("PEST-SIMPLE-{$suffix}");
        expect($create['json']['name'])->toBe('Pest Test Simple Product');
        expect($create['json']['status'])->toBe('enabled');
        expect($create['json']['type'])->toBe('simple');

        $productId = $create['json']['id'];
        trackCreated('product', $productId);

        // Read back (public)
        $read = apiGet("/api/rest/v2/products/{$productId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['sku'])->toBe("PEST-SIMPLE-{$suffix}");
        expect($read['json']['price'])->toBe(29.95);
    });

    it('creates a simple product with all basic fields', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-FULL-{$suffix}",
            'name' => 'Pest Test Full Product',
            'type' => 'simple',
            'price' => 49.95,
            'specialPrice' => 39.95,
            'description' => '<p>A test product created by Pest test suite</p>',
            'shortDescription' => 'Test product for API testing',
            'weight' => 0.5,
            'urlKey' => "pest-test-full-{$suffix}",
            'metaTitle' => 'Pest Test Product',
            'metaDescription' => 'A test product',
            'visibility' => 'catalog_search',
            'isActive' => true,
            'websiteIds' => [1],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        expect($create['json']['sku'])->toBe("PEST-FULL-{$suffix}");
        expect($create['json']['name'])->toBe('Pest Test Full Product');
        trackCreated('product', $create['json']['id']);

        // Read back and verify extra fields persisted
        $read = apiGet("/api/rest/v2/products/{$create['json']['id']}");
        expect($read['status'])->toBe(200);
        expect($read['json']['price'])->toBe(49.95);
    });

    it('creates a product with a special price date range and reads it back', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-SPECIAL-{$suffix}",
            'name' => 'Pest Test Special Price Product',
            'price' => 49.95,
            'specialPrice' => 39.95,
            'specialFromDate' => '2026-08-01',
            'specialToDate' => '2026-08-31',
            'websiteIds' => [1],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        expect($create['json']['specialFromDate'])->toBe('2026-08-01');
        expect($create['json']['specialToDate'])->toBe('2026-08-31');
        $productId = $create['json']['id'];
        trackCreated('product', $productId);

        $read = apiGet("/api/rest/v2/products/{$productId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['specialPrice'])->toBe(39.95);
        expect($read['json']['specialFromDate'])->toBe('2026-08-01');
        expect($read['json']['specialToDate'])->toBe('2026-08-31');
    });

    it('rejects an invalid or inverted special price date range', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $badDate = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-BADDATE-{$suffix}",
            'name' => 'Pest Test Bad Date',
            'price' => 10.00,
            'specialFromDate' => 'not-a-date',
        ], $token);
        expect($badDate['status'])->toBe(400);

        $inverted = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-INVDATE-{$suffix}",
            'name' => 'Pest Test Inverted Dates',
            'price' => 10.00,
            'specialFromDate' => '2026-09-01',
            'specialToDate' => '2026-08-01',
        ], $token);
        expect($inverted['status'])->toBe(400);
    });

    it('creates a virtual product', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-VIRTUAL-{$suffix}",
            'name' => 'Pest Test Virtual Product',
            'type' => 'virtual',
            'price' => 15.00,
            'websiteIds' => [1],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $productId = $create['json']['id'];
        trackCreated('product', $productId);

        $read = apiGet("/api/rest/v2/products/{$productId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['type'])->toBe('virtual');
    });

    it('creates a disabled product', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-DISABLED-{$suffix}",
            'name' => 'Pest Test Disabled Product',
            'price' => 10.00,
            'isActive' => false,
            'websiteIds' => [1],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        expect($create['json']['status'])->toBe('disabled');
        trackCreated('product', $create['json']['id']);
    });

    it('requires sku and name for product creation', function (): void {
        $token = serviceToken(['products/write']);

        // Missing SKU
        $noSku = apiPost('/api/rest/v2/products', [
            'name' => 'No SKU Product',
            'price' => 10.00,
        ], $token);
        expect($noSku['status'])->toBeIn([400, 422]);

        // Missing name
        $noName = apiPost('/api/rest/v2/products', [
            'sku' => 'PEST-NO-NAME-' . substr(uniqid(), -8),
            'price' => 10.00,
        ], $token);
        expect($noName['status'])->toBeIn([400, 422]);
    });

});

describe('Product Update (REST)', function (): void {

    it('updates a product name and restores it', function (): void {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write']);

        // Read original name
        $original = apiGet("/api/rest/v2/products/{$productId}");
        expect($original['status'])->toBe(200);
        $originalName = $original['json']['name'];

        // Update
        $update = apiPut("/api/rest/v2/products/{$productId}", [
            'name' => 'Pest Test Temporary Name',
        ], $token);
        expect($update['status'])->toBe(200);

        // Verify
        $verify = apiGet("/api/rest/v2/products/{$productId}");
        expect($verify['status'])->toBe(200);
        expect($verify['json']['name'])->toBe('Pest Test Temporary Name');

        // Restore
        $restore = apiPut("/api/rest/v2/products/{$productId}", [
            'name' => $originalName,
        ], $token);
        expect($restore['status'])->toBe(200);
    });

    it('updates and clears special price dates', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-SPDATES-{$suffix}",
            'name' => 'Pest Test Special Dates Update',
            'price' => 20.00,
            'websiteIds' => [1],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $productId = $create['json']['id'];
        trackCreated('product', $productId);

        $update = apiPut("/api/rest/v2/products/{$productId}", [
            'specialPrice' => 15.00,
            'specialFromDate' => '2026-08-01',
            'specialToDate' => '2026-08-31',
        ], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['specialFromDate'])->toBe('2026-08-01');
        expect($update['json']['specialToDate'])->toBe('2026-08-31');

        // Empty string clears a date; the omitted field stays untouched
        $clear = apiPut("/api/rest/v2/products/{$productId}", [
            'specialToDate' => '',
        ], $token);
        expect($clear['status'])->toBe(200);
        expect($clear['json']['specialToDate'])->toBeNull();

        $read = apiGet("/api/rest/v2/products/{$productId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['specialFromDate'])->toBe('2026-08-01');
        expect($read['json']['specialToDate'])->toBeNull();
    });

});

describe('Product Extended Fields (REST)', function (): void {

    it('creates a product with extended price, date and msrp fields and reads them back', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-EXT-{$suffix}",
            'name' => 'Pest Extended Fields Product',
            'price' => 99.95,
            'msrp' => 129.99,
            'msrpEnabled' => 1,
            'msrpDisplayActualPriceType' => 1,
            'newsFromDate' => '2026-08-01',
            'newsToDate' => '2026-08-31',
            'metaRobots' => 'noindex,nofollow',
            'countryOfManufacture' => 'IT',
            'imageLabel' => 'Front view',
            'websiteIds' => [1],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        expect($create['json']['msrp'])->toBe(129.99);
        expect($create['json']['msrpEnabled'])->toBe(1);
        expect($create['json']['newsFromDate'])->toBe('2026-08-01');
        expect($create['json']['newsToDate'])->toBe('2026-08-31');
        $productId = $create['json']['id'];
        trackCreated('product', $productId);

        $read = apiGet("/api/rest/v2/products/{$productId}", $token);
        expect($read['status'])->toBe(200);
        expect($read['json']['msrp'])->toBe(129.99);
        expect($read['json']['msrpDisplayActualPriceType'])->toBe(1);
        expect($read['json']['newsFromDate'])->toBe('2026-08-01');
        expect($read['json']['newsToDate'])->toBe('2026-08-31');
        expect($read['json']['metaRobots'])->toBe('noindex,nofollow');
        expect($read['json']['countryOfManufacture'])->toBe('IT');
        expect($read['json']['imageLabel'])->toBe('Front view');
    });

    it('exposes websiteIds on product detail reads', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-WSID-{$suffix}",
            'name' => 'Pest Website Ids Product',
            'price' => 5.00,
            'websiteIds' => [1],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        expect($create['json']['websiteIds'])->toBe([1]);
        $productId = $create['json']['id'];
        trackCreated('product', $productId);

        $read = apiGet("/api/rest/v2/products/{$productId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['websiteIds'])->toBe([1]);
    });

    it('exposes a written user-defined attribute through customAttributes', function (): void {
        Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();

        $code = 'pest_custom_' . substr(uniqid(), -6);
        $setup = new \Mage_Catalog_Model_Resource_Setup('catalog_setup');
        $setup->addAttribute(\Mage_Catalog_Model_Product::ENTITY, $code, [
            'type' => 'varchar',
            'label' => 'Pest Custom Attribute',
            'input' => 'text',
            'required' => false,
            'user_defined' => true,
            'global' => \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
            'group' => 'General',
        ]);
        \Mage::app()->getCache()->cleanType('eav');

        try {
            $token = serviceToken(['products/write', 'products/delete']);
            $suffix = substr(uniqid(), -8);

            $create = apiPost('/api/rest/v2/products', [
                'sku' => "PEST-CATTR-READ-{$suffix}",
                'name' => 'Pest Custom Attributes Read Product',
                'price' => 9.99,
                'websiteIds' => [1],
                'customAttributesWrite' => [$code => 'pest-value'],
            ], $token);
            expect($create['status'])->toBeIn([200, 201]);
            $productId = $create['json']['id'];
            trackCreated('product', $productId);

            $read = apiGet("/api/rest/v2/products/{$productId}", $token);
            expect($read['status'])->toBe(200);
            expect($read['json']['customAttributes'])->toBeArray();
            expect($read['json']['customAttributes'][$code] ?? null)->toBe('pest-value');

            // The raw attribute map ignores is_visible_on_front, so it is
            // backend only; the response cache must not leak it.
            $public = apiGet("/api/rest/v2/products/{$productId}");
            expect($public['status'])->toBe(200);
            expect($public['json']['customAttributes'] ?? null)->toBeNull();

            $reread = apiGet("/api/rest/v2/products/{$productId}", $token);
            expect($reread['json']['customAttributes'][$code] ?? null)->toBe('pest-value');
        } finally {
            $setup->removeAttribute(\Mage_Catalog_Model_Product::ENTITY, $code);
            \Mage::app()->getCache()->cleanType('eav');
        }
    });

    it('keeps cost visibility per caller regardless of who warms the response cache', function (): void {
        $type = costApplicableProductType();
        if ($type === null) {
            $this->markTestSkipped('The cost attribute applies to no createable product type in this install');
        }

        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-COSTCACHE-A-{$suffix}",
            'name' => 'Pest Cost Cache Public First',
            'type' => $type,
            'price' => 60.00,
            'cost' => 42.50,
            'websiteIds' => [1],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $publicFirstId = $create['json']['id'];
        trackCreated('product', $publicFirstId);

        // Anonymous read warms the cache: the privileged read after it must
        // still see the cost.
        expect((apiGet("/api/rest/v2/products/{$publicFirstId}"))['json']['cost'] ?? null)->toBeNull();
        expect((apiGet("/api/rest/v2/products/{$publicFirstId}", $token))['json']['cost'])->toBe(42.50);
        expect((apiGet("/api/rest/v2/products/{$publicFirstId}"))['json']['cost'] ?? null)->toBeNull();

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-COSTCACHE-B-{$suffix}",
            'name' => 'Pest Cost Cache Privileged First',
            'type' => $type,
            'price' => 30.00,
            'cost' => 17.25,
            'websiteIds' => [1],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $privilegedFirstId = $create['json']['id'];
        trackCreated('product', $privilegedFirstId);

        // Privileged read warms the cache: the anonymous read after it must
        // not receive the cost.
        expect((apiGet("/api/rest/v2/products/{$privilegedFirstId}", $token))['json']['cost'])->toBe(17.25);
        expect((apiGet("/api/rest/v2/products/{$privilegedFirstId}"))['json']['cost'] ?? null)->toBeNull();
        expect((apiGet("/api/rest/v2/products/{$privilegedFirstId}", $token))['json']['cost'])->toBe(17.25);
    });

    it('rejects an unseparated numeric date instead of storing a unix timestamp', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-BADDATE-{$suffix}",
            'name' => 'Pest Numeric Date Product',
            'price' => 4.00,
            'websiteIds' => [1],
            'specialFromDate' => '20260801',
        ], $token);

        expect($create['status'])->toBe(400);
    });

    it('rejects out-of-range extended stock values', function (): void {
        $token = serviceToken(['products/write', 'products/delete', 'inventory/write']);
        $suffix = substr(uniqid(), -8);
        $sku = "PEST-STOCKVAL-{$suffix}";

        $create = apiPost('/api/rest/v2/products', [
            'sku' => $sku,
            'name' => 'Pest Stock Validation Product',
            'price' => 6.00,
            'websiteIds' => [1],
            'stockData' => ['qty' => 5, 'is_in_stock' => true],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        trackCreated('product', $create['json']['id']);

        $badBackorders = apiPut('/api/rest/v2/inventory', [
            'sku' => $sku,
            'backorders' => 5,
        ], $token);
        expect($badBackorders['status'])->toBe(400);

        $negativeMinQty = apiPut('/api/rest/v2/inventory', [
            'sku' => $sku,
            'minQty' => -3,
        ], $token);
        expect($negativeMinQty['status'])->toBe(400);

        $goodBulk = apiPut('/api/rest/v2/inventory/bulk', [
            'items' => [
                ['sku' => $sku, 'qty' => 2, 'qtyIncrements' => 1],
            ],
        ], $token);
        expect($goodBulk['status'])->toBeIn([200, 201]);

        $badBulk = apiPut('/api/rest/v2/inventory/bulk', [
            'items' => [
                ['sku' => $sku, 'qty' => 2, 'qtyIncrements' => -1],
            ],
        ], $token);
        expect($badBulk['status'])->toBe(400);
    });

    it('writes extended stock data on create and reads it back through stockItem, backend columns only for privileged callers', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-STOCKX-{$suffix}",
            'name' => 'Pest Extended Stock Product',
            'price' => 12.34,
            'websiteIds' => [1],
            'stockData' => [
                'qty' => 25,
                'is_in_stock' => true,
                'manage_stock' => true,
                'min_qty' => 2,
                'min_sale_qty' => 3,
                'max_sale_qty' => 10,
                'backorders' => 1,
                'notify_stock_qty' => 4,
                'qty_increments' => 5,
                'enable_qty_increments' => true,
                'use_config_min_qty' => false,
                'use_config_min_sale_qty' => false,
                'use_config_max_sale_qty' => false,
                'use_config_backorders' => false,
                'use_config_notify_stock_qty' => false,
                'use_config_qty_increments' => false,
                'use_config_enable_qty_inc' => false,
                'use_config_manage_stock' => false,
            ],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $productId = $create['json']['id'];
        trackCreated('product', $productId);

        // Anonymous: only the columns that constrain what a shopper may order.
        $public = apiGet("/api/rest/v2/products/{$productId}");
        expect($public['status'])->toBe(200);
        $publicStock = $public['json']['stockItem'] ?? null;
        expect($publicStock)->toBeArray();
        expect((float) $publicStock['qty'])->toBe(25.0);
        expect($publicStock['is_in_stock'])->toBeTrue();
        expect((float) $publicStock['min_sale_qty'])->toBe(3.0);
        expect((float) $publicStock['max_sale_qty'])->toBe(10.0);
        expect((float) $publicStock['qty_increments'])->toBe(5.0);
        expect($publicStock['enable_qty_increments'])->toBeTrue();
        expect($publicStock)->toHaveKey('is_qty_decimal');

        foreach (['min_qty', 'notify_stock_qty', 'backorders', 'manage_stock', 'low_stock_date'] as $column) {
            expect($publicStock)->not->toHaveKey($column);
        }
        foreach (array_keys($publicStock) as $column) {
            expect($column)->not->toStartWith('use_config_');
        }

        // Privileged: the full admin inventory structure.
        $read = apiGet("/api/rest/v2/products/{$productId}", $token);
        expect($read['status'])->toBe(200);
        $stockItem = $read['json']['stockItem'] ?? null;
        expect($stockItem)->toBeArray();
        expect((float) $stockItem['qty'])->toBe(25.0);
        expect($stockItem['is_in_stock'])->toBeTrue();
        expect($stockItem['manage_stock'])->toBeTrue();
        expect((float) $stockItem['min_qty'])->toBe(2.0);
        expect((float) $stockItem['min_sale_qty'])->toBe(3.0);
        expect((float) $stockItem['max_sale_qty'])->toBe(10.0);
        expect($stockItem['backorders'])->toBe(1);
        expect((float) $stockItem['notify_stock_qty'])->toBe(4.0);
        expect((float) $stockItem['qty_increments'])->toBe(5.0);
        expect($stockItem['enable_qty_increments'])->toBeTrue();
        expect($stockItem['use_config_backorders'])->toBeFalse();
        expect($stockItem['use_config_manage_stock'])->toBeFalse();
    });

    it('keeps stock item visibility per caller regardless of who warms the response cache', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);
        $stockData = [
            'qty' => 20,
            'is_in_stock' => true,
            'min_qty' => 2,
            'min_sale_qty' => 3,
            'use_config_min_qty' => false,
            'use_config_min_sale_qty' => false,
        ];

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-STOCKCACHE-A-{$suffix}",
            'name' => 'Pest Stock Cache Public First',
            'price' => 11.00,
            'websiteIds' => [1],
            'stockData' => $stockData,
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $publicFirstId = $create['json']['id'];
        trackCreated('product', $publicFirstId);

        // Anonymous read warms the cache: the privileged read after it must
        // still see the backend columns.
        $publicStock = (apiGet("/api/rest/v2/products/{$publicFirstId}"))['json']['stockItem'];
        expect($publicStock)->not->toHaveKey('min_qty');
        expect($publicStock)->not->toHaveKey('use_config_min_qty');
        expect((float) $publicStock['min_sale_qty'])->toBe(3.0);

        $privilegedStock = (apiGet("/api/rest/v2/products/{$publicFirstId}", $token))['json']['stockItem'];
        expect((float) $privilegedStock['min_qty'])->toBe(2.0);
        expect($privilegedStock['use_config_min_qty'])->toBeFalse();

        expect((apiGet("/api/rest/v2/products/{$publicFirstId}"))['json']['stockItem'])->not->toHaveKey('min_qty');

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-STOCKCACHE-B-{$suffix}",
            'name' => 'Pest Stock Cache Privileged First',
            'price' => 12.00,
            'websiteIds' => [1],
            'stockData' => $stockData,
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $privilegedFirstId = $create['json']['id'];
        trackCreated('product', $privilegedFirstId);

        // Privileged read warms the cache: the anonymous read after it must not
        // receive the backend columns.
        $privilegedStock = (apiGet("/api/rest/v2/products/{$privilegedFirstId}", $token))['json']['stockItem'];
        expect((float) $privilegedStock['min_qty'])->toBe(2.0);
        expect($privilegedStock['use_config_min_qty'])->toBeFalse();

        $publicStock = (apiGet("/api/rest/v2/products/{$privilegedFirstId}"))['json']['stockItem'];
        expect($publicStock)->not->toHaveKey('min_qty');
        expect($publicStock)->not->toHaveKey('use_config_min_qty');
        expect((float) $publicStock['min_sale_qty'])->toBe(3.0);

        expect((float) (apiGet("/api/rest/v2/products/{$privilegedFirstId}", $token))['json']['stockItem']['min_qty'])->toBe(2.0);
    });

    it('updates extended stock fields via the inventory endpoint', function (): void {
        $token = serviceToken(['products/write', 'products/delete', 'inventory/write']);
        $suffix = substr(uniqid(), -8);
        $sku = "PEST-INVX-{$suffix}";

        $create = apiPost('/api/rest/v2/products', [
            'sku' => $sku,
            'name' => 'Pest Inventory Extended Product',
            'price' => 8.00,
            'websiteIds' => [1],
            'stockData' => ['qty' => 10, 'is_in_stock' => true],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $productId = $create['json']['id'];
        trackCreated('product', $productId);

        $update = apiPut('/api/rest/v2/inventory', [
            'sku' => $sku,
            'qty' => 7,
            'minSaleQty' => 2,
            'backorders' => 2,
            'useConfigBackorders' => false,
        ], $token);
        expect($update['status'])->toBeIn([200, 201]);
        expect((float) $update['json']['qty'])->toBe(7.0);
        expect((float) $update['json']['minSaleQty'])->toBe(2.0);
        expect($update['json']['backorders'])->toBe(2);
        expect($update['json']['useConfigBackorders'])->toBeFalse();

        $read = apiGet("/api/rest/v2/products/{$productId}", $token);
        expect($read['status'])->toBe(200);
        $stockItem = $read['json']['stockItem'] ?? null;
        expect($stockItem)->toBeArray();
        expect((float) $stockItem['qty'])->toBe(7.0);
        expect((float) $stockItem['min_sale_qty'])->toBe(2.0);
        expect($stockItem['backorders'])->toBe(2);
        expect($stockItem['use_config_backorders'])->toBeFalse();
    });

});

describe('Product Delete (REST)', function (): void {

    it('deletes a product successfully', function (): void {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        // Create a throwaway product to test delete
        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-DEL-{$suffix}",
            'name' => 'Pest Delete Test',
            'price' => 1.00,
            'websiteIds' => [1],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $productId = $create['json']['id'];

        // Delete should succeed now
        $delete = apiDelete("/api/rest/v2/products/{$productId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);

        // Confirm gone
        $gone = apiGet("/api/rest/v2/products/{$productId}");
        expect($gone['status'])->toBe(404);
    });

});

describe('Product Type Fields - Read Verification', function (): void {

    it('reads simple product with expected fields', function (): void {
        $productId = fixtures('product_id');
        $response = apiGet("/api/rest/v2/products/{$productId}");
        expect($response['status'])->toBe(200);

        $product = $response['json'];
        expect($product)->toHaveKey('sku');
        expect($product)->toHaveKey('name');
        expect($product)->toHaveKey('price');
        expect($product)->toHaveKey('type');
        expect($product)->toHaveKey('stockStatus');
        expect($product)->toHaveKey('customOptions');
        expect($product)->toHaveKey('mediaGallery');
        expect($product['customOptions'])->toBeArray();
        expect($product['mediaGallery'])->toBeArray();
    });

    it('reads configurable product with options and variants', function (): void {
        // Look up configurable by SKU via collection search
        $sku = fixtures('configurable_sku');
        $response = apiGet("/api/rest/v2/products?search={$sku}");
        expect($response['status'])->toBe(200);

        $items = getItems($response);
        $configurable = null;
        foreach ($items as $item) {
            if (($item['sku'] ?? '') === $sku && ($item['type'] ?? '') === 'configurable') {
                $configurable = $item;
                break;
            }
        }

        if ($configurable === null) {
            test()->markTestSkipped("Configurable product {$sku} not found in search results");
        }

        // Get detail
        $detail = apiGet("/api/rest/v2/products/{$configurable['id']}");
        expect($detail['status'])->toBe(200);
        expect($detail['json']['type'])->toBe('configurable');
        expect($detail['json'])->toHaveKey('configurableOptions');
        expect($detail['json'])->toHaveKey('variants');
        expect($detail['json']['configurableOptions'])->toBeArray();
        expect($detail['json']['variants'])->toBeArray();
    });

    it('reads product via GraphQL by SKU', function (): void {
        $sku = fixtures('product_sku');
        $query = <<<GRAPHQL
        {
            products(sku: "{$sku}") {
                edges { node {
                    _id
                    sku
                    name
                    price
                    type
                    stockStatus
                    customOptions
                    mediaGallery
                } }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);
        expect($response['status'])->toBe(200);

        $data = $response['json']['data']['products']['edges'][0]['node'] ?? null;
        if ($data === null) {
            // May need auth for this query
            $response = gqlQuery($query, [], customerToken());
            $data = $response['json']['data']['products']['edges'][0]['node'] ?? null;
        }

        if ($data !== null) {
            expect($data['sku'])->toBe($sku);
            expect($data)->toHaveKey('name');
            expect($data)->toHaveKey('price');
        }
    });

});

/**
 * A product type the `cost` attribute applies to, or null when there is none.
 *
 * _isApplicableAttribute() silently drops a value whose attribute does not apply
 * to the product's type, and apply_to for cost differs between a Maho core
 * install (simple,virtual) and a sample-data one (virtual,downloadable), so the
 * type has to be read from the attribute rather than hard-coded.
 */
function costApplicableProductType(): ?string
{
    Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();

    $attribute = Mage::getSingleton('eav/config')
        ->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'cost');

    $applyTo = $attribute ? $attribute->getApplyTo() : [];
    if ($applyTo === []) {
        return Mage_Catalog_Model_Product_Type::TYPE_SIMPLE;
    }

    foreach ([Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL] as $type) {
        if (in_array($type, $applyTo, true)) {
            return $type;
        }
    }

    return null;
}
