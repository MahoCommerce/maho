<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Blog Helper Data', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('blog');
        // Enable database transactions to isolate test data changes
    });

    test('isEnabled checks module status', function () {
        // This should return true in test environment
        expect($this->helper->isEnabled())->toBeTrue();
    });

    test('getBlogUrl generates correct URL', function () {
        $url = $this->helper->getBlogUrl();

        expect($url)->toBeString();

        $expectedPrefix = $this->helper->getBlogUrlPrefix();
        expect($url)->toContain($expectedPrefix);
    });

    test('getCategoryUrl follows the trailing slash behavior of the store', function (string $mode, string $expectedEnd) {
        $store = Mage::app()->getStore();
        $previousMode = $store->getConfig('web/url/trailing_slash_behavior');
        $store->setConfig('web/url/trailing_slash_behavior', $mode);
        try {
            $url = $this->helper->getCategoryUrl(Mage::getModel('blog/category')->setUrlKey('news'));
        } finally {
            $store->setConfig('web/url/trailing_slash_behavior', $previousMode);
        }

        expect($url)->toEndWith($expectedEnd);
    })->with([
        'add' => [Mage_Adminhtml_Model_System_Config_Source_Catalog_Trailingslash::ADD_TRAILING_SLASH, '/news/'],
        'remove' => [Mage_Adminhtml_Model_System_Config_Source_Catalog_Trailingslash::REMOVE_TRAILING_SLASH, '/news'],
    ]);

    test('navigation respects store isolation - collection filtering works correctly', function () {
        $currentStore = Mage::app()->getStore();
        $currentStoreId = $currentStore->getId();

        // Test the collection filtering behavior directly
        $initialNavState = $this->helper->hasVisiblePosts();

        // Create a post assigned to current store
        $currentStorePost = Mage::getModel('blog/post');
        $currentStorePost->setTitle('Current Store Post');
        $currentStorePost->setContent('This post should be visible in current store');
        $currentStorePost->setIsActive(true);
        $currentStorePost->setPublishDate('2025-01-01');
        $currentStorePost->setStores([$currentStoreId]);
        $currentStorePost->save();

        // Navigation should now show (post visible to current store)
        expect($this->helper->hasVisiblePosts())->toBeTrue();
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // Test that store filter works correctly
        $storeFilteredCollection = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter($currentStore)
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('entity_id', $currentStorePost->getId());
        $storeFilteredCollection->getSelect()->where(
            'publish_date IS NULL OR publish_date <= ?',
            Mage::app()->getLocale()->todayUtc(),
        );

        expect($storeFilteredCollection->getSize())->toBe(1); // Post visible with store filter

        // Test that the helper method respects store filtering
        expect($this->helper->hasVisiblePosts())->toBeTrue();

        // Create an inactive post for the same store - should not affect navigation
        $inactivePost = Mage::getModel('blog/post');
        $inactivePost->setTitle('Inactive Store Post');
        $inactivePost->setContent('This inactive post should not affect navigation');
        $inactivePost->setIsActive(false); // Inactive
        $inactivePost->setPublishDate('2025-01-01');
        $inactivePost->setStores([$currentStoreId]);
        $inactivePost->save();

        // Navigation should still be true (active post still exists)
        expect($this->helper->hasVisiblePosts())->toBeTrue();
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // A post that is published later does not count as visible
        $futurePost = Mage::getModel('blog/post');
        $futurePost->setTitle('Future Store Post');
        $futurePost->setContent('This future post should not affect navigation');
        $futurePost->setIsActive(true);
        $futurePost->setPublishDate(Mage::app()->getLocale()->utcToStore()->modify('+1 year')->format(Mage_Core_Model_Locale::DATE_FORMAT));
        $futurePost->setStores([$currentStoreId]);
        $futurePost->save();

        $futureCollection = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter($currentStore)
            ->addPublishedFilter()
            ->addFieldToFilter('entity_id', $futurePost->getId());
        expect($futureCollection->getSize())->toBe(0);

        // Remove the active post
        $currentStorePost->delete();

        // Navigation should be back to initial state (only inactive post remains)
        expect($this->helper->hasVisiblePosts())->toBe($initialNavState);
        expect($this->helper->shouldShowInNavigation())->toBe($initialNavState && $this->helper->isEnabled());

        // Clean up
        $inactivePost->delete();
        $futurePost->delete();
    });

    test('hasVisiblePosts is false when no post is active', function () {
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $write->beginTransaction();
        try {
            $write->update(Mage::getResourceSingleton('blog/post')->getEntityTable(), ['is_active' => 0]);

            expect($this->helper->hasVisiblePosts())->toBeFalse();
            expect($this->helper->shouldShowInNavigation())->toBeFalse();
        } finally {
            $write->rollBack();
        }
    });
});
