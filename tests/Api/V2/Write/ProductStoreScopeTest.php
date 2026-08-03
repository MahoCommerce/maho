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
        ->setIsActive(1)
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
