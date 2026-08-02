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
const CAT_RESTRICT_WEBSITE_CODE = 'apitest_cat_rw';
const CAT_RESTRICT_STORE_CODE = 'apitest_cat_rs';

function catScopeStoreId(): int
{
    return (int) ($GLOBALS['_cat_scope_store_id'] ?? 0);
}

function catRestrictStoreId(): int
{
    return (int) ($GLOBALS['_cat_restrict_store_id'] ?? 0);
}

function catRestrictRootId(): int
{
    return (int) ($GLOBALS['_cat_restrict_root_id'] ?? 0);
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
    } else {
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
    }

    // A store whose group points at its OWN root category, so a token
    // restricted to it has a category tree disjoint from the default store's.
    $restrictStore = Mage::getModel('core/store')->load(CAT_RESTRICT_STORE_CODE, 'code');
    if (!$restrictStore->getId()) {
        $rootCategory = Mage::getModel('catalog/category');
        $rootCategory->setStoreId(0)
            ->setName('API Cat Restrict Root')
            ->setIsActive(1)
            ->setPath('1')
            ->save();

        $restrictWebsite = Mage::getModel('core/website')
            ->setCode(CAT_RESTRICT_WEBSITE_CODE)
            ->setName('API Cat Restrict Website')
            ->setSortOrder(97)
            ->save();
        $restrictGroup = Mage::getModel('core/store_group')
            ->setWebsiteId((int) $restrictWebsite->getId())
            ->setName('API Cat Restrict Group')
            ->setRootCategoryId((int) $rootCategory->getId())
            ->save();
        $restrictStore = Mage::getModel('core/store')
            ->setCode(CAT_RESTRICT_STORE_CODE)
            ->setWebsiteId((int) $restrictWebsite->getId())
            ->setGroupId((int) $restrictGroup->getId())
            ->setName('API Cat Restrict Store')
            ->setIsActive(1)
            ->setSortOrder(97)
            ->save();
        $restrictWebsite->setDefaultGroupId((int) $restrictGroup->getId())->save();
        $restrictGroup->setDefaultStoreId((int) $restrictStore->getId())->save();
        $GLOBALS['_cat_restrict_root_id'] = (int) $rootCategory->getId();
    } else {
        $GLOBALS['_cat_restrict_root_id'] = (int) Mage::app()
            ->getStore(CAT_RESTRICT_STORE_CODE)->getRootCategoryId();
    }
    $GLOBALS['_cat_restrict_store_id'] = (int) Mage::getModel('core/store')
        ->load(CAT_RESTRICT_STORE_CODE, 'code')->getId();

    Mage::app()->cleanCache();
});

afterAll(function (): void {
    ApiV2Helper::ensureMahoBootstrapped();
    Mage::register('isSecureArea', true);
    try {
        $store = Mage::getModel('core/store')->load(CAT_SCOPE_STORE_CODE, 'code');
        if ($store->getId()) {
            $store->delete();
        }
        foreach (['core/store' => CAT_RESTRICT_STORE_CODE, 'core/website' => CAT_RESTRICT_WEBSITE_CODE] as $model => $code) {
            $entity = Mage::getModel($model)->load($code, 'code');
            if ($entity->getId()) {
                $entity->delete();
            }
        }
        $group = Mage::getModel('core/store_group')->load('API Cat Restrict Group', 'name');
        if ($group->getId()) {
            $group->delete();
        }
        $rootId = catRestrictRootId();
        if ($rootId) {
            $root = Mage::getModel('catalog/category')->load($rootId);
            if ($root->getId()) {
                $root->delete();
            }
        }
    } catch (\Throwable) {
        // Ignore cleanup errors
    } finally {
        Mage::unregister('isSecureArea');
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

describe('Store-restricted category writes (REST)', function (): void {

    it('denies writes outside the token store root tree and allows them inside', function (): void {
        $suffix = substr(uniqid(), -8);

        // A category in the DEFAULT store's tree, created by an unrestricted token.
        $create = apiPost('/api/rest/v2/categories', [
            'name' => "Foreign Tree Category {$suffix}",
            'isActive' => true,
        ], serviceToken(['categories/write', 'categories/delete']));
        expect($create['status'])->toBeIn([200, 201]);
        $foreignCategoryId = (int) $create['json']['id'];
        trackCreated('category', $foreignCategoryId);

        $restricted = serviceToken(['categories/write', 'categories/delete'], [catRestrictStoreId()]);
        $store = '?store=' . CAT_RESTRICT_STORE_CODE;

        // Update, move-in and delete of a category outside the allowed root tree.
        expect(apiPut("/api/rest/v2/categories/{$foreignCategoryId}{$store}", [
            'name' => 'Hijacked',
        ], $restricted)['status'])->toBe(403);
        expect(apiDelete("/api/rest/v2/categories/{$foreignCategoryId}{$store}", $restricted)['status'])->toBe(403);

        // Create under the default store's root (outside the allowlist tree).
        $defaultRootId = (int) Mage::app()->getStore(1)->getRootCategoryId();
        expect(apiPost("/api/rest/v2/categories{$store}", [
            'name' => "Denied Create {$suffix}",
            'parentId' => $defaultRootId,
        ], $restricted)['status'])->toBe(403);

        // Positive control: the same token can create under its own root.
        $allowed = apiPost("/api/rest/v2/categories{$store}", [
            'name' => "Allowed Create {$suffix}",
            'parentId' => catRestrictRootId(),
            'isActive' => true,
        ], $restricted);
        expect($allowed['status'])->toBeIn([200, 201]);
        $ownCategoryId = (int) $allowed['json']['id'];
        trackCreated('category', $ownCategoryId);

        // Moving its own category into the foreign tree is denied too.
        expect(apiPut("/api/rest/v2/categories/{$ownCategoryId}{$store}", [
            'parentId' => $defaultRootId,
        ], $restricted)['status'])->toBe(403);

        // The unrestricted token remains able to update the foreign category.
        expect(apiPut("/api/rest/v2/categories/{$foreignCategoryId}", [
            'name' => "Foreign Tree Category {$suffix}",
        ], serviceToken(['categories/write']))['status'])->toBe(200);
    });

});
