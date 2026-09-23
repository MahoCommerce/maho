<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ApiV2Helper;

/**
 * API v2 store-scoped product write tests.
 *
 * Verifies the EAV write-scope contract: a plain update writes the global
 * (admin) scope, ?store= writes a store-view override, useDefault removes the
 * override again, and ?store=admin exposes raw global values to backend
 * readers only. Runs against a temporary second store view so multi-store
 * semantics apply (a single-store install collapses every scope to global).
 *
 * @group write
 */

const SCOPE_STORE_CODE = 'apitest_scope';

function scopeTestStoreId(): int
{
    return (int) ($GLOBALS['_scope_test_store_id'] ?? 0);
}

/** Read the raw name rows for a product keyed by store_id, straight from EAV. */
function nameRowsByStore(int $productId): array
{
    ApiV2Helper::ensureMahoBootstrapped();
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $attributeId = (int) Mage::getSingleton('eav/config')
        ->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'name')
        ->getId();

    $select = $adapter->select()
        ->from($resource->getTableName('catalog_product_entity_varchar'), ['store_id', 'value'])
        ->where('entity_id = ?', $productId)
        ->where('attribute_id = ?', $attributeId);

    $rows = [];
    foreach ($adapter->fetchAll($select) as $row) {
        $rows[(int) $row['store_id']] = $row['value'];
    }
    return $rows;
}

/** Codes of the attributes that have a value row for a product in one store, from every EAV value table. */
function productStoreValueCodes(int $productId, int $storeId): array
{
    ApiV2Helper::ensureMahoBootstrapped();
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');

    $codes = [];
    foreach (['varchar', 'text', 'int', 'decimal', 'datetime'] as $type) {
        $select = $adapter->select()
            ->from(['v' => $resource->getTableName("catalog_product_entity_{$type}")], [])
            ->join(['a' => $resource->getTableName('eav_attribute')], 'a.attribute_id = v.attribute_id', ['attribute_code'])
            ->where('v.entity_id = ?', $productId)
            ->where('v.store_id = ?', $storeId);
        $codes = array_merge($codes, $adapter->fetchCol($select));
    }
    sort($codes);
    return $codes;
}

beforeAll(function (): void {
    ApiV2Helper::ensureMahoBootstrapped();

    $existing = Mage::getModel('core/store')->load(SCOPE_STORE_CODE, 'code');
    if ($existing->getId()) {
        $GLOBALS['_scope_test_store_id'] = (int) $existing->getId();
        return;
    }

    $website = Mage::app()->getWebsite(1);
    $store = Mage::getModel('core/store');
    $store->setCode(SCOPE_STORE_CODE)
        ->setWebsiteId((int) $website->getId())
        ->setGroupId((int) $website->getDefaultGroupId())
        ->setName('API Scope Test Store')
        ->setIsActive()
        ->setSortOrder(99)
        ->save();
    $GLOBALS['_scope_test_store_id'] = (int) $store->getId();

    // The API server bootstraps per request off the shared cache; flush so it
    // sees the new store (and leaves single-store mode) immediately.
    Mage::app()->cleanCache();
});

afterAll(function (): void {
    ApiV2Helper::ensureMahoBootstrapped();
    $store = Mage::getModel('core/store')->load(SCOPE_STORE_CODE, 'code');
    if ($store->getId()) {
        Mage::register('isSecureArea', true);
        try {
            $store->delete();
        } finally {
            Mage::unregister('isSecureArea');
        }
    }
    Mage::app()->cleanCache();
    cleanupTestData();
});

