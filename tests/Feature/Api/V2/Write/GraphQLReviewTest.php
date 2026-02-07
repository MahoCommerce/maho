<?php

declare(strict_types=1);

/**
 * GraphQL Review Integration Tests (WRITE)
 *
 * WARNING: These tests CREATE real data in the database!
 * Only run with: ./vendor/bin/pest --group=write
 *
 * Includes regression test for args vs args.input bug in ReviewProcessor.
 *
 * @group write
 * @group graphql
 */

describe('GraphQL Review - Product Reviews Query', function () {

    it('returns reviews for a product', function () {
        $productId = fixtures('product_id');

        $query = <<<GRAPHQL
        {
            productReviewsReviews(productId: {$productId}, pageSize: 5) {
                edges {
                    node {
                        id
                        _id
                        title
                        detail
                        nickname
                        rating
                        status
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('productReviewsReviews');
    });

});

describe('GraphQL Review - Submit Review Mutation', function () {

    /**
     * Regression: submitReview was reading args instead of args.input
     * The ReviewProcessor was looking for productId in $context['args']
     * but API Platform GraphQL passes it in $context['args']['input']
     */
    it('submits a review successfully (regression: args vs args.input)', function () {
        $productId = fixtures('product_id');
        $timestamp = time();

        $query = <<<GRAPHQL
        mutation {
            submitReviewReview(input: {
                productId: {$productId},
                title: "Test Review {$timestamp}",
                detail: "This is an automated test review created at {$timestamp}.",
                nickname: "TestUser",
                rating: 4
            }) {
                review {
                    id
                    _id
                    productId
                    title
                    detail
                    nickname
                    rating
                    status
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->not->toHaveKey('errors');
        expect($response['json']['data']['submitReviewReview'])->not->toBeNull();

        $review = $response['json']['data']['submitReviewReview']['review'];
        expect($review['productId'])->toBe($productId);
        expect($review['title'])->toContain('Test Review');
        expect($review['nickname'])->toBe('TestUser');
        expect($review['rating'])->toBe(4);
        expect($review['status'])->toBeString(); // 'pending' typically
    });

    it('returns error when required fields are missing', function () {
        $query = <<<'GRAPHQL'
        mutation {
            submitReviewReview(input: {
                productId: 421,
                title: "",
                detail: "",
                nickname: "",
                rating: 0
            }) {
                review {
                    _id
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        // Should have errors due to empty required fields
        expect($response['json'])->toHaveKey('errors');
    });

});

describe('GraphQL Review - My Reviews Query', function () {

    it('returns customer reviews when authenticated', function () {
        $query = <<<'GRAPHQL'
        {
            myReviewsReviews {
                edges {
                    node {
                        id
                        _id
                        productId
                        productName
                        title
                        rating
                        status
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query, [], customerToken());

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('myReviewsReviews');
    });

});
