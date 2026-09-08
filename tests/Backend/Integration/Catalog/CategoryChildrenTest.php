<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

test('getChildren() returns the active children of a category on every database', function () {
    $store = Mage::app()->getDefaultStoreView();
    $parentId = Mage::getResourceModel('catalog/category_collection')
        ->addFieldToFilter('level', 2)
        ->addFieldToFilter('children_count', ['gt' => 0])
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    if (!$parentId) {
        $this->markTestSkipped('No category with children in the sample data.');
    }

    /** @var Mage_Catalog_Model_Category $parent */
    $parent = Mage::getModel('catalog/category')->setStoreId($store->getId())->load($parentId);
    $expected = Mage::getResourceModel('catalog/category_collection')
        ->setStoreId($store->getId())
        ->addFieldToFilter('parent_id', $parentId)
        ->addAttributeToFilter('is_active', 1)
        ->getAllIds();

    expect($expected)->not->toBeEmpty();
    expect(array_map(intval(...), $parent->getResource()->getChildren($parent, false)))
        ->toEqualCanonicalizing(array_map(intval(...), $expected));
    expect($parent->getChildrenCategories()->count())->toBe(count($expected));
});
