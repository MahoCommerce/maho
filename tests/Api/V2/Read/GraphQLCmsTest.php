<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * GraphQL CMS & Blog Post Integration Tests
 *
 * Tests CMS page and blog post queries via GraphQL.
 * Includes regression tests for custom QueryCollection operations.
 *
 * @group read
 * @group graphql
 */

// The sample data has no blog post in the default store view, so the file creates its own
beforeAll(function (): void {
    $create = apiPost('/api/rest/v2/blog-posts', [
        'title' => 'GraphQL Blog Post Fixture',
        'urlKey' => 'test-pest-graphql-blog-post',
        'content' => '<p>Created by Pest test suite</p>',
        'isActive' => true,
        'publishDate' => '2026-01-15',
        'stores' => ['all'],
    ], serviceToken(['blog-posts/write']));

    if (!in_array($create['status'], [200, 201], true)) {
        throw new RuntimeException("Could not create the blog post fixture: HTTP {$create['status']}");
    }
    trackCreated('blog_post', (int) $create['json']['id']);
});

afterAll(function (): void {
    cleanupTestData();
});

describe('GraphQL CMS Pages', function (): void {

    it('returns CMS pages collection', function (): void {
        $query = <<<'GRAPHQL'
        {
            cmsPages {
                edges {
                    node {
                        id
                        _id
                        identifier
                        title
                        status
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('cmsPages');

        $edges = $response['json']['data']['cmsPages']['edges'] ?? [];
        expect($edges)->not->toBeEmpty();

        $page = $edges[0]['node'];
        expect($page)->toHaveKey('identifier');
        expect($page)->toHaveKey('title');
    });

    /**
     * Regression: Custom QueryCollection with identifier filter
     * Previously, custom query operations without `id` returned null
     */
    it('supports identifier filter on custom query (regression)', function (): void {
        $query = <<<'GRAPHQL'
        {
            cmsPages(identifier: "home") {
                edges {
                    node {
                        id
                        _id
                        identifier
                        title
                        content
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');

        $edges = $response['json']['data']['cmsPages']['edges'] ?? [];
        expect($edges)->not->toBeEmpty();
        expect($edges[0]['node']['identifier'])->toBe('home');
    });

    it('returns single CMS page by IRI', function (): void {
        // First get a page ID
        $listQuery = <<<'GRAPHQL'
        {
            cmsPages {
                edges {
                    node {
                        id
                        _id
                        identifier
                        title
                    }
                }
            }
        }
        GRAPHQL;

        $listResponse = gqlQuery($listQuery, [], customerToken());
        expect($listResponse['status'])->toBe(200);

        $edges = $listResponse['json']['data']['cmsPages']['edges'] ?? [];
        if (empty($edges)) {
            $this->markTestSkipped('No CMS pages available');
        }

        $pageIri = $edges[0]['node']['id'];

        $query = <<<GRAPHQL
        {
            cmsPage(id: "{$pageIri}") {
                id
                _id
                identifier
                title
                content
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['data']['cmsPage'])->not->toBeNull();
    });

});

describe('GraphQL Blog Posts', function (): void {

    it('returns blog posts collection', function (): void {
        $query = <<<'GRAPHQL'
        {
            blogPosts {
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

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->not->toHaveKey('errors');
        expect($response['json']['data']['blogPosts']['edges'] ?? [])->not->toBeEmpty();
    });

    /**
     * Regression: Custom QueryCollection with urlKey filter
     * Previously, custom query operations without `id` returned null
     */
    it('supports urlKey filter on custom query (regression)', function (): void {
        // First get an existing blog post URL key
        $listQuery = <<<'GRAPHQL'
        {
            blogPosts {
                edges {
                    node {
                        id
                        _id
                        title
                        urlKey
                    }
                }
            }
        }
        GRAPHQL;

        $listResponse = gqlQuery($listQuery, [], customerToken());
        expect($listResponse['status'])->toBe(200);

        $edges = $listResponse['json']['data']['blogPosts']['edges'] ?? [];
        if (empty($edges)) {
            $this->markTestSkipped('No blog posts available');
        }

        $urlKey = $edges[0]['node']['urlKey'];

        $query = <<<GRAPHQL
        {
            blogPosts(urlKey: "{$urlKey}") {
                edges {
                    node {
                        id
                        _id
                        title
                        urlKey
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        $filteredEdges = $response['json']['data']['blogPosts']['edges'] ?? [];
        expect($filteredEdges)->not->toBeEmpty();
        expect($filteredEdges[0]['node']['urlKey'])->toBe($urlKey);
    });

    it('returns single blog post by IRI', function (): void {
        $listQuery = <<<'GRAPHQL'
        {
            blogPosts {
                edges {
                    node {
                        id
                        _id
                        title
                    }
                }
            }
        }
        GRAPHQL;

        $listResponse = gqlQuery($listQuery, [], customerToken());
        expect($listResponse['status'])->toBe(200);

        $edges = $listResponse['json']['data']['blogPosts']['edges'] ?? [];
        if (empty($edges)) {
            $this->markTestSkipped('No blog posts available');
        }

        $postIri = $edges[0]['node']['id'];

        $query = <<<GRAPHQL
        {
            blogPost(id: "{$postIri}") {
                id
                _id
                title
                urlKey
                content
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['data']['blogPost'])->not->toBeNull();
    });

});
