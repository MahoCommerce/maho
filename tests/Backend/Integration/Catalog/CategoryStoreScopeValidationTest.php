<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

beforeEach(function () {
    // Snapshot the categories that already exist (sample data + anything a prior
    // test left behind) so afterEach only removes what THIS test creates.
    $this->preExistingCategoryIds = array_map('intval', Mage::getModel('catalog/category')->getCollection()->getAllIds());
});

afterEach(function () {
    // Clean up ONLY the categories this test created. Deleting every category
    // with entity_id > 2 would also remove the sample-data categories and cascade
    // into catalog_category_product, corrupting the shared test database for the
    // API integration tests that run later in the same suite.
    $preExisting = array_flip($this->preExistingCategoryIds ?? []);
    $collection = Mage::getModel('catalog/category')->getCollection()
        ->addAttributeToFilter('entity_id', ['gt' => 2]);

    foreach ($collection as $category) {
        if (isset($preExisting[(int) $category->getId()])) {
            continue;
        }
        try {
            $category->delete();
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }
});

function createDefaultScopeCategory(): Mage_Catalog_Model_Category
{
    $category = Mage::getModel('catalog/category');
    $category->setName('Default Name')
        ->setUrlKey('use-default-validation-' . uniqid())
        ->setIsActive(1)
        ->setDisplayMode(Mage_Catalog_Model_Category::DM_PRODUCT)
        ->setIsAnchor(0)
        ->setAvailableSortBy(['position'])
        ->setDefaultSortBy('position')
        ->setParentId(2)
        ->setStoreId(0)
        ->save();

    return $category;
}

it('does not flag a required attribute as missing when its store value falls back to default (#994)', function () {
    $category = createDefaultScopeCategory();

    // Simulate editing in store view 1 with "Use Default Value" checked for name
    $storeCategory = Mage::getModel('catalog/category');
    $storeCategory->setStoreId(1);
    $storeCategory->load($category->getId());
    $storeCategory->setData('name', false);

    expect($storeCategory->validate())->toBeTrue();
});

it('still flags a missing required attribute in the default scope', function () {
    $category = createDefaultScopeCategory();

    $category->setStoreId(0);
    $category->setData('name', false);

    expect($category->validate())->not->toBeTrue();
});

it('saves a category in a store view inheriting name from the default scope', function () {
    $category = createDefaultScopeCategory();

    $storeCategory = Mage::getModel('catalog/category');
    $storeCategory->setStoreId(1);
    $storeCategory->load($category->getId());
    $storeCategory->setData('name', false);
    $storeCategory->save();

    $reloaded = Mage::getModel('catalog/category');
    $reloaded->setStoreId(1);
    $reloaded->load($category->getId());

    expect($reloaded->getName())->toBe('Default Name');
});
