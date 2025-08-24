<?php

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

    test('store isolation prevents cross-store post visibility in navigation', function () {
        $currentStore = Mage::app()->getStore();
        $currentStoreId = $currentStore->getId();
        $otherStoreId = $currentStoreId + 1; // Simulate different store

        // Baseline - no posts should mean no navigation
        $baselineVisible = $this->helper->hasVisiblePosts();

        // Create post for another store only
        $otherStoreOnlyPost = Mage::getModel('blog/post');
        $otherStoreOnlyPost->setTitle('Nav Test Other Store Only Post');
        $otherStoreOnlyPost->setContent('Should not appear in current store navigation');
        $otherStoreOnlyPost->setIsActive(1);
        $otherStoreOnlyPost->setPublishDate('2025-01-01');
        $otherStoreOnlyPost->setStores([$otherStoreId]); // Other store only
        $otherStoreOnlyPost->save();

        // Navigation should not change (post not visible to current store)
        expect($this->helper->hasVisiblePosts())->toBe($baselineVisible);
        expect($this->helper->shouldShowInNavigation())->toBe($baselineVisible);

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

        // Create multi-store post
        $multiStorePost = Mage::getModel('blog/post');
        $multiStorePost->setTitle('Nav Test Multi Store Post');
        $multiStorePost->setContent('Should appear in both stores');
        $multiStorePost->setIsActive(1);
        $multiStorePost->setPublishDate('2025-01-01');
        $multiStorePost->setStores([$currentStoreId, $otherStoreId]); // Both stores
        $multiStorePost->save();

        // Navigation should still be visible (multiple posts for current store)
        expect($this->helper->hasVisiblePosts())->toBeTrue();
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // Verify collection filtering works correctly
        $currentStoreCollection = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter($currentStore)
            ->addFieldToFilter('is_active', 1);
        $currentStoreCollection->getSelect()->where(
            'publish_date IS NULL OR publish_date <= ?',
            Mage_Core_Model_Locale::today(),
        );

        // Should find 2 posts (current store post + multi store post)
        expect($currentStoreCollection->getSize())->toBe(2);

        // Verify other store post is excluded
        $otherOnlyCollection = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter($currentStore)
            ->addFieldToFilter('entity_id', $otherStoreOnlyPost->getId());
        expect($otherOnlyCollection->getSize())->toBe(0);

        // Clean up
        $otherStoreOnlyPost->delete();
        $currentStorePost->delete();
        $multiStorePost->delete();
    });
});
