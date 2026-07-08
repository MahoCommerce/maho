<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 GraphQL Permission Tests
 *
 * Tests that GraphQL queries and mutations enforce permissions correctly.
 * Verifies public queries work without auth and mutations require correct tokens.
 *
 * Note: Custom QueryCollection operations (products, categories, etc.)
 * do NOT support Relay pagination args (first/last/before/after). Only default
 * collection_query operations get those automatically.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('GraphQL Public Queries', function (): void {

    it('allows public product query without auth', function (): void {
        $query = <<<'GRAPHQL'
        {
            products {
                edges {
                    node {
                        _id
                        sku
                        name
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);
        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('products');
    });

    it('allows public category query without auth', function (): void {
        $query = <<<'GRAPHQL'
        {
            categories {
                edges {
                    node {
                        _id
                        name
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);
        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('categories');
    });

    it('allows public CMS page query without auth', function (): void {
        $query = <<<'GRAPHQL'
        {
            cmsPages {
                edges {
                    node {
                        _id
                        title
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);
        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('cmsPages');
    });

    it('allows public blog post query without auth', function (): void {
        $query = <<<'GRAPHQL'
        {
            blogPosts {
                edges {
                    node {
                        _id
                        title
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);
        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('blogPosts');
    });

});

describe('GraphQL Mutation Permissions', function (): void {

    it('denies cart creation without valid masked ID', function (): void {
        $query = <<<'GRAPHQL'
        mutation {
            createCart(input: { maskedId: "invalid" }) {
                _id
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);
        expect($response['status'])->toBe(200);
        // GraphQL returns 200 with errors in the response
        $errors = $response['json']['errors'] ?? [];
        expect($errors)->not->toBeEmpty();
    });

    it('denies review submission without auth', function (): void {
        $query = <<<'GRAPHQL'
        mutation {
            submitReview(input: {
                productId: 421
                title: "Test Review"
                detail: "Test detail"
                nickname: "Tester"
                ratings: []
            }) {
                _id
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);
        expect($response['status'])->toBe(200);
        $errors = $response['json']['errors'] ?? [];
        expect($errors)->not->toBeEmpty();
    });

    it('allows query with service token', function (): void {
        $token = serviceToken(['all']);

        $query = <<<'GRAPHQL'
        {
            products {
                edges {
                    node {
                        _id
                        sku
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], $token);
        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['errors'] ?? [])->toBeEmpty();
    });

    it('allows query with customer token', function (): void {
        $token = customerToken();

        $query = <<<'GRAPHQL'
        {
            products {
                edges {
                    node {
                        _id
                        sku
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], $token);
        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
    });

    it('denies query with expired token', function (): void {
        $token = expiredToken();

        $query = <<<'GRAPHQL'
        {
            products {
                edges {
                    node {
                        _id
                        sku
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], $token);
        // GraphQL might return 200 with auth error or 401
        if ($response['status'] === 200) {
            $errors = $response['json']['errors'] ?? [];
            expect($errors)->not->toBeEmpty();
        } else {
            expect($response['status'])->toBe(401);
        }
    });

    it('denies query with invalid token', function (): void {
        $token = invalidToken();

        $query = <<<'GRAPHQL'
        {
            products {
                edges {
                    node {
                        _id
                        sku
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], $token);
        if ($response['status'] === 200) {
            $errors = $response['json']['errors'] ?? [];
            expect($errors)->not->toBeEmpty();
        } else {
            expect($response['status'])->toBe(401);
        }
    });

});