describe('Product write scope (REST)', function (): void {

    it('writes the global scope on a plain update', function (): void {
        $token = serviceToken(['products/write', 'products/read']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/products', [
            'sku' => "PEST-SCOPE-{$suffix}",
            'name' => 'Scope Test Product',
            'price' => 10.00,
            'websiteIds' => [1],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $productId = (int) $create['json']['id'];
        trackCreated('product', $productId);
        $GLOBALS['_scope_test_product_id'] = $productId;

        $update = apiPut("/api/rest/v2/products/{$productId}", [
            'name' => 'Scope Test Global Name',
        ], $token);
        expect($update['status'])->toBe(200);

        $rows = nameRowsByStore($productId);
        expect($rows[0] ?? null)->toBe('Scope Test Global Name');
        expect($rows)->not->toHaveKey(scopeTestStoreId());
    });

    it('writes a store override with ?store= and leaves the global value untouched', function (): void {
        $productId = (int) $GLOBALS['_scope_test_product_id'];
        $token = serviceToken(['products/write', 'products/read']);

        $update = apiPut('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE, [
            'name' => 'Scope Test Store Name',
        ], $token);
        expect($update['status'])->toBe(200);

        $rows = nameRowsByStore($productId);
        expect($rows[0] ?? null)->toBe('Scope Test Global Name');
        expect($rows[scopeTestStoreId()] ?? null)->toBe('Scope Test Store Name');
    });

    it('reads the override in store context and the global value elsewhere', function (): void {
        $productId = (int) $GLOBALS['_scope_test_product_id'];

        $storeRead = apiGet('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE);
        expect($storeRead['status'])->toBe(200);
        expect($storeRead['json']['name'])->toBe('Scope Test Store Name');

        $defaultRead = apiGet("/api/rest/v2/products/{$productId}");
        expect($defaultRead['status'])->toBe(200);
        expect($defaultRead['json']['name'])->toBe('Scope Test Global Name');
    });

    it('removes the override with useDefault', function (): void {
        $productId = (int) $GLOBALS['_scope_test_product_id'];
        $token = serviceToken(['products/write', 'products/read']);

        $update = apiPut('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE, [
            'useDefault' => ['name'],
        ], $token);
        expect($update['status'])->toBe(200);

        $rows = nameRowsByStore($productId);
        expect($rows)->not->toHaveKey(scopeTestStoreId());
        expect($rows[0] ?? null)->toBe('Scope Test Global Name');
    });

    it('rejects an empty or unknown store parameter instead of writing globally', function (): void {
        $productId = (int) $GLOBALS['_scope_test_product_id'];
        $token = serviceToken(['products/write']);

        $empty = apiPut("/api/rest/v2/products/{$productId}?store=", [
            'name' => 'Should Never Land',
        ], $token);
        expect($empty['status'])->toBe(400);

        $unknown = apiPut("/api/rest/v2/products/{$productId}?store=does_not_exist", [
            'name' => 'Should Never Land',
        ], $token);
        expect($unknown['status'])->toBe(400);

        expect(nameRowsByStore($productId)[0] ?? null)->toBe('Scope Test Global Name');
    });

    it('rejects useDefault without a store context', function (): void {
        $productId = (int) $GLOBALS['_scope_test_product_id'];
        $token = serviceToken(['products/write']);

        $update = apiPut("/api/rest/v2/products/{$productId}", [
            'useDefault' => ['name'],
        ], $token);
        expect($update['status'])->toBe(400);
    });

    it('supports store overrides and useDefault in fast-update mode', function (): void {
        $productId = (int) $GLOBALS['_scope_test_product_id'];
        $token = serviceToken(['products/write', 'products/read']);

        $update = apiPut('/api/rest/v2/products/' . $productId . '?fast=true&store=' . SCOPE_STORE_CODE, [
            'name' => 'Scope Test Fast Store Name',
        ], $token);
        expect($update['status'])->toBe(200);
        expect(nameRowsByStore($productId)[scopeTestStoreId()] ?? null)->toBe('Scope Test Fast Store Name');

        $revert = apiPut('/api/rest/v2/products/' . $productId . '?fast=true&store=' . SCOPE_STORE_CODE, [
            'useDefault' => ['name'],
        ], $token);
        expect($revert['status'])->toBe(200);
        expect(nameRowsByStore($productId))->not->toHaveKey(scopeTestStoreId());
    });

    it('denies global-scope writes to a store-restricted token but allows its own store', function (): void {
        $productId = (int) $GLOBALS['_scope_test_product_id'];
        $token = serviceToken(['products/write'], [scopeTestStoreId()]);

        $global = apiPut("/api/rest/v2/products/{$productId}", [
            'name' => 'Restricted Global Attempt',
        ], $token);
        expect($global['status'])->toBe(403);

        $scoped = apiPut('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE, [
            'name' => 'Restricted Store Name',
        ], $token);
        expect($scoped['status'])->toBe(200);

        // Clean the override so later tests see only the global value.
        $revert = apiPut('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE, [
            'useDefault' => ['name'],
        ], $token);
        expect($revert['status'])->toBe(200);
    });

});

