<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

afterEach(function () {
    $collection = Mage::getModel('catalog/category')->getCollection()
        ->addAttributeToFilter('entity_id', ['gt' => 2]);

    foreach ($collection as $category) {
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
