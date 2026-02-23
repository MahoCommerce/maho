<?php

declare(strict_types=1);

/**
 * API v2 Blog Post Tests
 *
 * Tests for blog post endpoints - PUBLIC ACCESS (no auth required).
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 */

describe('API v2 Blog Posts', function (): void {

    describe('public access (no auth)', function (): void {

        it('allows listing blog posts without authentication', function (): void {
            $response = apiGet('/api/blog-posts');

            expect($response['status'])->toBeSuccessful();
        });

        it('returns blog posts collection', function (): void {
            $response = apiGet('/api/blog-posts');

            expect($response['status'])->toBe(200);
            expect($response['json'])->toBeArray();
        });

        it('allows getting single blog post without authentication', function (): void {
            $list = apiGet('/api/blog-posts');
            $members = $list['json']['member'] ?? $list['json']['hydra:member'] ?? [];

            if (!empty($members) && isset($members[0]['id'])) {
                $postId = $members[0]['id'];
                $response = apiGet("/api/blog-posts/{$postId}");

                expect($response['status'])->toBeSuccessful();
            } else {
                expect(true)->toBeTrue();
            }
        });

        it('returns 404 for non-existent blog post', function (): void {
            $response = apiGet('/api/blog-posts/999999');

            expect($response['status'])->toBe(404);
        });

    });

    describe('with authentication', function (): void {

        it('allows listing blog posts with valid token', function (): void {
            $response = apiGet('/api/blog-posts', customerToken());

            expect($response['status'])->toBeSuccessful();
        });

        it('allows listing blog posts with admin token', function (): void {
            $response = apiGet('/api/blog-posts', adminToken());

            expect($response['status'])->toBeSuccessful();
        });

    });

    describe('response format', function (): void {

        it('includes expected blog post fields', function (): void {
            $response = apiGet('/api/blog-posts');
            $members = $response['json']['member'] ?? $response['json']['hydra:member'] ?? [];

            if (!empty($members) && isset($members[0])) {
                $post = $members[0];

                expect($post)->toHaveKey('id');
                expect($post)->toHaveKey('title');
            } else {
                expect(true)->toBeTrue();
            }
        });

    });

});
