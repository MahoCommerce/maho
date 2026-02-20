<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Blog Post Collection', function () {
    beforeEach(function () {
        $this->collection = Mage::getResourceModel('blog/post_collection');

        // Create test posts
        $this->testPosts = [];

        // Active post with publish date in past
        $post1 = Mage::getModel('blog/post');
        $post1->setTitle('Active Past Post');
        $post1->setContent('Content for active past post');
        $post1->setIsActive(1);
        $post1->setPublishDate('2025-01-01');
        $post1->save();
        $this->testPosts[] = $post1;

        // Active post with future publish date
        $post2 = Mage::getModel('blog/post');
        $post2->setTitle('Active Future Post');
        $post2->setContent('Content for active future post');
        $post2->setIsActive(1);
        $post2->setPublishDate('2025-12-31');
        $post2->save();
        $this->testPosts[] = $post2;

        // Inactive post
        $post3 = Mage::getModel('blog/post');
        $post3->setTitle('Inactive Post');
        $post3->setContent('Content for inactive post');
        $post3->setIsActive(0);
        $post3->setPublishDate('2025-01-01');
        $post3->save();
        $this->testPosts[] = $post3;

        // Post with no publish date
        $post4 = Mage::getModel('blog/post');
        $post4->setTitle('No Date Post');
        $post4->setContent('Content for no date post');
        $post4->setIsActive(1);
        $post4->setPublishDate(null);
        $post4->save();
        $this->testPosts[] = $post4;
    });

    afterEach(function () {
        // Clean up test posts
        foreach ($this->testPosts as $post) {
            $post->delete();
        }
    });

    test('can create collection instance', function () {
        expect($this->collection)->toBeInstanceOf(Maho_Blog_Model_Resource_Post_Collection::class);
    });

    test('can load all posts', function () {
        $this->collection->load();

        expect($this->collection->getSize())->toBeGreaterThanOrEqual(4);
    });

    test('can filter by active status using static attribute', function () {
        $activeCollection = Mage::getResourceModel('blog/post_collection')
            ->addFieldToFilter('is_active', 1);

        foreach ($activeCollection as $post) {
            expect((int) $post->getIsActive())->toBe(1);
        }

        expect($activeCollection->getSize())->toBeGreaterThanOrEqual(3);
    });

    test('can filter by title using static attribute', function () {
        $titleCollection = Mage::getResourceModel('blog/post_collection')
            ->addFieldToFilter('title', 'Active Past Post'); // Exact match

        expect($titleCollection->getSize())->toBe(1);

        $post = $titleCollection->getFirstItem();
        expect($post->getTitle())->toBe('Active Past Post');
    });

    test('can filter by publish date', function () {
        $today = Mage_Core_Model_Locale::today();
        $pastCollection = Mage::getResourceModel('blog/post_collection');
        $pastCollection->getSelect()->where('publish_date IS NULL OR publish_date <= ?', $today);

        foreach ($pastCollection as $post) {
            $publishDate = $post->getPublishDate();
            expect($publishDate === null || $publishDate <= $today)->toBeTrue();
        }
    });

    test('smart attribute filtering works for static attributes', function () {
        $collection = Mage::getResourceModel('blog/post_collection')
            ->addAttributeToFilter('title', 'Active Past Post');

        $collection->load();
        expect($collection->getSize())->toBe(1);

        $post = $collection->getFirstItem();
        expect($post->getTitle())->toBe('Active Past Post');
    });

    test('can add store filter', function () {
        $storeId = Mage::app()->getStore()->getId();
        $this->collection->addStoreFilter($storeId);

        // Should not throw exception and should load data
        $this->collection->load();
        expect($this->collection)->toBeInstanceOf(Maho_Blog_Model_Resource_Post_Collection::class);
    });

    test('can order by static attributes', function () {
        // Test with our specific test data
        $collection = Mage::getResourceModel('blog/post_collection')
            ->addFieldToFilter('title', ['in' => ['Active Past Post', 'Active Future Post']])
            ->setOrder('title', 'ASC');

        $collection->load();

        expect($collection->getSize())->toBe(2);

        $titles = [];
        foreach ($collection as $post) {
            $titles[] = $post->getTitle();
        }

        expect($titles[0])->toBe('Active Future Post'); // A comes before P
        expect($titles[1])->toBe('Active Past Post');
    });

    test('can select specific attributes', function () {
        $this->collection->addAttributeToSelect(['title', 'is_active']);
        $this->collection->load();

        foreach ($this->collection as $post) {
            expect($post->getTitle())->not()->toBeEmpty();
            expect((int) $post->getIsActive())->toBeIn([0, 1]);
        }
    });

    test('prevents infinite loop in attribute filtering', function () {
        // This should not cause infinite recursion
        $collection = Mage::getResourceModel('blog/post_collection')
            ->addAttributeToFilter('title', 'Test')
            ->addAttributeToFilter('is_active', 1);

        $collection->load();
        expect($collection)->toBeInstanceOf(Maho_Blog_Model_Resource_Post_Collection::class);
    });
});
