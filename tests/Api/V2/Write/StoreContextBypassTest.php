<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ApiV2Helper;

/**
 * API v2 store-context bypass tests (WRITE)
 *
 * The request-level listeners only check ?store= / X-Store-Code against the
 * token's store allowlist. These tests prove that store ids arriving in request
 * bodies (cart create, newsletter subscribe) or URI variables (store switch)
 * are checked too, that cart creation binds to the requested store context, and
 * that reviews are store-scoped for reads, moderation, and listing.
 *
 * Runs against a temporary second store view so multi-store semantics apply.
 *
 * @group write
 */

const CTXSCOPE_STORE_CODE = 'apitest_ctxscope';

function ctxScopeStoreId(): int
{
    return (int) ($GLOBALS['_ctxscope_store_id'] ?? 0);
}

function ctxScopeDefaultStoreId(): int
{
    return (int) ($GLOBALS['_ctxscope_default_store_id'] ?? 1);
}

function ctxScopeDefaultStoreCode(): string
{
    return (string) ($GLOBALS['_ctxscope_default_store_code'] ?? 'default');
}

/** Create a review directly via models: deterministic stores, no rate-limit cost. */
function ctxScopeCreateReview(int $statusId): int
{
    ApiV2Helper::ensureMahoBootstrapped();

    /** @var Mage_Review_Model_Review $review */
    $review = Mage::getModel('review/review');
    $review->setEntityPkValue((int) fixtures('product_id'))
        ->setEntityId($review->getEntityIdByCode(Mage_Review_Model_Review::ENTITY_PRODUCT_CODE))
        ->setStatusId($statusId)
        ->setTitle('Store scope test ' . uniqid())
        ->setDetail('Automated review created for store-context authorization tests.')
        ->setNickname('CtxScope')
        ->setStoreId(ctxScopeDefaultStoreId())
        ->setStores([ctxScopeDefaultStoreId()])
        ->save();

    $id = (int) $review->getId();
    trackCreated('review', $id);
    return $id;
}

function ctxScopePendingReviewId(): int
{
    static $id = null;
    return $id ??= ctxScopeCreateReview(Mage_Review_Model_Review::STATUS_PENDING);
}

function ctxScopeApprovedReviewId(): int
{
    static $id = null;
    return $id ??= ctxScopeCreateReview(Mage_Review_Model_Review::STATUS_APPROVED);
}

beforeAll(function (): void {
    ApiV2Helper::ensureMahoBootstrapped();

    $defaultStore = Mage::app()->getDefaultStoreView();
    $GLOBALS['_ctxscope_default_store_id'] = (int) $defaultStore->getId();
    $GLOBALS['_ctxscope_default_store_code'] = (string) $defaultStore->getCode();

    $existing = Mage::getModel('core/store')->load(CTXSCOPE_STORE_CODE, 'code');
    if ($existing->getId()) {
        $GLOBALS['_ctxscope_store_id'] = (int) $existing->getId();
        return;
    }

    $website = Mage::app()->getWebsite(1);
    $store = Mage::getModel('core/store');
    $store->setCode(CTXSCOPE_STORE_CODE)
        ->setWebsiteId((int) $website->getId())
        ->setGroupId((int) $website->getDefaultGroupId())
        ->setName('API Context Scope Test Store')
        ->setIsActive(1)
        ->setSortOrder(98)
        ->save();
    $GLOBALS['_ctxscope_store_id'] = (int) $store->getId();

    // The API server bootstraps per request off the shared cache; flush so it
    // sees the new store (and leaves single-store mode) immediately.
    Mage::app()->cleanCache();
});

afterAll(function (): void {
    ApiV2Helper::ensureMahoBootstrapped();
    $store = Mage::getModel('core/store')->load(CTXSCOPE_STORE_CODE, 'code');
    if ($store->getId()) {
        Mage::register('isSecureArea', true);
        try {
            $store->delete();
        } finally {
            Mage::unregister('isSecureArea');
        }
    }
    Mage::app()->cleanCache();
    cleanupTestData();
});

