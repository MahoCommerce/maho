<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * The rating of a review that the API submits: the stars of the body become the vote of the review.
 *
 * @group write
 */

beforeEach(function (): void {
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
});

afterAll(function (): void {
    cleanupTestData();
});

/**
 * @return list<int> the value of each vote of the review $reviewId
 */
function reviewSubmitVoteValues(int $reviewId): array
{
    $votes = Mage::getModel('rating/rating_option_vote')->getResourceCollection()->setReviewFilter($reviewId);
    return array_map(fn($vote): int => (int) $vote->getValue(), array_values($votes->getItems()));
}

function reviewSubmitGraphQl(?int $rating): array
{
    $productId = fixtures('product_id');
    $ratingArg = $rating === null ? '' : ", rating: {$rating}";
    $query = <<<GRAPHQL
    mutation {
        submitReview(input: {productId: {$productId}, title: "Rating test", detail: "Rating test detail", nickname: "Tester"{$ratingArg}}) {
            review { _id rating }
        }
    }
    GRAPHQL;
    return gqlQuery($query, [], customerToken());
}

it('stores the stars of a REST body as the vote of the review', function (): void {
    $response = apiPost('/api/rest/v2/products/' . fixtures('product_id') . '/reviews', [
        'title' => 'Rating test',
        'detail' => 'Rating test detail',
        'nickname' => 'Tester',
        'rating' => 2,
    ], customerToken());

    expect($response['status'])->toBeIn([200, 201])
        ->and($response['json']['rating'])->toBe(2);
    $reviewId = (int) $response['json']['id'];
    trackCreated('review', $reviewId);

    expect(reviewSubmitVoteValues($reviewId))->toBe([2]);
});

it('saves a review with no vote when the REST body has no rating, as the storefront form does', function (): void {
    $response = apiPost('/api/rest/v2/products/' . fixtures('product_id') . '/reviews', [
        'title' => 'Rating test',
        'detail' => 'Rating test detail',
        'nickname' => 'Tester',
    ], customerToken());

    expect($response['status'])->toBeIn([200, 201]);
    $reviewId = (int) $response['json']['id'];
    trackCreated('review', $reviewId);

    expect(reviewSubmitVoteValues($reviewId))->toBe([])
        ->and($response['json']['rating'] ?? null)->toBeNull();

    $read = apiGet('/api/rest/v2/reviews/' . $reviewId, adminToken());
    // Null fields are omitted from REST responses (skip_null_values)
    expect($read['status'])->toBe(200)
        ->and($read['json'])->not->toHaveKey('rating');
});

it('refuses stars out of 1 to 5 in a REST body with a 422 on rating', function (int $rating): void {
    $response = apiPost('/api/rest/v2/products/' . fixtures('product_id') . '/reviews', [
        'title' => 'Rating test',
        'detail' => 'Rating test detail',
        'nickname' => 'Tester',
        'rating' => $rating,
    ], customerToken());

    expect($response['status'])->toBe(422)
        ->and(array_column($response['json']['details']['errors'] ?? [], 'field'))->toBe(['rating']);
})->with([0, 6]);

it('refuses stars out of 1 to 5 in the submitReview mutation with a 422', function (int $rating): void {
    $response = reviewSubmitGraphQl($rating);

    expect($response['json']['errors'][0]['message'] ?? null)->toBe('Rating must be between 1 and 5')
        ->and($response['json']['errors'][0]['extensions']['status'] ?? null)->toBe(422);
})->with([0, 6]);

it('saves a review with no vote when the submitReview mutation has no rating', function (): void {
    $response = reviewSubmitGraphQl(null);

    expect($response['json'])->not->toHaveKey('errors');
    $reviewId = (int) $response['json']['data']['submitReview']['review']['_id'];
    trackCreated('review', $reviewId);

    expect(reviewSubmitVoteValues($reviewId))->toBe([]);
});