describe('Store override flags (storeOverrides)', function (): void {

    it('lists only the attribute that a one-field store write overrides', function (): void {
        $productId = (int) $GLOBALS['_scope_test_product_id'];
        $token = serviceToken(['products/write', 'products/read']);

        // Default values of attributes whose backends act on save: special price
        // dates, URL key, image labels, and tier prices in their own table.
        $global = apiPut("/api/rest/v2/products/{$productId}", [
            'specialPrice' => 8.5,
            'specialFromDate' => '2026-01-01',
            'metaTitle' => 'Scope Test Global Meta Title',
            'urlKey' => 'scope-test-global-url-key-' . $productId,
            'imageLabel' => 'Scope Test Global Image Label',
        ], $token);
        expect($global['status'])->toBe(200);
        $tiers = apiPut("/api/rest/v2/products/{$productId}/tier-prices", [
            ['customerGroupId' => 'all', 'websiteId' => 0, 'qty' => 5, 'price' => 9.5],
            ['customerGroupId' => 'all', 'websiteId' => 1, 'qty' => 10, 'price' => 9.0],
        ], $token);
        expect($tiers['status'])->toBe(200);
        $tierCount = count(getItems(apiGet("/api/rest/v2/products/{$productId}/tier-prices", $token)));
        expect($tierCount)->toBeGreaterThan(0);
        expect(productStoreValueCodes($productId, scopeTestStoreId()))->toBe([]);

        $update = apiPut('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE, [
            'name' => 'Scope Test Store Name',
        ], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['storeOverrides'])->toBe(['name']);
        expect($update['json']['metaTitle'])->toBe('Scope Test Global Meta Title');
        expect($update['json']['specialFromDate'])->toBe('2026-01-01');
        expect(productStoreValueCodes($productId, scopeTestStoreId()))->toBe(['name']);
        expect(getItems(apiGet("/api/rest/v2/products/{$productId}/tier-prices", $token)))->toHaveCount($tierCount);

        $read = apiGet('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE, $token);
        expect($read['status'])->toBe(200);
        expect($read['json']['storeOverrides'])->toBe(['name']);
    });

    it('does not bring back an override that useDefault removed', function (): void {
        $productId = (int) $GLOBALS['_scope_test_product_id'];
        $token = serviceToken(['products/write', 'products/read']);

        $revert = apiPut('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE, [
            'useDefault' => ['name'],
        ], $token);
        expect($revert['status'])->toBe(200);
        expect($revert['json']['storeOverrides'])->toBe([]);

        $update = apiPut('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE, [
            'shortDescription' => 'Scope Test Store Short Description',
        ], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['storeOverrides'])->toBe(['short_description']);
        expect($update['json']['name'])->toBe('Scope Test Global Name');
        expect(productStoreValueCodes($productId, scopeTestStoreId()))->toBe(['short_description']);

        // The fast path writes only the fields of the request too.
        $fast = apiPut('/api/rest/v2/products/' . $productId . '?fast=true&store=' . SCOPE_STORE_CODE, [
            'description' => 'Scope Test Store Description',
        ], $token);
        expect($fast['status'])->toBe(200);
        expect($fast['json']['storeOverrides'])->toBe(['description', 'short_description']);

        $revert = apiPut('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE, [
            'useDefault' => ['description', 'short_description'],
        ], $token);
        expect($revert['status'])->toBe(200);
        expect($revert['json']['storeOverrides'])->toBe([]);
        expect(productStoreValueCodes($productId, scopeTestStoreId()))->toBe([]);
    });

    it('keeps a plain update on the global scope', function (): void {
        $productId = (int) $GLOBALS['_scope_test_product_id'];
        $token = serviceToken(['products/write', 'products/read']);

        $update = apiPut("/api/rest/v2/products/{$productId}", [
            'shortDescription' => 'Scope Test Global Short Description',
        ], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['shortDescription'])->toBe('Scope Test Global Short Description');
        expect(productStoreValueCodes($productId, scopeTestStoreId()))->toBe([]);

        $read = apiGet('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE, $token);
        expect($read['json']['shortDescription'])->toBe('Scope Test Global Short Description');
        expect($read['json']['storeOverrides'])->toBe([]);
    });

    it('keeps the website value that another store view of the website has', function (): void {
        $productId = (int) $GLOBALS['_scope_test_product_id'];
        $token = serviceToken(['products/write', 'products/read']);
        $siblingStoreId = 1;
        expect((int) Mage::app()->getStore($siblingStoreId)->getWebsiteId())
            ->toBe((int) Mage::app()->getStore(scopeTestStoreId())->getWebsiteId());

        // A website-scope value that only the other store view has, as after a
        // store view was added to the website later.
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_write');
        $statusId = (int) Mage::getSingleton('eav/config')
            ->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'status')->getId();
        $adapter->insert($resource->getTableName('catalog_product_entity_int'), [
            'entity_type_id' => (int) Mage::getSingleton('eav/config')
                ->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getId(),
            'attribute_id' => $statusId,
            'store_id' => $siblingStoreId,
            'entity_id' => $productId,
            'value' => Mage_Catalog_Model_Product_Status::STATUS_DISABLED,
        ]);

        $update = apiPut('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE, [
            'name' => 'Scope Test Store Name',
        ], $token);
        expect($update['status'])->toBe(200);
        // The save writes the website value to the store view, and does not delete it.
        expect($update['json']['storeOverrides'])->toBe(['name', 'status']);
        expect($update['json']['status'])->toBe('disabled');
        expect(productStoreValueCodes($productId, $siblingStoreId))->toContain('status');

        $revert = apiPut('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE, [
            'useDefault' => ['name', 'status'],
        ], $token);
        expect($revert['status'])->toBe(200);
        expect($revert['json']['storeOverrides'])->toBe([]);
        expect(productStoreValueCodes($productId, $siblingStoreId))->not->toContain('status');
    });

    it('is null without a store view context', function (): void {
        $productId = (int) $GLOBALS['_scope_test_product_id'];
        $token = serviceToken(['products/read']);

        $read = apiGet("/api/rest/v2/products/{$productId}", $token);
        expect($read['status'])->toBe(200);
        expect($read['json']['storeOverrides'] ?? null)->toBeNull();

        $admin = apiGet("/api/rest/v2/products/{$productId}?store=admin", $token);
        expect($admin['status'])->toBe(200);
        expect($admin['json']['storeOverrides'] ?? null)->toBeNull();
    });

    it('is not given to guest and customer callers', function (): void {
        $productId = (int) $GLOBALS['_scope_test_product_id'];
        $token = serviceToken(['products/write']);

        // An override exists, so only the caller decides whether the field is set.
        $update = apiPut('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE, [
            'name' => 'Scope Test Store Name',
        ], $token);
        expect($update['status'])->toBe(200);

        $guest = apiGet('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE);
        expect($guest['status'])->toBe(200);
        expect($guest['json']['storeOverrides'] ?? null)->toBeNull();

        $customer = apiGet('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE, customerToken());
        expect($customer['status'])->toBe(200);
        expect($customer['json']['storeOverrides'] ?? null)->toBeNull();

        $revert = apiPut('/api/rest/v2/products/' . $productId . '?store=' . SCOPE_STORE_CODE, [
            'useDefault' => ['name'],
        ], $token);
        expect($revert['status'])->toBe(200);
    });

});

describe('Admin-scope reads (?store=admin)', function (): void {

    it('returns raw global values for backend tokens', function (): void {
        $productId = (int) $GLOBALS['_scope_test_product_id'];
        $token = serviceToken(['products/read', 'products/write']);

        $read = apiGet("/api/rest/v2/products/{$productId}?store=admin", $token);
        expect($read['status'])->toBe(200);
        expect($read['json']['name'])->toBe('Scope Test Global Name');
    });

    it('is denied for anonymous and customer callers', function (): void {
        $productId = (int) $GLOBALS['_scope_test_product_id'];

        $anonymous = apiGet("/api/rest/v2/products/{$productId}?store=admin");
        expect($anonymous['status'])->toBe(401);

        $customer = apiGet("/api/rest/v2/products/{$productId}?store=admin", customerToken());
        expect($customer['status'])->toBe(403);
    });

});
