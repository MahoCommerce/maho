<?php

/**
 * Maho
 *
 * @category   Tests
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Blog Helper Data', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('blog');
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
        expect($url)->toContain('blog');
    });

    test('shouldShowInNavigation works with different scenarios', function () {
        $currentStore = Mage::app()->getStore();

        // Clean up any existing posts that might affect the test
        $existingPosts = Mage::getResourceModel('blog/post_collection');
        $existingPostIds = [];
        foreach ($existingPosts as $post) {
            $existingPostIds[] = $post->getId();
        }

        // Initially should be false when no visible posts exist
        $initialResult = $this->helper->shouldShowInNavigation();
        expect($initialResult)->toBeBool();

        // Create an active post with past publish date - should make navigation visible
        $activePost = Mage::getModel('blog/post');
        $activePost->setTitle('Unit Test Active Post');
        $activePost->setContent('Test content');
        $activePost->setIsActive(1);
        $activePost->setPublishDate('2025-01-01'); // Past date
        $activePost->setStores([$currentStore->getId()]);
        $activePost->save();

        // Now navigation should be visible
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // Create an inactive post - shouldn't affect navigation
        $inactivePost = Mage::getModel('blog/post');
        $inactivePost->setTitle('Unit Test Inactive Post');
        $inactivePost->setContent('Test content');
        $inactivePost->setIsActive(0); // Inactive
        $inactivePost->setPublishDate('2025-01-01');
        $inactivePost->setStores([$currentStore->getId()]);
        $inactivePost->save();

        // Should still be true (active post still exists)
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // Create a future post - shouldn't make navigation visible by itself
        $futurePost = Mage::getModel('blog/post');
        $futurePost->setTitle('Unit Test Future Post');
        $futurePost->setContent('Test content');
        $futurePost->setIsActive(1);
        $futurePost->setPublishDate('2025-12-31'); // Future date
        $futurePost->setStores([$currentStore->getId()]);
        $futurePost->save();

        // Should still be true (active past post still exists)
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // Remove the active past post
        $activePost->delete();

        // Now should be false (only inactive and future posts remain)
        expect($this->helper->shouldShowInNavigation())->toBeFalse();

        // Create a post with no publish date - should make navigation visible
        $noDatePost = Mage::getModel('blog/post');
        $noDatePost->setTitle('Unit Test No Date Post');
        $noDatePost->setContent('Test content');
        $noDatePost->setIsActive(1);
        $noDatePost->setPublishDate(null); // No date
        $noDatePost->setStores([$currentStore->getId()]);
        $noDatePost->save();

        // Should be true again (post with no date is considered visible)
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // Clean up test posts
        $inactivePost->delete();
        $futurePost->delete();
        $noDatePost->delete();
    });

    test('hasVisiblePosts correctly identifies visible posts', function () {
        $currentStore = Mage::app()->getStore();

        // Initially, check baseline state
        $initialResult = $this->helper->hasVisiblePosts();
        expect($initialResult)->toBeBool();

        // Create posts with different visibility conditions
        $testPosts = [];

        // 1. Active post with past publish date - VISIBLE
        $visiblePost = Mage::getModel('blog/post');
        $visiblePost->setTitle('Visible Test Post');
        $visiblePost->setContent('Test content');
        $visiblePost->setIsActive(1);
        $visiblePost->setPublishDate('2025-01-01');
        $visiblePost->setStores([$currentStore->getId()]);
        $visiblePost->save();
        $testPosts[] = $visiblePost;

        expect($this->helper->hasVisiblePosts())->toBeTrue();

        // 2. Inactive post - NOT VISIBLE
        $inactivePost = Mage::getModel('blog/post');
        $inactivePost->setTitle('Inactive Test Post');
        $inactivePost->setContent('Test content');
        $inactivePost->setIsActive(0);
        $inactivePost->setPublishDate('2025-01-01');
        $inactivePost->setStores([$currentStore->getId()]);
        $inactivePost->save();
        $testPosts[] = $inactivePost;

        // Should still be true (visible post exists)
        expect($this->helper->hasVisiblePosts())->toBeTrue();

        // 3. Future post - NOT VISIBLE
        $futurePost = Mage::getModel('blog/post');
        $futurePost->setTitle('Future Test Post');
        $futurePost->setContent('Test content');
        $futurePost->setIsActive(1);
        $futurePost->setPublishDate('2025-12-31');
        $futurePost->setStores([$currentStore->getId()]);
        $futurePost->save();
        $testPosts[] = $futurePost;

        // Should still be true (visible post still exists)
        expect($this->helper->hasVisiblePosts())->toBeTrue();

        // Remove the visible post
        $visiblePost->delete();

        // Now should be false (only inactive and future posts remain)
        expect($this->helper->hasVisiblePosts())->toBeFalse();

        // 4. Post with no publish date - VISIBLE
        $noDatePost = Mage::getModel('blog/post');
        $noDatePost->setTitle('No Date Test Post');
        $noDatePost->setContent('Test content');
        $noDatePost->setIsActive(1);
        $noDatePost->setPublishDate(null);
        $noDatePost->setStores([$currentStore->getId()]);
        $noDatePost->save();
        $testPosts[] = $noDatePost;

        // Should be true again
        expect($this->helper->hasVisiblePosts())->toBeTrue();

        // Clean up
        foreach ($testPosts as $post) {
            if ($post->getId()) {
                $post->delete();
            }
        }
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
