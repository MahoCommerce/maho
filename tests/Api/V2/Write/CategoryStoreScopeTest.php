<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ApiV2Helper;

/**
 * API v2 store-scoped category write tests.
 *
 * Verifies that category updates follow the same EAV write-scope contract as
 * products: global writes without ?store=, store-view overrides with it, and
 * useDefault to drop an override.
 *
 * @group write
 */

const CAT_SCOPE_STORE_CODE = 'apitest_scope_cat';

function catScopeStoreId(): int
{
    return (int) ($GLOBALS['_cat_scope_store_id'] ?? 0);
}

/** Read the raw name rows for a category keyed by store_id, straight from EAV. */
function categoryNameRowsByStore(int $categoryId): array
{
    ApiV2Helper::ensureMahoBootstrapped();
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $attributeId = (int) Mage::getSingleton('eav/config')
        ->getAttribute(Mage_Catalog_Model_Category::ENTITY, 'name')
        ->getId();

    $select = $adapter->select()
        ->from($resource->getTableName('catalog_category_entity_varchar'), ['store_id', 'value'])
        ->where('entity_id = ?', $categoryId)
        ->where('attribute_id = ?', $attributeId);

    $rows = [];
    foreach ($adapter->fetchAll($select) as $row) {
        $rows[(int) $row['store_id']] = $row['value'];
    }
    return $rows;
}

beforeAll(function (): void {
    ApiV2Helper::ensureMahoBootstrapped();

    $existing = Mage::getModel('core/store')->load(CAT_SCOPE_STORE_CODE, 'code');
    if ($existing->getId()) {
        $GLOBALS['_cat_scope_store_id'] = (int) $existing->getId();
        return;
    }

    $website = Mage::app()->getWebsite(1);
    $store = Mage::getModel('core/store');
    $store->setCode(CAT_SCOPE_STORE_CODE)
        ->setWebsiteId((int) $website->getId())
        ->setGroupId((int) $website->getDefaultGroupId())
        ->setName('API Category Scope Test Store')
        ->setIsActive(1)
        ->setSortOrder(98)
        ->save();
    $GLOBALS['_cat_scope_store_id'] = (int) $store->getId();

    Mage::app()->cleanCache();
});

afterAll(function (): void {
    ApiV2Helper::ensureMahoBootstrapped();
    $store = Mage::getModel('core/store')->load(CAT_SCOPE_STORE_CODE, 'code');
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

describe('Category write scope (REST)', function (): void {

    it('writes the global scope on a plain update', function (): void {
        $token = serviceToken(['categories/write', 'categories/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/categories', [
            'name' => "Scope Category {$suffix}",
            'isActive' => true,
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $categoryId = (int) $create['json']['id'];
        trackCreated('category', $categoryId);
        $GLOBALS['_cat_scope_category_id'] = $categoryId;

        $update = apiPut("/api/rest/v2/categories/{$categoryId}", [
            'name' => 'Scope Category Global Name',
        ], $token);
        expect($update['status'])->toBe(200);

        $rows = categoryNameRowsByStore($categoryId);
        expect($rows[0] ?? null)->toBe('Scope Category Global Name');
        expect($rows)->not->toHaveKey(catScopeStoreId());
    });

    it('writes a store override with ?store= and reverts it with useDefault', function (): void {
        $categoryId = (int) $GLOBALS['_cat_scope_category_id'];
        $token = serviceToken(['categories/write', 'categories/delete']);

        $update = apiPut('/api/rest/v2/categories/' . $categoryId . '?store=' . CAT_SCOPE_STORE_CODE, [
            'name' => 'Scope Category Store Name',
        ], $token);
        expect($update['status'])->toBe(200);

        $rows = categoryNameRowsByStore($categoryId);
        expect($rows[0] ?? null)->toBe('Scope Category Global Name');
        expect($rows[catScopeStoreId()] ?? null)->toBe('Scope Category Store Name');

        $revert = apiPut('/api/rest/v2/categories/' . $categoryId . '?store=' . CAT_SCOPE_STORE_CODE, [
            'useDefault' => ['name'],
        ], $token);
        expect($revert['status'])->toBe(200);

        $rows = categoryNameRowsByStore($categoryId);
        expect($rows)->not->toHaveKey(catScopeStoreId());
        expect($rows[0] ?? null)->toBe('Scope Category Global Name');
    });

    it('rejects useDefault without a store context', function (): void {
        $categoryId = (int) $GLOBALS['_cat_scope_category_id'];
        $token = serviceToken(['categories/write']);

        $update = apiPut("/api/rest/v2/categories/{$categoryId}", [
            'useDefault' => ['name'],
        ], $token);
        expect($update['status'])->toBe(400);
    });

});
