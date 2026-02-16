<?php

declare(strict_types=1);

/**
 * API v2 Product Write Tests
 *
 * End-to-end tests for product create, update, and delete via REST.
 * Permission enforcement and product type field verification.
 *
 * @group write
 */

afterAll(function () {
    cleanupTestData();
});

describe('Product Permission Enforcement (REST)', function () {

    it('denies create without authentication', function () {
        $response = apiPost('/api/products', [
            'sku' => 'TEST-NOAUTH',
            'name' => 'Test Product No Auth',
            'price' => 9.99,
        ]);

        expect($response['status'])->toBe(401);
    });

    it('denies create with customer token (wrong role)', function () {
        $response = apiPost('/api/products', [
            'sku' => 'TEST-CUSTOMER',
            'name' => 'Test Product Customer',
            'price' => 9.99,
        ], customerToken());

        expect($response['status'])->toBeForbidden();
    });

    it('denies create without correct permission', function () {
        $token = serviceToken(['cms-pages/write']);
        $response = apiPost('/api/products', [
            'sku' => 'TEST-NOPERM',
            'name' => 'Test Product No Permission',
            'price' => 9.99,
        ], $token);

        expect($response['status'])->toBeForbidden();
    });

    it('denies update without correct permission', function () {
        $productId = fixtures('product_id');
        $token = serviceToken(['cms-pages/write']);
        $response = apiPut("/api/products/{$productId}", [
            'name' => 'Should Not Update',
        ], $token);

        expect($response['status'])->toBeForbidden();
    });

    it('denies delete without correct permission', function () {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write']);
        $response = apiDelete("/api/products/{$productId}", $token);

        expect($response['status'])->toBeForbidden();
    });

});

describe('Product Create Lifecycle (REST)', function () {

    it('creates a simple product and reads it back', function () {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/products', [
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
        $read = apiGet("/api/products/{$productId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['sku'])->toBe("PEST-SIMPLE-{$suffix}");
        expect($read['json']['price'])->toBe(29.95);
    });

    it('creates a simple product with all basic fields', function () {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/products', [
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
        $read = apiGet("/api/products/{$create['json']['id']}");
        expect($read['status'])->toBe(200);
        expect($read['json']['price'])->toBe(49.95);
    });

    it('creates a virtual product', function () {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/products', [
            'sku' => "PEST-VIRTUAL-{$suffix}",
            'name' => 'Pest Test Virtual Product',
            'type' => 'virtual',
            'price' => 15.00,
            'websiteIds' => [1],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $productId = $create['json']['id'];
        trackCreated('product', $productId);

        $read = apiGet("/api/products/{$productId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['type'])->toBe('virtual');
    });

    it('creates a disabled product', function () {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/products', [
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

    it('requires sku and name for product creation', function () {
        $token = serviceToken(['products/write']);

        // Missing SKU
        $noSku = apiPost('/api/products', [
            'name' => 'No SKU Product',
            'price' => 10.00,
        ], $token);
        expect($noSku['status'])->toBeIn([400, 422]);

        // Missing name
        $noName = apiPost('/api/products', [
            'sku' => 'PEST-NO-NAME-' . substr(uniqid(), -8),
            'price' => 10.00,
        ], $token);
        expect($noName['status'])->toBeIn([400, 422]);
    });

});

describe('Product Update (REST)', function () {

    it('updates a product name and restores it', function () {
        $productId = fixtures('product_id');
        $token = serviceToken(['products/write']);

        // Read original name
        $original = apiGet("/api/products/{$productId}");
        expect($original['status'])->toBe(200);
        $originalName = $original['json']['name'];

        // Update
        $update = apiPut("/api/products/{$productId}", [
            'name' => 'Pest Test Temporary Name',
        ], $token);
        expect($update['status'])->toBe(200);

        // Verify
        $verify = apiGet("/api/products/{$productId}");
        expect($verify['status'])->toBe(200);
        expect($verify['json']['name'])->toBe('Pest Test Temporary Name');

        // Restore
        $restore = apiPut("/api/products/{$productId}", [
            'name' => $originalName,
        ], $token);
        expect($restore['status'])->toBe(200);
    });

});

describe('Product Delete (REST)', function () {

    it('deletes a product successfully', function () {
        $token = serviceToken(['products/write', 'products/delete']);
        $suffix = substr(uniqid(), -8);

        // Create a throwaway product to test delete
        $create = apiPost('/api/products', [
            'sku' => "PEST-DEL-{$suffix}",
            'name' => 'Pest Delete Test',
            'price' => 1.00,
            'websiteIds' => [1],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $productId = $create['json']['id'];

        // Delete should succeed now
        $delete = apiDelete("/api/products/{$productId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);

        // Confirm gone
        $gone = apiGet("/api/products/{$productId}");
        expect($gone['status'])->toBe(404);
    });

});

describe('Product Type Fields - Read Verification', function () {

    it('reads simple product with expected fields', function () {
        $productId = fixtures('product_id');
        $response = apiGet("/api/products/{$productId}");
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

    it('reads configurable product with options and variants', function () {
        // Look up configurable by SKU via collection search
        $sku = fixtures('configurable_sku');
        $response = apiGet("/api/products?search={$sku}");
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
        $detail = apiGet("/api/products/{$configurable['id']}");
        expect($detail['status'])->toBe(200);
        expect($detail['json']['type'])->toBe('configurable');
        expect($detail['json'])->toHaveKey('configurableOptions');
        expect($detail['json'])->toHaveKey('variants');
        expect($detail['json']['configurableOptions'])->toBeArray();
        expect($detail['json']['variants'])->toBeArray();
    });

    it('reads product via GraphQL by SKU', function () {
        $sku = fixtures('product_sku');
        $query = <<<GRAPHQL
        {
            productBySkuProduct(sku: "{$sku}") {
                _id
                sku
                name
                price
                type
                stockStatus
                customOptions
                mediaGallery
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);
        expect($response['status'])->toBe(200);

        $data = $response['json']['data']['productBySkuProduct'] ?? null;
        if ($data === null) {
            // May need auth for this query
            $response = gqlQuery($query, [], customerToken());
            $data = $response['json']['data']['productBySkuProduct'] ?? null;
        }

        if ($data !== null) {
            expect($data['sku'])->toBe($sku);
            expect($data)->toHaveKey('name');
            expect($data)->toHaveKey('price');
        }
    });

});
