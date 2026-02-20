<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Blog Post Basic Integration', function () {
    test('can perform full CRUD cycle', function () {
        // CREATE
        $post = Mage::getModel('blog/post');
        $post->setTitle('Integration Test Post');
        $post->setContent('Content for integration test');
        $post->setUrlKey('integration-test-post');
        $post->setIsActive(1);
        $post->setPublishDate('2025-01-15');
        $post->setMetaTitle('Integration Meta Title');
        $post->setMetaKeywords('integration, test, blog');
        $post->setMetaDescription('Meta description for integration test');
        $post->save();

        $postId = $post->getId();
        expect($postId)->toBeGreaterThan(0);

        // READ
        $loadedPost = Mage::getModel('blog/post')->load($postId);
        expect($loadedPost->getTitle())->toBe('Integration Test Post');
        expect($loadedPost->getContent())->toBe('Content for integration test');
        expect($loadedPost->getUrlKey())->toBe('integration-test-post');
        expect((int) $loadedPost->getIsActive())->toBe(1);
        expect($loadedPost->getPublishDate())->toBe('2025-01-15');
        expect($loadedPost->getMetaTitle())->toBe('Integration Meta Title');

        // UPDATE
        $loadedPost->setTitle('Updated Integration Test Post');
        $loadedPost->setMetaDescription('Updated meta description');
        $loadedPost->save();

        $updatedPost = Mage::getModel('blog/post')->load($postId);
        expect($updatedPost->getTitle())->toBe('Updated Integration Test Post');
        expect($updatedPost->getMetaDescription())->toBe('Updated meta description');
        // Other fields should remain unchanged
        expect($updatedPost->getContent())->toBe('Content for integration test');

        // DELETE
        $updatedPost->delete();

        $deletedPost = Mage::getModel('blog/post')->load($postId);
        expect($deletedPost->getId())->toBeNull();
    });

    test('blog module models are properly registered', function () {
        // Test that blog models can be instantiated via Mage factory
        $post = Mage::getModel('blog/post');
        expect($post)->toBeInstanceOf(Maho_Blog_Model_Post::class);

        $collection = Mage::getResourceModel('blog/post_collection');
        expect($collection)->toBeInstanceOf(Maho_Blog_Model_Resource_Post_Collection::class);

        $helper = Mage::helper('blog');
        expect($helper)->toBeInstanceOf(Maho_Blog_Helper_Data::class);
    });

    test('can handle store relationships', function () {
        $post = Mage::getModel('blog/post');
        $post->setTitle('Store Test Post');
        $post->setContent('Testing store relationships');
        $post->setIsActive(1);
        $post->setStores([0]); // All stores
        $post->save();

        expect($post->getId())->toBeGreaterThan(0);

        // Test store filtering in collection
        $collection = Mage::getResourceModel('blog/post_collection')
            ->addStoreFilter(0) // All stores
            ->addFieldToFilter('entity_id', $post->getId());

        expect($collection->getSize())->toBe(1);

        $post->delete();
    });
});
