<?php

/**
 * Maho
 *
 * @package    Tests
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * GraphQL CMS & Blog Post Integration Tests
 *
 * Tests CMS page and blog post queries via GraphQL.
 * Includes regression tests for custom QueryCollection operations.
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 * @group graphql
 */

describe('GraphQL CMS Pages', function (): void {

    it('returns CMS pages collection', function (): void {
        $query = <<<'GRAPHQL'
        {
            cmsPagesCmsPages {
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
        expect($response['json']['data'])->toHaveKey('cmsPagesCmsPages');

        $edges = $response['json']['data']['cmsPagesCmsPages']['edges'] ?? [];
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
            cmsPagesCmsPages(identifier: "home") {
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

        $edges = $response['json']['data']['cmsPagesCmsPages']['edges'] ?? [];
        if (!empty($edges)) {
            expect($edges[0]['node']['identifier'])->toBe('home');
        }
    });

    it('returns single CMS page by IRI', function (): void {
        // First get a page ID
        $listQuery = <<<'GRAPHQL'
        {
            cmsPagesCmsPages {
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

        $edges = $listResponse['json']['data']['cmsPagesCmsPages']['edges'] ?? [];
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

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('blogPostsBlogPosts');
    });

    /**
     * Regression: Custom QueryCollection with urlKey filter
     * Previously, custom query operations without `id` returned null
     */
    it('supports urlKey filter on custom query (regression)', function (): void {
        // First get an existing blog post URL key
        $listQuery = <<<'GRAPHQL'
        {
            blogPostsBlogPosts {
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

        $edges = $listResponse['json']['data']['blogPostsBlogPosts']['edges'] ?? [];
        if (empty($edges)) {
            $this->markTestSkipped('No blog posts available');
        }

        $urlKey = $edges[0]['node']['urlKey'];

        $query = <<<GRAPHQL
        {
            blogPostsBlogPosts(urlKey: "{$urlKey}") {
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
        $filteredEdges = $response['json']['data']['blogPostsBlogPosts']['edges'] ?? [];
        expect($filteredEdges)->not->toBeEmpty();
        expect($filteredEdges[0]['node']['urlKey'])->toBe($urlKey);
    });

    it('returns single blog post by IRI', function (): void {
        $listQuery = <<<'GRAPHQL'
        {
            blogPostsBlogPosts {
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

        $edges = $listResponse['json']['data']['blogPostsBlogPosts']['edges'] ?? [];
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
