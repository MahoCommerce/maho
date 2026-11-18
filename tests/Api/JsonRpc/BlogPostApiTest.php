<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoApiTestCase;

uses(MahoApiTestCase::class);

describe('Blog Post JSON-RPC API', function () {
    beforeEach(function () {
        $this->skipIfApiNotAvailable();
        $this->setupBlogApiUser();
    });

    describe('Authentication', function () {
        it('fails authentication with invalid credentials', function () {
            $client = new Tests\Api\Client\JsonRpcClient($this->apiConfig['base_url']);

            expect(fn() => $client->login('invalid_user', 'invalid_password'))
                ->toThrow(Exception::class, 'Login failed');
        });
    });

    describe('Blog Post CRUD Operations', function () {
        beforeEach(function () {
            $this->testPostIds = [];
        });

        afterEach(function () {
            // Cleanup created posts
            foreach ($this->testPostIds as $postId) {
                try {
                    $this->authenticatedCall('blog_post.delete', [$postId]);
                } catch (Exception $e) {
                    // Ignore cleanup errors
                }
            }
        });

        it('can list blog posts', function () {
            $response = $this->authenticatedCall('blog_post.list');

            $this->assertSuccessfulResponse($response);
            $result = $response->getResult();

            expect($result)->toBeArray()->not->toBeEmpty();
            expect($result[0])->toHaveKeys(['post_id', 'title', 'url_key', 'is_active']);
        });

        it('can create a blog post', function () {
            $postData = [
                'title' => 'Test Post via JSON-RPC API',
                'content' => 'This post was created via JSON-RPC API for testing purposes.',
                'url_key' => 'test-post-jsonrpc-' . time(),
                'is_active' => 1,
                'publish_date' => date('Y-m-d'),
                'stores' => [1],
                'meta_title' => 'Test Meta Title',
                'meta_description' => 'Test meta description for API created post',
            ];

            $response = $this->authenticatedCall('blog_post.create', [$postData]);

            $this->assertSuccessfulResponse($response, 'Failed to create blog post');
            $postId = $response->getResult();

            expect($postId)->toBeInt()->toBeGreaterThan(0);
            $this->testPostIds[] = $postId;

            // Verify the created post
            $infoResponse = $this->authenticatedCall('blog_post.info', [$postId]);
            $this->assertSuccessfulResponse($infoResponse);

            $postInfo = $infoResponse->getResult();
            expect($postInfo['title'])->toBe($postData['title']);
            expect($postInfo['url_key'])->toBe($postData['url_key']);
            expect($postInfo['content'])->toBe($postData['content']);
        });

        it('can retrieve blog post info', function () {
            // First create a test post
            $postData = [
                'title' => 'Test Info Post',
                'content' => 'Content for info test',
                'url_key' => 'test-info-post-' . time(),
                'is_active' => 1,
                'publish_date' => date('Y-m-d'),
                'stores' => [1],
            ];

            $createResponse = $this->authenticatedCall('blog_post.create', [$postData]);
            $postId = $createResponse->getResult();
            $this->testPostIds[] = $postId;

            // Now test info retrieval
            $response = $this->authenticatedCall('blog_post.info', [$postId]);

            $this->assertResponseStructure($response, [
                'post_id' => 'int',
                'title' => 'string',
                'content' => 'string',
                'url_key' => 'string',
                'is_active' => 'int',
                'publish_date',
                'stores' => 'array',
                'created_at',
                'updated_at',
            ]);

            $result = $response->getResult();
            expect($result['post_id'])->toBe($postId);
            expect($result['title'])->toBe($postData['title']);
        });

        it('can update a blog post', function () {
            // Create a test post
            $postData = [
                'title' => 'Original Title',
                'content' => 'Original content',
                'url_key' => 'test-update-post-' . time(),
                'is_active' => 1,
                'publish_date' => date('Y-m-d'),
                'stores' => [1],
            ];

            $createResponse = $this->authenticatedCall('blog_post.create', [$postData]);
            $postId = $createResponse->getResult();
            $this->testPostIds[] = $postId;

            // Update the post
            $updateData = [
                'title' => 'Updated Title via API',
                'content' => 'Updated content via JSON-RPC API',
                'meta_title' => 'Updated Meta Title',
            ];

            $updateResponse = $this->authenticatedCall('blog_post.update', [$postId, $updateData]);
            $this->assertSuccessfulResponse($updateResponse, 'Failed to update blog post');
            expect($updateResponse->getResult())->toBe(true);

            // Verify the update
            $infoResponse = $this->authenticatedCall('blog_post.info', [$postId]);
            $updatedPost = $infoResponse->getResult();

            expect($updatedPost['title'])->toBe($updateData['title']);
            expect($updatedPost['content'])->toBe($updateData['content']);
            expect($updatedPost['meta_title'])->toBe($updateData['meta_title']);
        });

        it('can delete a blog post', function () {
            // Create a test post
            $postData = [
                'title' => 'Post to Delete',
                'content' => 'This post will be deleted',
                'url_key' => 'test-delete-post-' . time(),
                'is_active' => 1,
                'publish_date' => date('Y-m-d'),
                'stores' => [1],
            ];

            $createResponse = $this->authenticatedCall('blog_post.create', [$postData]);
            $postId = $createResponse->getResult();

            // Delete the post
            $deleteResponse = $this->authenticatedCall('blog_post.delete', [$postId]);
            $this->assertSuccessfulResponse($deleteResponse, 'Failed to delete blog post');
            expect($deleteResponse->getResult())->toBe(true);

            // Verify it's deleted (should return error)
            $infoResponse = $this->authenticatedCall('blog_post.info', [$postId]);
            $this->assertErrorResponse($infoResponse);
        });
    });

    describe('Validation and Error Handling', function () {
        it('validates required fields on create', function () {
            $invalidData = [
                'url_key' => 'test-invalid',
                // Missing required title and content
            ];

            $response = $this->authenticatedCall('blog_post.create', [$invalidData]);
            $this->assertErrorResponse($response);
        });

        it('handles non-existent post ID gracefully', function () {
            $nonExistentId = 999999;

            $response = $this->authenticatedCall('blog_post.info', [$nonExistentId]);
            $this->assertErrorResponse($response);
        });

        it('replaces an invalid publish_date with the date of today in the store', function () {
            $postData = [
                'title' => 'Test Date Validation',
                'content' => 'Testing date validation',
                'url_key' => 'test-date-validation-' . time(),
                'is_active' => 1,
                'publish_date' => 'invalid-date-format',
                'stores' => [1],
            ];

            $response = $this->authenticatedCall('blog_post.create', [$postData]);

            $this->assertSuccessfulResponse($response);
            $postId = $response->getResult();
            expect($postId)->toBeInt()->toBeGreaterThan(0);
            $this->testPostIds[] = $postId;

            $info = $this->authenticatedCall('blog_post.info', [$postId])->getResult();
            $today = Mage::app()->getLocale()->utcToStore()->format(Mage_Core_Model_Locale::DATE_FORMAT);
            expect((string) $info['publish_date'])->toStartWith($today);
        });
    });

    describe('Filtering and Search', function () {
        beforeEach(function () {
            $this->testPostIds = [];
        });

        afterEach(function () {
            foreach ($this->testPostIds as $postId) {
                try {
                    $this->authenticatedCall('blog_post.delete', [$postId]);
                } catch (Exception $e) {
                    // Ignore cleanup errors
                }
            }
        });

        it('can filter posts by status', function () {
            // Create an active post
            $activePostData = [
                'title' => 'Active Test Post',
                'content' => 'This is an active post',
                'url_key' => 'active-test-post-' . time(),
                'is_active' => 1,
                'publish_date' => date('Y-m-d'),
                'stores' => [1],
            ];

            $activeId = $this->authenticatedCall('blog_post.create', [$activePostData])->getResult();
            $this->testPostIds[] = $activeId;

            $inactiveId = $this->authenticatedCall('blog_post.create', [[
                'title' => 'Inactive Test Post',
                'content' => 'This is an inactive post',
                'url_key' => 'inactive-test-post-' . time(),
                'is_active' => 0,
                'publish_date' => date('Y-m-d'),
                'stores' => [1],
            ]])->getResult();
            $this->testPostIds[] = $inactiveId;

            $response = $this->authenticatedCall('blog_post.list', [['is_active' => 1]]);

            $this->assertSuccessfulResponse($response);
            $posts = $response->getResult();

            expect($posts)->toBeArray();
            foreach ($posts as $post) {
                expect($post['is_active'])->toBe(1);
            }
            $ids = array_map('intval', array_column($posts, 'post_id'));
            expect($ids)->toContain($activeId);
            expect($ids)->not->toContain($inactiveId);
        });
    });

    describe('Multi-call Operations', function () {
        it('can perform batch operations', function () {
            // multiCall returns the raw return value of each call, in order -
            // not a {result: ...} envelope. Both calls list posts.
            $calls = [
                ['blog_post.list', []],
                ['blog_post.list', [['is_active' => 1]]],
            ];

            $sessionId = $this->getAuthenticatedSessionId();
            $responses = $this->apiClient->multiCall($calls, $sessionId);

            expect($responses)->toHaveCount(2);
            expect($responses[0])->toBeArray();
            expect($responses[1])->toBeArray();
        });
    });
});
