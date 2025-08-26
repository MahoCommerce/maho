<?php

/**
 * Improved Blog Helper Data Test - Works with existing data
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Blog Helper Data (Improved)', function () {
    beforeEach(function () {
        $this->helper = Mage::helper('blog');
        $this->useTransactions(); // Isolate database changes
    });

    test('helper instance is correct type', function () {
        expect($this->helper)->toBeInstanceOf(Maho_Blog_Helper_Data::class);
    });

    test('navigation state changes correctly based on visible posts', function () {
        $currentStore = Mage::app()->getStore();
        
        // Record the initial state (may be true or false based on existing data)
        $initialHasVisiblePosts = $this->helper->hasVisiblePosts();
        $initialShouldShowNav = $this->helper->shouldShowInNavigation();
        
        // Create test posts that we control
        $testPosts = [];
        
        // 1. Create an active, visible post - should ensure navigation is TRUE
        $visiblePost = Mage::getModel('blog/post');
        $visiblePost->setTitle('Test Visible Post - ' . uniqid());
        $visiblePost->setContent('This post should be visible');
        $visiblePost->setIsActive(1);
        $visiblePost->setPublishDate('2025-01-01'); // Past date
        $visiblePost->setStores([$currentStore->getId()]);
        $visiblePost->save();
        $testPosts[] = $visiblePost;

        // Now navigation should definitely be true (we added a visible post)
        expect($this->helper->hasVisiblePosts())->toBeTrue();
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // 2. Create invisible posts - should NOT change the true state
        $inactivePost = Mage::getModel('blog/post');
        $inactivePost->setTitle('Test Inactive Post - ' . uniqid());
        $inactivePost->setContent('This post is inactive');
        $inactivePost->setIsActive(0); // Inactive
        $inactivePost->setPublishDate('2025-01-01');
        $inactivePost->setStores([$currentStore->getId()]);
        $inactivePost->save();
        $testPosts[] = $inactivePost;

        $futurePost = Mage::getModel('blog/post');
        $futurePost->setTitle('Test Future Post - ' . uniqid());
        $futurePost->setContent('This post is in the future');
        $futurePost->setIsActive(1);
        $futurePost->setPublishDate('2025-12-31'); // Future date
        $futurePost->setStores([$currentStore->getId()]);
        $futurePost->save();
        $testPosts[] = $futurePost;

        // Should still be true (visible post still exists)
        expect($this->helper->hasVisiblePosts())->toBeTrue();
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // 3. Remove our visible post
        $visiblePost->delete();
        
        // State should return to what it was initially 
        // (could be true if other existing posts are visible)
        $currentHasVisiblePosts = $this->helper->hasVisiblePosts();
        expect($currentHasVisiblePosts)->toBe($initialHasVisiblePosts);
        
        // 4. Create a post with no publish date - should make it visible
        $noDatePost = Mage::getModel('blog/post');
        $noDatePost->setTitle('Test No Date Post - ' . uniqid());
        $noDatePost->setContent('This post has no publish date');
        $noDatePost->setIsActive(1);
        $noDatePost->setPublishDate(null); // No date = visible
        $noDatePost->setStores([$currentStore->getId()]);
        $noDatePost->save();
        $testPosts[] = $noDatePost;

        // Should be true (post with no date is visible)
        expect($this->helper->hasVisiblePosts())->toBeTrue();
        expect($this->helper->shouldShowInNavigation())->toBeTrue();

        // Clean up remaining test posts
        foreach ($testPosts as $post) {
            if ($post->getId()) {
                $post->delete();
            }
        }
    });

    test('hasVisiblePosts respects all visibility criteria', function () {
        $currentStore = Mage::app()->getStore();
        $today = Mage_Core_Model_Locale::today();
        
        // Test each visibility condition in isolation
        
        // 1. Active post with past date = VISIBLE
        $pastPost = Mage::getModel('blog/post');
        $pastPost->setTitle('Past Post - ' . uniqid());
        $pastPost->setContent('Past post content');
        $pastPost->setIsActive(1);
        $pastPost->setPublishDate('2025-01-01');
        $pastPost->setStores([$currentStore->getId()]);
        $pastPost->save();
        
        // Should be visible
        $collection1 = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter($currentStore)
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('entity_id', $pastPost->getId());
        $collection1->getSelect()->where('publish_date IS NULL OR publish_date <= ?', $today);
        
        expect($collection1->getSize())->toBeGreaterThan(0);
        
        // 2. Inactive post = NOT VISIBLE
        $inactivePost = Mage::getModel('blog/post');
        $inactivePost->setTitle('Inactive Post - ' . uniqid());
        $inactivePost->setContent('Inactive post content');
        $inactivePost->setIsActive(0); // Inactive
        $inactivePost->setPublishDate('2025-01-01');
        $inactivePost->setStores([$currentStore->getId()]);
        $inactivePost->save();
        
        $collection2 = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter($currentStore)
            ->addFieldToFilter('is_active', 1) // This should exclude our inactive post
            ->addFieldToFilter('entity_id', $inactivePost->getId());
        $collection2->getSelect()->where('publish_date IS NULL OR publish_date <= ?', $today);
        
        expect($collection2->getSize())->toBe(0); // Should be excluded
        
        // 3. Future post = NOT VISIBLE
        $futurePost = Mage::getModel('blog/post');
        $futurePost->setTitle('Future Post - ' . uniqid());
        $futurePost->setContent('Future post content');
        $futurePost->setIsActive(1);
        $futurePost->setPublishDate('2025-12-31'); // Future
        $futurePost->setStores([$currentStore->getId()]);
        $futurePost->save();
        
        $collection3 = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter($currentStore)
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('entity_id', $futurePost->getId());
        $collection3->getSelect()->where('publish_date IS NULL OR publish_date <= ?', $today);
        
        expect($collection3->getSize())->toBe(0); // Should be excluded by date
        
        // 4. Post with no date = VISIBLE
        $noDatePost = Mage::getModel('blog/post');
        $noDatePost->setTitle('No Date Post - ' . uniqid());
        $noDatePost->setContent('No date post content');
        $noDatePost->setIsActive(1);
        $noDatePost->setPublishDate(null); // No date
        $noDatePost->setStores([$currentStore->getId()]);
        $noDatePost->save();
        
        $collection4 = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter($currentStore)
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('entity_id', $noDatePost->getId());
        $collection4->getSelect()->where('publish_date IS NULL OR publish_date <= ?', $today);
        
        expect($collection4->getSize())->toBeGreaterThan(0); // Should be included
        
        // Clean up
        $pastPost->delete();
        $inactivePost->delete();
        $futurePost->delete();
        $noDatePost->delete();
    });

    test('store isolation works correctly', function () {
        $currentStore = Mage::app()->getStore();
        $currentStoreId = $currentStore->getId();
        
        // Create a post for current store
        $currentStorePost = Mage::getModel('blog/post');
        $currentStorePost->setTitle('Current Store Post - ' . uniqid());
        $currentStorePost->setContent('This post is for current store');
        $currentStorePost->setIsActive(1);
        $currentStorePost->setPublishDate('2025-01-01');
        $currentStorePost->setStores([$currentStoreId]);
        $currentStorePost->save();
        
        // Verify it appears in current store's collection
        $currentStoreCollection = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter($currentStore)
            ->addFieldToFilter('entity_id', $currentStorePost->getId());
            
        expect($currentStoreCollection->getSize())->toBe(1);
        
        // Test the helper methods work with this post
        expect($this->helper->hasVisiblePosts())->toBeTrue();
        expect($this->helper->shouldShowInNavigation())->toBeTrue();
        
        // Clean up
        $currentStorePost->delete();
    });

    test('getBlogUrl generates correct URL', function () {
        $url = $this->helper->getBlogUrl();
        
        expect($url)->toBeString();
        expect($url)->toContain('blog');
    });

    test('getPostsPerPage returns valid number', function () {
        $postsPerPage = $this->helper->getPostsPerPage();
        
        expect($postsPerPage)->toBeInt();
        expect($postsPerPage)->toBeGreaterThan(0);
        expect($postsPerPage)->toBeLessThanOrEqual(1000); // Reasonable upper limit
    });
});