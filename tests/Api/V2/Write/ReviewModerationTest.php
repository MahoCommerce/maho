<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Review Moderation Tests (WRITE)
 *
 * Status changes must be possible for admin/service tokens and impossible for
 * customer tokens. Also covers the ratings breakdown and stores read fields.
 *
 * A single submitted review is shared across tests: review submission is
 * rate-limited (10/hour per customer and per IP), so each test creating its
 * own review would eat into the shared budget of the whole suite run.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

function pestModerationReviewId(): int
{
    static $id = null;
    if ($id !== null) {
        return $id;
    }

    $productId = fixtures('product_id');

    $response = apiPost("/api/rest/v2/products/{$productId}/reviews", [
        'title' => 'Moderation test ' . uniqid(),
        'detail' => 'Automated review created to exercise the moderation endpoint.',
        'nickname' => 'PestModeration',
        'rating' => 4,
    ], customerToken());

    expect($response['status'])->toBeIn([200, 201]);
    expect($response['json']['status'])->toBe('pending');
    $id = (int) $response['json']['id'];
    expect($id)->toBeGreaterThan(0);
    trackCreated('review', $id);

    return $id;
}

describe('Review moderation', function (): void {

    it('denies status changes with a customer token', function (): void {
        $reviewId = pestModerationReviewId();

        $response = apiPut("/api/rest/v2/reviews/{$reviewId}", [
            'status' => 'approved',
        ], customerToken());

        expect($response['status'])->toBeForbidden();

        // The review is still pending (author may read their own pending review).
        $get = apiGet("/api/rest/v2/reviews/{$reviewId}", customerToken());
        expect($get['status'])->toBe(200);
        expect($get['json']['status'])->toBe('pending');
    });

    it('denies status changes without the reviews/write permission', function (): void {
        $response = apiPut('/api/rest/v2/reviews/' . pestModerationReviewId(), [
            'status' => 'approved',
        ], serviceToken(['cms-pages/write']));

        expect($response['status'])->toBeForbidden();
    });

    it('lets a service token approve the pending review', function (): void {
        $reviewId = pestModerationReviewId();
        $token = serviceToken(['reviews/write', 'reviews/read']);

        $approve = apiPut("/api/rest/v2/reviews/{$reviewId}", [
            'status' => 'approved',
        ], $token);

        expect($approve['status'])->toBe(200);
        expect($approve['json']['status'])->toBe('approved');

        $get = apiGet("/api/rest/v2/reviews/{$reviewId}", $token);
        expect($get['status'])->toBe(200);
        expect($get['json']['status'])->toBe('approved');

        // Read enrichments: per-rating breakdown and assigned stores.
        expect($get['json']['ratings'])->toBeArray();
        expect($get['json']['stores'])->toBeArray();
        expect($get['json']['stores'])->not->toBeEmpty();
        if ($get['json']['ratings'] !== []) {
            expect($get['json']['ratings'][0])->toHaveKeys(['code', 'value', 'percent']);
        }
    });

    it('keeps the status when a moderation Put only touches stores', function (): void {
        // Approved by the previous test; a stores-only body must not demote it.
        $reviewId = pestModerationReviewId();
        $token = serviceToken(['reviews/write', 'reviews/read']);

        $response = apiPut("/api/rest/v2/reviews/{$reviewId}", [
            'stores' => [1],
        ], $token);

        expect($response['status'])->toBe(200);
        expect($response['json']['status'])->toBe('approved');
    });

    it('rejects a moderation Put with neither status nor stores', function (): void {
        $response = apiPut('/api/rest/v2/reviews/' . pestModerationReviewId(), [], adminToken());

        expect($response['status'])->toBe(400);
    });

    it('lets an admin token move the review to not_approved', function (): void {
        $reviewId = pestModerationReviewId();

        $reject = apiPut("/api/rest/v2/reviews/{$reviewId}", [
            'status' => 'not_approved',
        ], adminToken());

        expect($reject['status'])->toBe(200);
        expect($reject['json']['status'])->toBe('not_approved');
    });

    it('hides the non-approved review from a service token without reviews/read', function (): void {
        // Left at not_approved by the previous test.
        $reviewId = pestModerationReviewId();

        $unscoped = apiGet("/api/rest/v2/reviews/{$reviewId}", serviceToken(['newsletter/read']));
        expect($unscoped['status'])->toBe(404);

        $scoped = apiGet("/api/rest/v2/reviews/{$reviewId}", serviceToken(['reviews/read']));
        expect($scoped['status'])->toBe(200);
        expect($scoped['json']['status'])->not->toBe('approved');
    });

    it('rejects an invalid status value', function (): void {
        $response = apiPut('/api/rest/v2/reviews/' . pestModerationReviewId(), [
            'status' => 'sideways',
        ], adminToken());

        expect($response['status'])->toBeGreaterThanOrEqual(400);
        expect($response['status'])->toBeLessThan(500);
    });

});
