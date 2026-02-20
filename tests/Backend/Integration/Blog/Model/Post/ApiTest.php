<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Blog Post SOAP API', function () {
    beforeEach(function () {
        $this->api = new Maho_Blog_Model_Post_Api();

        // Create test posts for API testing
        $this->testPosts = [];

        $post1 = Mage::getModel('blog/post');
        $post1->setTitle('API Test Post 1');
        $post1->setContent('Content for API test post 1');
        $post1->setUrlKey('api-test-post-1');
        $post1->setIsActive(1);
        $post1->setPublishDate('2025-01-01');
        $post1->setMetaTitle('API Meta Title 1');
        $post1->setMetaKeywords('api, test, post');
        $post1->setMetaDescription('API test description 1');
        $post1->save();
        $this->testPosts[] = $post1;

        $post2 = Mage::getModel('blog/post');
        $post2->setTitle('API Test Post 2');
        $post2->setContent('Content for API test post 2');
        $post2->setUrlKey('api-test-post-2');
        $post2->setIsActive(0);
        $post2->setPublishDate('2025-01-15');
        $post2->save();
        $this->testPosts[] = $post2;
    });

    afterEach(function () {
        // Clean up test posts
        foreach ($this->testPosts as $post) {
            $post->delete();
        }
    });

    test('can list posts via API', function () {
        $posts = $this->api->items();

        expect($posts)->toBeArray();
        expect(count($posts))->toBeGreaterThanOrEqual(2);

        // Check structure of returned data
        $firstPost = $posts[0];
        expect($firstPost)->toHaveKeys([
            'post_id', 'title', 'content', 'url_key', 'image',
            'is_active', 'publish_date', 'meta_title', 'meta_keywords',
            'meta_description', 'meta_robots', 'created_at', 'updated_at', 'stores',
        ]);
    });

    test('can filter posts via API', function () {
        $filters = ['is_active' => 1];
        $activePosts = $this->api->items($filters);

        expect($activePosts)->toBeArray();

        foreach ($activePosts as $post) {
            expect((int) $post['is_active'])->toBe(1);
        }
    });

    test('can get specific post info via API', function () {
        $testPost = $this->testPosts[0];
        $postInfo = $this->api->info((int) $testPost->getId());

        expect($postInfo)->toBeArray();
        expect($postInfo['post_id'])->toBe((int) $testPost->getId());
        expect($postInfo['title'])->toBe('API Test Post 1');
        expect($postInfo['content'])->toBe('Content for API test post 1');
        expect($postInfo['url_key'])->toBe('api-test-post-1');
        expect((int) $postInfo['is_active'])->toBe(1);
        expect($postInfo['meta_title'])->toBe('API Meta Title 1');
        expect($postInfo['meta_keywords'])->toBe('api, test, post');
        expect($postInfo['meta_description'])->toBe('API test description 1');
    });

    test('can create post via API', function () {
        $postData = [
            'title' => 'API Created Post',
            'content' => 'Content created via API',
            'url_key' => 'api-created-post',
            'is_active' => 1,
            'publish_date' => '2025-02-01',
            'meta_title' => 'API Created Meta Title',
            'meta_keywords' => 'api, created, test',
            'meta_description' => 'Post created via API test',
            'meta_robots' => 'index,follow',
        ];

        $postId = $this->api->create($postData);

        expect($postId)->toBeGreaterThan(0);

        // Verify post was created correctly
        $createdPost = Mage::getModel('blog/post')->load($postId);
        expect($createdPost->getTitle())->toBe('API Created Post');
        expect($createdPost->getContent())->toBe('Content created via API');
        expect($createdPost->getUrlKey())->toBe('api-created-post');
        expect((int) $createdPost->getIsActive())->toBe(1);
        expect($createdPost->getMetaTitle())->toBe('API Created Meta Title');
        expect($createdPost->getMetaRobots())->toBe('index,follow');

        // Cleanup
        $createdPost->delete();
    });

    test('can update post via API', function () {
        $testPost = $this->testPosts[0];
        $originalTitle = $testPost->getTitle();

        $updateData = [
            'title' => 'Updated API Title',
            'meta_description' => 'Updated meta description',
            'is_active' => 0,
        ];

        $result = $this->api->update((int) $testPost->getId(), $updateData);
        expect($result)->toBeTrue();

        // Reload post to verify changes
        $testPost->load($testPost->getId());
        expect($testPost->getTitle())->toBe('Updated API Title');
        expect($testPost->getMetaDescription())->toBe('Updated meta description');
        expect((int) $testPost->getIsActive())->toBe(0);

        // Content should remain unchanged
        expect($testPost->getContent())->toBe('Content for API test post 1');
    });

    test('can delete post via API', function () {
        $testPost = $this->testPosts[1]; // Use second test post
        $postId = $testPost->getId();

        $result = $this->api->delete((int) $postId);
        expect($result)->toBeTrue();

        // Verify post was deleted
        $deletedPost = Mage::getModel('blog/post')->load($postId);
        expect($deletedPost->getId())->toBeNull();

        // Remove from cleanup array since it's already deleted
        unset($this->testPosts[1]);
    });

    test('throws exception for non-existent post info', function () {
        expect(fn() => $this->api->info(99999))->toThrow(Exception::class);
    });

    test('throws exception for non-existent post update', function () {
        $updateData = ['title' => 'Non-existent Update'];
        expect(fn() => $this->api->update(99999, $updateData))->toThrow(Exception::class);
    });

    test('throws exception for non-existent post delete', function () {
        expect(fn() => $this->api->delete(99999))->toThrow(Exception::class);
    });

    test('returns complete post data structure', function () {
        $testPost = $this->testPosts[0];
        $postData = $this->api->info((int) $testPost->getId());

        // Verify all expected fields are present
        $expectedFields = [
            'post_id', 'title', 'content', 'url_key', 'image',
            'is_active', 'publish_date', 'meta_title', 'meta_keywords',
            'meta_description', 'meta_robots', 'created_at', 'updated_at', 'stores',
        ];

        foreach ($expectedFields as $field) {
            expect($postData)->toHaveKey($field);
        }

        // Verify data types
        expect((int) $postData['post_id'])->toBeInt();
        expect($postData['title'])->toBeString();
        expect($postData['content'])->toBeString();
        expect((int) $postData['is_active'])->toBeIn([0, 1]);
    });
});
