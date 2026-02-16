<?php

declare(strict_types=1);

/**
 * API v2 Blog Post CRUD Tests
 *
 * End-to-end tests for blog post create, read, update, delete via REST,
 * plus GraphQL read verification. Tests permission enforcement at each step.
 *
 * @group write
 */

afterAll(function () {
    cleanupTestData();
});

describe('Blog Post Permission Enforcement (REST)', function () {

    it('denies create without authentication', function () {
        $response = apiPost('/api/blog-posts', [
            'title' => 'Test Post No Auth',
            'urlKey' => 'test-post-noauth',
            'content' => '<p>Should fail</p>',
        ]);

        expect($response['status'])->toBe(401);
    });

    it('denies create with customer token (wrong role)', function () {
        $response = apiPost('/api/blog-posts', [
            'title' => 'Test Post Customer',
            'urlKey' => 'test-post-customer',
            'content' => '<p>Should fail</p>',
        ], customerToken());

        expect($response['status'])->toBeForbidden();
    });

    it('denies create without correct permission', function () {
        $token = serviceToken(['cms-pages/write']);
        $response = apiPost('/api/blog-posts', [
            'title' => 'Test Post No Permission',
            'urlKey' => 'test-post-noperm',
            'content' => '<p>Should fail</p>',
        ], $token);

        expect($response['status'])->toBeForbidden();
    });

});

describe('Blog Post CRUD Lifecycle (REST)', function () {

    it('creates → reads → updates → verifies → delete-denied → deletes → confirms gone', function () {
        $writeToken = serviceToken(['blog-posts/write']);
        $deleteToken = serviceToken(['blog-posts/delete']);

        // 1. Create
        $create = apiPost('/api/blog-posts', [
            'title' => 'Test CRUD Blog Post',
            'urlKey' => 'test-pest-crud-post',
            'content' => '<p>Created by Pest test suite</p>',
            'shortContent' => 'Test short content',
            'isActive' => true,
            'publishedAt' => '2026-01-15 10:00:00',
            'stores' => ['all'],
        ], $writeToken);

        expect($create['status'])->toBeIn([200, 201]);
        expect($create['json'])->toHaveKey('id');
        expect($create['json']['title'])->toBe('Test CRUD Blog Post');
        expect($create['json']['urlKey'])->toBe('test-pest-crud-post');

        $postId = $create['json']['id'];
        trackCreated('blog_post', $postId);

        // 2. Read (public, no auth)
        $read = apiGet("/api/blog-posts/{$postId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['title'])->toBe('Test CRUD Blog Post');
        expect($read['json']['urlKey'])->toBe('test-pest-crud-post');

        // 3. Update
        $update = apiPut("/api/blog-posts/{$postId}", [
            'title' => 'Test CRUD Blog Post Updated',
            'content' => '<p>Updated by Pest test suite</p>',
            'shortContent' => 'Updated short content',
        ], $writeToken);
        expect($update['status'])->toBe(200);
        expect($update['json']['title'])->toBe('Test CRUD Blog Post Updated');

        // 4. Verify update persisted
        $verify = apiGet("/api/blog-posts/{$postId}");
        expect($verify['status'])->toBe(200);
        expect($verify['json']['title'])->toBe('Test CRUD Blog Post Updated');
        expect($verify['json']['content'])->toContain('Updated by Pest');

        // 5. Deny delete with only write permission
        $denyDelete = apiDelete("/api/blog-posts/{$postId}", $writeToken);
        expect($denyDelete['status'])->toBeForbidden();

        // 6. Delete with correct permission
        $delete = apiDelete("/api/blog-posts/{$postId}", $deleteToken);
        expect($delete['status'])->toBeIn([200, 204]);

        // 7. Confirm gone
        $gone = apiGet("/api/blog-posts/{$postId}");
        expect($gone['status'])->toBeNotFound();
    });

});

describe('Blog Post CRUD with "all" permission', function () {

    it('full lifecycle with "all" permission', function () {
        $token = serviceToken(['all']);

        // Create
        $create = apiPost('/api/blog-posts', [
            'title' => 'All Permission Post',
            'urlKey' => 'test-pest-all-perm-post',
            'content' => '<p>All permission test</p>',
            'isActive' => true,
            'stores' => ['all'],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $postId = $create['json']['id'];
        trackCreated('blog_post', $postId);

        // Read (public)
        $read = apiGet("/api/blog-posts/{$postId}");
        expect($read['status'])->toBe(200);

        // Update
        $update = apiPut("/api/blog-posts/{$postId}", [
            'title' => 'All Permission Post Updated',
        ], $token);
        expect($update['status'])->toBe(200);

        // Delete
        $delete = apiDelete("/api/blog-posts/{$postId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);

        // Confirm gone
        $gone = apiGet("/api/blog-posts/{$postId}");
        expect($gone['status'])->toBeNotFound();
    });

});

describe('Blog Post via GraphQL (read)', function () {

    it('reads blog posts collection via GraphQL', function () {
        $query = <<<'GRAPHQL'
        {
            blogPostsBlogPosts {
                edges {
                    node {
                        id
                        _id
                        title
                        urlKey
                        publishDate
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('blogPostsBlogPosts');
    });

    it('creates post via REST then reads via GraphQL', function () {
        $token = serviceToken(['blog-posts/write', 'blog-posts/delete']);

        // Create via REST
        $create = apiPost('/api/blog-posts', [
            'title' => 'GraphQL Verify Post',
            'urlKey' => 'test-pest-gql-verify-post',
            'content' => '<p>Verify via GraphQL</p>',
            'isActive' => true,
            'stores' => ['all'],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $postId = $create['json']['id'];
        trackCreated('blog_post', $postId);

        // Read via GraphQL by IRI
        $iri = "/api/blog-posts/{$postId}";
        $query = <<<GRAPHQL
        {
            blogPost(id: "{$iri}") {
                _id
                title
                urlKey
                content
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);

        expect($response['status'])->toBe(200);
        expect($response['json']['data']['blogPost'])->not->toBeNull();
        expect($response['json']['data']['blogPost']['title'])->toBe('GraphQL Verify Post');
        expect($response['json']['data']['blogPost']['urlKey'])->toBe('test-pest-gql-verify-post');

        // Read via urlKey filter
        $filterQuery = <<<'GRAPHQL'
        {
            blogPostsBlogPosts(urlKey: "test-pest-gql-verify-post") {
                edges {
                    node {
                        _id
                        title
                        urlKey
                    }
                }
            }
        }
        GRAPHQL;

        $filterResponse = gqlQuery($filterQuery);
        expect($filterResponse['status'])->toBe(200);
        $edges = $filterResponse['json']['data']['blogPostsBlogPosts']['edges'] ?? [];
        expect($edges)->not->toBeEmpty();
        expect($edges[0]['node']['urlKey'])->toBe('test-pest-gql-verify-post');

        // Cleanup via REST
        $delete = apiDelete("/api/blog-posts/{$postId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);
    });

});