describe('Cart creation store binding', function (): void {

    it('binds a guest cart to the requested ?store= context', function (): void {
        $response = apiPost('/api/rest/v2/guest-carts?store=' . CTXSCOPE_STORE_CODE, []);

        expect($response['status'])->toBe(201);
        expect((int) $response['json']['storeId'])->toBe(ctxScopeStoreId());
        if (isset($response['json']['id'])) {
            trackCreated('quote', (int) $response['json']['id']);
        }
    });

    it('denies cart create with a body storeId outside the token allowlist', function (): void {
        $token = serviceToken(['carts/write'], [ctxScopeStoreId()]);

        // ?store= keeps the request-level allowlist check green; only the
        // body-level storeId points outside the allowlist.
        $response = apiPost('/api/rest/v2/carts?store=' . CTXSCOPE_STORE_CODE, [
            'storeId' => ctxScopeDefaultStoreId(),
        ], $token);

        expect($response['status'])->toBe(403);
    });

    it('allows cart create with a body storeId on the token allowlist', function (): void {
        $token = serviceToken(['carts/write'], [ctxScopeStoreId()]);

        $response = apiPost('/api/rest/v2/carts?store=' . CTXSCOPE_STORE_CODE, [
            'storeId' => ctxScopeStoreId(),
        ], $token);

        expect($response['status'])->toBeIn([200, 201]);
        expect((int) $response['json']['storeId'])->toBe(ctxScopeStoreId());
        if (isset($response['json']['id'])) {
            trackCreated('quote', (int) $response['json']['id']);
        }
    });

});

describe('Newsletter subscribe store allowlist', function (): void {

    it('denies subscribing with a body storeId outside the token allowlist', function (): void {
        $token = serviceToken(['newsletter/read'], [ctxScopeStoreId()]);

        $response = apiPost('/api/rest/v2/newsletter/subscribe?store=' . CTXSCOPE_STORE_CODE, [
            'email' => 'ctxscope+' . uniqid() . '@example.com',
            'storeId' => ctxScopeDefaultStoreId(),
        ], $token);

        expect($response['status'])->toBe(403);
    });

});

describe('Store switch allowlist', function (): void {

    it('denies switching to a store outside the token allowlist', function (): void {
        $token = serviceToken(['all'], [ctxScopeStoreId()]);

        $response = apiPost(
            '/api/rest/v2/stores/switch/' . ctxScopeDefaultStoreCode() . '?store=' . CTXSCOPE_STORE_CODE,
            [],
            $token,
        );

        expect($response['status'])->toBe(403);
    });

    it('allows switching to a store on the token allowlist', function (): void {
        $token = serviceToken(['all'], [ctxScopeStoreId()]);

        $response = apiPost(
            '/api/rest/v2/stores/switch/' . CTXSCOPE_STORE_CODE . '?store=' . CTXSCOPE_STORE_CODE,
            [],
            $token,
        );

        expect($response['status'])->toBeIn([200, 201]);
        expect($response['json']['code'])->toBe(CTXSCOPE_STORE_CODE);
    });

});

