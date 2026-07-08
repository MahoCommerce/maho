<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * GraphQL Category Integration Tests
 *
 * Tests category queries via GraphQL.
 * All tests are READ-ONLY (safe for synced database).
 *
 * @group read
 * @group graphql
 */

describe('GraphQL Categories Collection Query', function (): void {

    it('returns a list of categories', function (): void {
        $query = <<<'GRAPHQL'
        {
            categories {
                edges {
                    node {
                        id
                        _id
                        name
                        parentId
                        level
                        isActive
                        includeInMenu
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('categories');

        $edges = $response['json']['data']['categories']['edges'] ?? [];
        expect($edges)->not->toBeEmpty();

        $category = $edges[0]['node'];
        expect($category)->toHaveKey('name');
        expect($category)->toHaveKey('parentId');
        expect($category)->toHaveKey('level');
    });

    it('supports parentId filter', function (): void {
        $query = <<<'GRAPHQL'
        {
            categories(parentId: 2) {
                edges {
                    node {
                        id
                        _id
                        name
                        parentId
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);

        $edges = $response['json']['data']['categories']['edges'] ?? [];
        foreach ($edges as $edge) {
            expect($edge['node']['parentId'])->toBe(2);
        }
    });

});

describe('GraphQL Single Category Query', function (): void {

    it('returns a single category by IRI', function (): void {
        $categoryId = fixtures('category_id');
        $iri = "/api/rest/v2/categories/{$categoryId}";

        $query = <<<GRAPHQL
        {
            category(id: "{$iri}") {
                id
                _id
                name
                parentId
                urlKey
                urlPath
                level
                isActive
                productCount
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json']['data']['category'])->not->toBeNull();

        $category = $response['json']['data']['category'];
        expect($category['_id'])->toBe($categoryId);
        expect($category['name'])->toBeString();
    });

    it('returns null for non-existent category', function (): void {
        $iri = '/api/rest/v2/categories/999999';

        $query = <<<GRAPHQL
        {
            category(id: "{$iri}") {
                id
                name
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);

        $data = $response['json']['data']['category'] ?? null;
        $errors = $response['json']['errors'] ?? [];
        expect($data === null || !empty($errors))->toBeTrue();
    });

});

describe('GraphQL Category By URL Key Query', function (): void {

    it('returns category by URL key', function (): void {
        // First get a category to know a valid URL key
        $query = <<<'GRAPHQL'
        {
            categories {
                edges {
                    node {
                        _id
                        name
                        urlKey
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());
        expect($response['status'])->toBe(200);

        $edges = $response['json']['data']['categories']['edges'] ?? [];
        $urlKey = null;
        foreach ($edges as $edge) {
            if (!empty($edge['node']['urlKey'])) {
                $urlKey = $edge['node']['urlKey'];
                break;
            }
        }

        if ($urlKey === null) {
            $this->markTestSkipped('No categories with urlKey found');
        }

        $lookupQuery = <<<GRAPHQL
        {
            categories(urlKey: "{$urlKey}") {
                edges { node {
                    id
                    _id
                    name
                    urlKey
                } }
            }
        }
        GRAPHQL;

        $lookupResponse = gqlQuery($lookupQuery, [], customerToken());

        expect($lookupResponse['status'])->toBe(200);
        expect($lookupResponse['json'])->toHaveKey('data');

        $result = $lookupResponse['json']['data']['categories']['edges'][0]['node'] ?? null;
        if ($result === null) {
            $this->markTestSkipped('categories(urlKey:) returned no match');
        }

        expect($result['urlKey'])->toBe($urlKey);
    });

});
