<?php

declare(strict_types=1);

/**
 * API v2 GraphQL Permission Tests
 *
 * Tests that GraphQL queries and mutations enforce permissions correctly.
 * Verifies public queries work without auth and mutations require correct tokens.
 *
 * Note: Custom QueryCollection operations (productsProducts, categoriesCategories, etc.)
 * do NOT support Relay pagination args (first/last/before/after). Only default
 * collection_query operations get those automatically.
 *
 * @group write
 */

afterAll(function () {
    cleanupTestData();
});

describe('GraphQL Public Queries', function () {

    it('allows public product query without auth', function () {
        $query = <<<'GRAPHQL'
        {
            productsProducts {
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
        expect($response['json']['data'])->toHaveKey('productsProducts');
    });

    it('allows public category query without auth', function () {
        $query = <<<'GRAPHQL'
        {
            categoriesCategories {
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
        expect($response['json']['data'])->toHaveKey('categoriesCategories');
    });

    it('allows public CMS page query without auth', function () {
        $query = <<<'GRAPHQL'
        {
            cmsPagesCmsPages {
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
        expect($response['json']['data'])->toHaveKey('cmsPagesCmsPages');
    });

    it('allows public blog post query without auth', function () {
        $query = <<<'GRAPHQL'
        {
            blogPostsBlogPosts {
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
        expect($response['json']['data'])->toHaveKey('blogPostsBlogPosts');
    });

});

describe('GraphQL Mutation Permissions', function () {

    it('denies cart creation without valid masked ID', function () {
        $query = <<<'GRAPHQL'
        mutation {
            createCartCart(input: { maskedId: "invalid" }) {
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

    it('denies review submission without auth', function () {
        $query = <<<'GRAPHQL'
        mutation {
            submitReviewReview(input: {
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

    it('allows query with service token', function () {
        $token = serviceToken(['all']);

        $query = <<<'GRAPHQL'
        {
            productsProducts {
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

    it('allows query with customer token', function () {
        $token = customerToken();

        $query = <<<'GRAPHQL'
        {
            productsProducts {
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

    it('denies query with expired token', function () {
        $token = expiredToken();

        $query = <<<'GRAPHQL'
        {
            productsProducts {
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

    it('denies query with invalid token', function () {
        $token = invalidToken();

        $query = <<<'GRAPHQL'
        {
            productsProducts {
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
