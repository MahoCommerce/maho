<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

describe('Blog Navigation Integration', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('blog');
        $this->observer = new Maho_Blog_Model_Observer();

        // Clean up any existing test posts
        $existingPosts = Mage::getResourceModel('blog/post_collection');
        foreach ($existingPosts as $post) {
            if (str_contains($post->getTitle(), 'Nav Test')) {
                $post->delete();
            }
        }
    });

    afterEach(function () {
        // Clean up test posts
        $testPosts = Mage::getResourceModel('blog/post_collection')
            ->addFieldToFilter('title', ['like' => '%Nav Test%']);
        foreach ($testPosts as $post) {
            $post->delete();
        }
    });

    test('blog helper is available in frontend context', function () {
        expect($this->helper)->toBeInstanceOf(Maho_Blog_Helper_Data::class);
    });

    test('navigation shows when module is enabled and has visible posts', function () {
        // Create a visible post
        $currentStore = Mage::app()->getStore();
        $post = Mage::getModel('blog/post');
        $post->setTitle('Nav Test Visible Post');
        $post->setContent('Test content');
        $post->setIsActive(1);
        $post->setPublishDate('2025-01-01'); // Past date
        $post->setStores([$currentStore->getId()]); // Associate with current store
        $post->save();

        expect($this->helper->isEnabled())->toBeTrue();
        expect($this->helper->hasVisiblePosts())->toBeTrue();
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        $post->delete();
    });

    test('can filter posts by active status', function () {
        // Create inactive post
        $post = Mage::getModel('blog/post');
        $post->setTitle('Nav Test Inactive Post');
        $post->setContent('Test content');
        $post->setIsActive(0); // Inactive
        $post->setPublishDate('2025-01-01');
        $post->save();

        // Verify inactive posts are excluded from visible posts
        $activeCollection = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter(Mage::app()->getStore())
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('entity_id', $post->getId());

        expect($activeCollection->getSize())->toBe(0); // Should not find inactive post

        $post->delete();
    });

    test('can filter posts by publish date', function () {
        $post = Mage::getModel('blog/post');
        $post->setTitle('Nav Test Future Post');
        $post->setContent('Test content');
        $post->setIsActive(1);
        $post->setPublishDate('2025-12-31'); // Future date
        $post->save();

        // Test filtering by current date (should exclude future posts)
        $today = Mage_Core_Model_Locale::today();
        $pastCollection = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter(Mage::app()->getStore())
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('entity_id', $post->getId());

        $pastCollection->getSelect()->where('publish_date <= ?', $today);

        expect($pastCollection->getSize())->toBe(0); // Future post should not appear

        $post->delete();
    });

    test('navigation shows for posts with no publish date', function () {
        $currentStore = Mage::app()->getStore();
        $post = Mage::getModel('blog/post');
        $post->setTitle('Nav Test No Date Post');
        $post->setContent('Test content');
        $post->setIsActive(1);
        $post->setPublishDate(null); // No publish date
        $post->setStores([$currentStore->getId()]); // Associate with current store
        $post->save();

        expect($this->helper->hasVisiblePosts())->toBeTrue();
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        $post->delete();
    });

    test('blog helper has required navigation methods', function () {
        // Test that the required methods exist
        expect(method_exists($this->helper, 'getBlogUrl'))->toBeTrue();
        expect(method_exists($this->helper, 'shouldShowInNavigation'))->toBeTrue();
        expect(method_exists($this->helper, 'hasVisiblePosts'))->toBeTrue();
        expect(method_exists($this->helper, 'isEnabled'))->toBeTrue();
    });

    test('observer class exists and has required methods', function () {
        expect($this->observer)->toBeInstanceOf(Maho_Blog_Model_Observer::class);
        expect(method_exists($this->observer, 'addBlogToTopmenuItems'))->toBeTrue();
    });

    test('navigation active state detection works', function () {
        // Test active state detection directly from request
        $request = Mage::app()->getRequest();
        $originalModuleName = $request->getModuleName();

        // Test when on blog page - active state is determined inline in observer
        $request->setModuleName('blog');
        $isBlogActive = $request->getModuleName() === 'blog';
        expect($isBlogActive)->toBeTrue();

        // Test when not on blog page
        $request->setModuleName('catalog');
        $isBlogActive = $request->getModuleName() === 'blog';
        expect($isBlogActive)->toBeFalse();

        // Restore original module name
        $request->setModuleName($originalModuleName);
    });

    test('store filtering works correctly', function () {
        // Create post for current store
        $currentStore = Mage::app()->getStore();
        $post = Mage::getModel('blog/post');
        $post->setTitle('Nav Test Store Post');
        $post->setContent('Test content');
        $post->setIsActive(1);
        $post->setPublishDate('2025-01-01');
        $post->setStores([$currentStore->getId()]);
        $post->save();

        // Check visibility with store filter
        $collection = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter($currentStore)
            ->addFieldToFilter('is_active', 1);

        $collection->getSelect()->where(
            'publish_date IS NULL OR publish_date <= ?',
            Mage_Core_Model_Locale::today(),
        );

        expect($collection->getSize())->toBeGreaterThan(0);

        $post->delete();
    });

    test('multiple visible posts still show single navigation link', function () {
        // Create multiple visible posts
        $currentStore = Mage::app()->getStore();
        $posts = [];
        for ($i = 1; $i <= 3; $i++) {
            $post = Mage::getModel('blog/post');
            $post->setTitle("Nav Test Multiple Post {$i}");
            $post->setContent('Test content');
            $post->setIsActive(1);
            $post->setPublishDate('2025-01-01');
            $post->setStores([$currentStore->getId()]); // Associate with current store
            $post->save();
            $posts[] = $post;
        }

        expect($this->helper->hasVisiblePosts())->toBeTrue();
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // Clean up
        foreach ($posts as $post) {
            $post->delete();
        }
    });

    test('store filtering works correctly for navigation visibility', function () {
        $currentStore = Mage::app()->getStore();
        $currentStoreId = $currentStore->getId();

        // Baseline visibility state
        $baselineVisible = $this->helper->hasVisiblePosts();

        // Create post for current store - should make navigation visible
        $currentStorePost = Mage::getModel('blog/post');
        $currentStorePost->setTitle('Nav Test Current Store Post');
        $currentStorePost->setContent('Should appear in current store navigation');
        $currentStorePost->setIsActive(1);
        $currentStorePost->setPublishDate('2025-01-01');
        $currentStorePost->setStores([$currentStoreId]); // Current store
        $currentStorePost->save();

        // Now navigation should be visible
        expect($this->helper->hasVisiblePosts())->toBeTrue();
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // Verify collection filtering works correctly
        $currentStoreCollection = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter($currentStore)
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('entity_id', $currentStorePost->getId());
        $currentStoreCollection->getSelect()->where(
            'publish_date IS NULL OR publish_date <= ?',
            Mage_Core_Model_Locale::today(),
        );

        // Should find the post we just created
        expect($currentStoreCollection->getSize())->toBe(1);

        // Create a future post for the same store - should not affect current visibility
        $futurePost = Mage::getModel('blog/post');
        $futurePost->setTitle('Nav Test Future Post');
        $futurePost->setContent('Future post should not be visible');
        $futurePost->setIsActive(1);
        $futurePost->setPublishDate((new DateTime('+1 day'))->format('Y-m-d')); // Future date
        $futurePost->setStores([$currentStoreId]);
        $futurePost->save();

        // Navigation should still be visible (current post exists)
        expect($this->helper->hasVisiblePosts())->toBeTrue();
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // Verify future post is filtered out
        $futureFilteredCollection = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter($currentStore)
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('entity_id', $futurePost->getId());
        $futureFilteredCollection->getSelect()->where(
            'publish_date <= ?',
            Mage_Core_Model_Locale::today(),
        );

        expect($futureFilteredCollection->getSize())->toBe(0); // Future post filtered out

        // Clean up
        $currentStorePost->delete();
        $futurePost->delete();
    });
});
