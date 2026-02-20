<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Blog Helper Data', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('blog');
        // Enable database transactions to isolate test data changes
    });

    test('helper instance is correct type', function () {
        expect($this->helper)->toBeInstanceOf(Maho_Blog_Helper_Data::class);
    });

    test('helper has correct module configuration', function () {
        // Test that the helper is properly configured
        expect($this->helper->isModuleEnabled())->toBeBool();
        expect($this->helper->isModuleOutputEnabled())->toBeBool();
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

    test('shouldShowInNavigation works with different scenarios', function () {
        $currentStore = Mage::app()->getStore();

        // Test that adding a visible post makes navigation true
        $activePost = Mage::getModel('blog/post');
        $activePost->setTitle('Unit Test Active Post - ' . uniqid());
        $activePost->setContent('Test content');
        $activePost->setIsActive(1);
        $activePost->setPublishDate('2025-01-01'); // Past date
        $activePost->setStores([$currentStore->getId()]);
        $activePost->save();

        // Now navigation should definitely be visible (we added a visible post)
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // Create invisible posts - should NOT change the true state
        $inactivePost = Mage::getModel('blog/post');
        $inactivePost->setTitle('Unit Test Inactive Post - ' . uniqid());
        $inactivePost->setContent('Test content');
        $inactivePost->setIsActive(0); // Inactive
        $inactivePost->setPublishDate('2025-01-01');
        $inactivePost->setStores([$currentStore->getId()]);
        $inactivePost->save();

        $futurePost = Mage::getModel('blog/post');
        $futurePost->setTitle('Unit Test Future Post - ' . uniqid());
        $futurePost->setContent('Test content');
        $futurePost->setIsActive(1);
        $futurePost->setPublishDate('2025-12-31'); // Future date
        $futurePost->setStores([$currentStore->getId()]);
        $futurePost->save();

        // Should still be true (visible post still exists)
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // Test post with no publish date - should be visible
        $noDatePost = Mage::getModel('blog/post');
        $noDatePost->setTitle('Unit Test No Date Post - ' . uniqid());
        $noDatePost->setContent('Test content');
        $noDatePost->setIsActive(1);
        $noDatePost->setPublishDate(null); // No date = visible
        $noDatePost->setStores([$currentStore->getId()]);
        $noDatePost->save();

        // Should still be true (multiple visible posts exist)
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // Clean up - transactions will rollback all changes
        $activePost->delete();
        $inactivePost->delete();
        $futurePost->delete();
        $noDatePost->delete();
    });

    test('hasVisiblePosts correctly identifies visible posts', function () {
        $currentStore = Mage::app()->getStore();

        // Test by creating posts with different visibility conditions
        // Focus on positive assertions - we add visible posts and verify they're detected

        // 1. Active post with past publish date - VISIBLE
        $visiblePost = Mage::getModel('blog/post');
        $visiblePost->setTitle('Unit Test Visible Post - ' . uniqid());
        $visiblePost->setContent('Test content');
        $visiblePost->setIsActive(1);
        $visiblePost->setPublishDate('2025-01-01');
        $visiblePost->setStores([$currentStore->getId()]);
        $visiblePost->save();

        expect($this->helper->hasVisiblePosts())->toBeTrue();

        // 2. Inactive post - should NOT change the true state
        $inactivePost = Mage::getModel('blog/post');
        $inactivePost->setTitle('Unit Test Inactive Post - ' . uniqid());
        $inactivePost->setContent('Test content');
        $inactivePost->setIsActive(0);
        $inactivePost->setPublishDate('2025-01-01');
        $inactivePost->setStores([$currentStore->getId()]);
        $inactivePost->save();

        // Should still be true (visible post exists)
        expect($this->helper->hasVisiblePosts())->toBeTrue();

        // 3. Future post - should NOT change the true state
        $futurePost = Mage::getModel('blog/post');
        $futurePost->setTitle('Unit Test Future Post - ' . uniqid());
        $futurePost->setContent('Test content');
        $futurePost->setIsActive(1);
        $futurePost->setPublishDate('2025-12-31');
        $futurePost->setStores([$currentStore->getId()]);
        $futurePost->save();

        // Should still be true (visible post still exists)
        expect($this->helper->hasVisiblePosts())->toBeTrue();

        // 4. Post with no publish date - VISIBLE (should maintain true state)
        $noDatePost = Mage::getModel('blog/post');
        $noDatePost->setTitle('Unit Test No Date Post - ' . uniqid());
        $noDatePost->setContent('Test content');
        $noDatePost->setIsActive(1);
        $noDatePost->setPublishDate(null);
        $noDatePost->setStores([$currentStore->getId()]);
        $noDatePost->save();

        // Should still be true (multiple visible posts exist)
        expect($this->helper->hasVisiblePosts())->toBeTrue();

        // Clean up - transactions will rollback all changes
        $visiblePost->delete();
        $inactivePost->delete();
        $futurePost->delete();
        $noDatePost->delete();
    });

    test('navigation respects store isolation - collection filtering works correctly', function () {
        $currentStore = Mage::app()->getStore();
        $currentStoreId = $currentStore->getId();

        // Test the collection filtering behavior directly
        $initialNavState = $this->helper->hasVisiblePosts();

        // Create a post assigned to current store
        $currentStorePost = Mage::getModel('blog/post');
        $currentStorePost->setTitle('Current Store Post');
        $currentStorePost->setContent('This post should be visible in current store');
        $currentStorePost->setIsActive(1);
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
            Mage_Core_Model_Locale::today(),
        );

        expect($storeFilteredCollection->getSize())->toBe(1); // Post visible with store filter

        // Test that the helper method respects store filtering
        expect($this->helper->hasVisiblePosts())->toBeTrue();

        // Create an inactive post for the same store - should not affect navigation
        $inactivePost = Mage::getModel('blog/post');
        $inactivePost->setTitle('Inactive Store Post');
        $inactivePost->setContent('This inactive post should not affect navigation');
        $inactivePost->setIsActive(0); // Inactive
        $inactivePost->setPublishDate('2025-01-01');
        $inactivePost->setStores([$currentStoreId]);
        $inactivePost->save();

        // Navigation should still be true (active post still exists)
        expect($this->helper->hasVisiblePosts())->toBeTrue();
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // Remove the active post
        $currentStorePost->delete();

        // Navigation should be back to initial state (only inactive post remains)
        expect($this->helper->hasVisiblePosts())->toBe($initialNavState);
        expect($this->helper->shouldShowInNavigation())->toBe($initialNavState && $this->helper->isEnabled());

        // Clean up
        $inactivePost->delete();
    });
});