describe('Review store scoping', function (): void {

    it('hides an approved review from item reads in a store it is not assigned to', function (): void {
        $reviewId = ctxScopeApprovedReviewId();

        $inStore = apiGet("/api/rest/v2/reviews/{$reviewId}");
        expect($inStore['status'])->toBe(200);

        $otherStore = apiGet('/api/rest/v2/reviews/' . $reviewId . '?store=' . CTXSCOPE_STORE_CODE);
        expect($otherStore['status'])->toBe(404);
    });

    it('denies moderation of a review outside the token store allowlist', function (): void {
        $reviewId = ctxScopePendingReviewId();
        $token = serviceToken(['reviews/write', 'reviews/read'], [ctxScopeStoreId()]);

        $response = apiPut('/api/rest/v2/reviews/' . $reviewId . '?store=' . CTXSCOPE_STORE_CODE, [
            'status' => 'approved',
        ], $token);

        expect($response['status'])->toBe(403);
    });

    it('lets the moderation Put assign stores', function (): void {
        $reviewId = ctxScopePendingReviewId();
        $token = serviceToken(['reviews/write', 'reviews/read']);

        $response = apiPut("/api/rest/v2/reviews/{$reviewId}", [
            'status' => 'approved',
            'stores' => [ctxScopeDefaultStoreId(), ctxScopeStoreId()],
        ], $token);

        expect($response['status'])->toBe(200);
        expect($response['json']['status'])->toBe('approved');
        expect($response['json']['stores'])->toContain(ctxScopeStoreId());
        expect($response['json']['stores'])->toContain(ctxScopeDefaultStoreId());
    });

    it('rejects assigning a store outside the token allowlist', function (): void {
        $reviewId = ctxScopePendingReviewId();
        $token = serviceToken(['reviews/write', 'reviews/read'], [ctxScopeStoreId()]);

        $response = apiPut('/api/rest/v2/reviews/' . $reviewId . '?store=' . CTXSCOPE_STORE_CODE, [
            'status' => 'approved',
            'stores' => [ctxScopeDefaultStoreId()],
        ], $token);

        expect($response['status'])->toBe(403);
    });

    it('lets a store-restricted token moderate a review assigned to its store', function (): void {
        // The previous assignment test added the scope store to the review.
        $reviewId = ctxScopePendingReviewId();
        $token = serviceToken(['reviews/write', 'reviews/read'], [ctxScopeStoreId()]);

        $response = apiPut('/api/rest/v2/reviews/' . $reviewId . '?store=' . CTXSCOPE_STORE_CODE, [
            'status' => 'not_approved',
        ], $token);

        expect($response['status'])->toBe(200);
        expect($response['json']['status'])->toBe('not_approved');
    });

});

describe('Review moderation listing', function (): void {

    it('lists reviews across stores for a service token with reviews/read', function (): void {
        $pendingId = ctxScopePendingReviewId();
        $approvedId = ctxScopeApprovedReviewId();

        $response = apiGet('/api/rest/v2/reviews?pageSize=100', serviceToken(['reviews/read']));

        expect($response['status'])->toBe(200);
        $ids = array_map(fn(array $item): int => (int) $item['id'], getItems($response));
        expect($ids)->toContain($pendingId);
        expect($ids)->toContain($approvedId);
    });

    it('filters the listing by status and rejects unknown values', function (): void {
        $token = serviceToken(['reviews/read']);

        $filtered = apiGet('/api/rest/v2/reviews?status=not_approved&pageSize=100', $token);
        expect($filtered['status'])->toBe(200);
        foreach (getItems($filtered) as $item) {
            expect($item['status'])->toBe('not_approved');
        }

        $invalid = apiGet('/api/rest/v2/reviews?status=sideways', $token);
        expect($invalid['status'])->toBe(400);
    });

    it('limits a store-restricted token to reviews assigned to its stores', function (): void {
        // ctxScopePendingReviewId() carries the scope store since the
        // assignment test; the approved review stays default-store only.
        $visibleId = ctxScopePendingReviewId();
        $hiddenId = ctxScopeApprovedReviewId();
        $token = serviceToken(['reviews/read'], [ctxScopeStoreId()]);

        $response = apiGet(
            '/api/rest/v2/reviews?pageSize=100&store=' . CTXSCOPE_STORE_CODE,
            $token,
        );

        expect($response['status'])->toBe(200);
        $ids = array_map(fn(array $item): int => (int) $item['id'], getItems($response));
        expect($ids)->toContain($visibleId);
        expect($ids)->not->toContain($hiddenId);
    });

    it('returns an empty listing for anonymous and customer callers', function (): void {
        ctxScopeApprovedReviewId();

        $anonymous = apiGet('/api/rest/v2/reviews');
        expect($anonymous['status'])->toBe(200);
        expect(getItems($anonymous))->toBe([]);

        $customer = apiGet('/api/rest/v2/reviews', customerToken());
        expect($customer['status'])->toBe(200);
        expect(getItems($customer))->toBe([]);
    });

});
