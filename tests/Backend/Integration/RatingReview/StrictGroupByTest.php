<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rating
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Sqlite;

uses(Tests\MahoBackendTestCase::class);

/**
 * Skip the entire suite on SQLite: SQLite does not enforce GROUP BY strictness.
 * Tests are meaningful only on MySQL (ONLY_FULL_GROUP_BY) and PostgreSQL.
 *
 * On MySQL, we force a fresh connection (by clearing the resource singleton's
 * connection cache via reflection) AFTER enabling developer mode so that
 * _initConnection() sets ONLY_FULL_GROUP_BY.
 */
beforeEach(function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    if ($adapter instanceof Sqlite) {
        $this->markTestSkipped('SQLite does not enforce strict GROUP BY; test is MySQL/PostgreSQL only.');
    }

    Mage::setIsDeveloperMode(true);

    // Force a fresh connection so ONLY_FULL_GROUP_BY is applied on MySQL.
    $resource = Mage::getSingleton('core/resource');
    $reflProp = new ReflectionProperty($resource, '_connections');
    $reflProp->setAccessible(true);
    $reflProp->setValue($resource, []);
});

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Insert a minimal review + rating vote fixture.
 * Returns an array of IDs for cleanup, including:
 *   review_id, vote_id, entity_pk_value, rating_id, option_id, aggregate_ids
 */
function ratingReviewInsertFixture(): array
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    // Use an entity_pk_value that won't clash with existing product data.
    // Since sample data products start from 1, use a high fake product ID.
    $entityPkValue = 999999;
    $storeId = (int) $adapter->fetchOne(
        "SELECT store_id FROM {$resource->getTableName('core/store')} WHERE store_id > 0 LIMIT 1",
    );

    // Get the first rating (Quality) and its option_id=5 (value=5, the highest).
    $ratingId = (int) $adapter->fetchOne(
        "SELECT rating_id FROM {$resource->getTableName('rating/rating')} ORDER BY rating_id LIMIT 1",
    );
    $optionId = (int) $adapter->fetchOne(
        "SELECT option_id FROM {$resource->getTableName('rating/rating_option')} WHERE rating_id = ? ORDER BY value DESC LIMIT 1",
        [$ratingId],
    );
    if (!$ratingId || !$optionId || !$storeId) {
        throw new \RuntimeException('Insufficient sample data for rating/review fixture.');
    }

    // Ensure the rating is assigned to this store (required for aggregate queries).
    $existingRatingStore = $adapter->fetchOne(
        "SELECT COUNT(*) FROM {$resource->getTableName('rating/rating_store')} WHERE rating_id = ? AND store_id = ?",
        [$ratingId, $storeId],
    );
    if (!$existingRatingStore) {
        $adapter->insert($resource->getTableName('rating/rating_store'), [
            'rating_id' => $ratingId,
            'store_id'  => $storeId,
        ]);
    }

    // Insert a review.
    $reviewEntityId = (int) $adapter->fetchOne(
        "SELECT entity_id FROM {$resource->getTableName('review/review_entity')} WHERE entity_code = 'product' LIMIT 1",
    );
    $approvedStatusId = (int) $adapter->fetchOne(
        "SELECT status_id FROM {$resource->getTableName('review/review_status')} WHERE status_code = 'Approved' LIMIT 1",
    );
    $adapter->insert($resource->getTableName('review/review'), [
        'entity_id'      => $reviewEntityId,
        'entity_pk_value' => $entityPkValue,
        'status_id'      => $approvedStatusId,
    ]);
    $reviewId = (int) $adapter->lastInsertId($resource->getTableName('review/review'));

    // Insert review_detail.
    $adapter->insert($resource->getTableName('review/review_detail'), [
        'review_id'  => $reviewId,
        'store_id'   => $storeId,
        'title'      => 'Test Review',
        'detail'     => 'Test review detail for strict GROUP BY tests.',
        'nickname'   => 'Tester',
        'customer_id' => null,
    ]);

    // Link review to store.
    $adapter->insert($resource->getTableName('review/review_store'), [
        'review_id' => $reviewId,
        'store_id'  => $storeId,
    ]);

    // Insert a rating vote.
    $optionData = $adapter->fetchRow(
        "SELECT * FROM {$resource->getTableName('rating/rating_option')} WHERE option_id = ?",
        [$optionId],
    );
    $percent = ($optionData['value'] / 5) * 100;
    $adapter->insert($resource->getTableName('rating/rating_option_vote'), [
        'option_id'      => $optionId,
        'review_id'      => $reviewId,
        'percent'        => $percent,
        'value'          => $optionData['value'],
        'entity_pk_value' => $entityPkValue,
        'rating_id'      => $ratingId,
        'remote_ip'      => '127.0.0.1',
        'remote_ip_long' => ip2long('127.0.0.1'),
        'customer_id'    => null,
    ]);
    $voteId = (int) $adapter->lastInsertId($resource->getTableName('rating/rating_option_vote'));

    return [
        'review_id'      => $reviewId,
        'vote_id'        => $voteId,
        'entity_pk_value' => $entityPkValue,
        'rating_id'      => $ratingId,
        'option_id'      => $optionId,
        'store_id'       => $storeId,
        'rating_store_inserted' => !$existingRatingStore,
    ];
}

/**
 * Remove the fixture created by ratingReviewInsertFixture().
 */
function ratingReviewCleanFixture(array $fixture): void
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');

    $adapter->delete(
        $resource->getTableName('rating/rating_vote_aggregated'),
        ['entity_pk_value = ?' => $fixture['entity_pk_value']],
    );
    $adapter->delete(
        $resource->getTableName('rating/rating_option_vote'),
        ['vote_id = ?' => $fixture['vote_id']],
    );
    $adapter->delete(
        $resource->getTableName('review/review_store'),
        ['review_id = ?' => $fixture['review_id']],
    );
    $adapter->delete(
        $resource->getTableName('review/review_detail'),
        ['review_id = ?' => $fixture['review_id']],
    );
    $adapter->delete(
        $resource->getTableName('review/review'),
        ['review_id = ?' => $fixture['review_id']],
    );
    if ($fixture['rating_store_inserted']) {
        $adapter->delete(
            $resource->getTableName('rating/rating_store'),
            ['rating_id = ? AND store_id = ?' => [$fixture['rating_id'], $fixture['store_id']]],
        );
    }
}

// ─── Requirement 1 ────────────────────────────────────────────────────────────

it('loads the Rating collection with addEntityFilter applied without strict GROUP BY errors', function () {
    /** @var Mage_Rating_Model_Resource_Rating_Collection $collection */
    $collection = Mage::getResourceModel('rating/rating_collection');
    $collection->addEntityFilter('product');

    expect(fn() => $collection->load())->not->toThrow(\Throwable::class);
    expect($collection->isLoaded())->toBeTrue();
    expect($collection->getSize())->toBeGreaterThanOrEqual(0);
});

// ─── Requirement 2 ────────────────────────────────────────────────────────────


it('aggregates rating votes via getEntityAggregate and getReviewSummary without strict GROUP BY errors', function () {
    $fixture = ratingReviewInsertFixture();
    try {
        /** @var Mage_Rating_Model_Resource_Rating $resource */
        $resource = Mage::getResourceModel('rating/rating');

        // getEntityAggregate internally calls _getEntitySummaryData which uses GROUP BY
        $ratingModel = Mage::getModel('rating/rating');
        $ratingModel->setEntityPkValue($fixture['entity_pk_value']);
        expect(fn() => $resource->getEntitySummary($ratingModel))->not->toThrow(\Throwable::class);

        // getReviewSummary uses GROUP BY rating_vote.review_id, review_store.store_id
        $ratingModel2 = Mage::getModel('rating/rating');
        $ratingModel2->setReviewId($fixture['review_id']);
        expect(fn() => $resource->getReviewSummary($ratingModel2, false))->not->toThrow(\Throwable::class);
    } finally {
        ratingReviewCleanFixture($fixture);
    }
});

// ─── Requirement 3 ────────────────────────────────────────────────────────────

it('re-aggregates the rating option table via aggregate() without strict GROUP BY errors', function () {
    $fixture = ratingReviewInsertFixture();
    try {
        /** @var Mage_Rating_Model_Resource_Rating_Option $optionResource */
        $optionResource = Mage::getResourceModel('rating/rating_option');

        // aggregate() calls aggregateEntityByRatingId() which runs a GROUP BY query.
        expect(
            fn() => $optionResource->aggregateEntityByRatingId($fixture['rating_id'], $fixture['entity_pk_value']),
        )->not->toThrow(\Throwable::class);

        // Verify a row was written to the aggregated table.
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $count = (int) $adapter->fetchOne(
            "SELECT COUNT(*) FROM {$resource->getTableName('rating/rating_vote_aggregated')}
             WHERE rating_id = ? AND entity_pk_value = ?",
            [$fixture['rating_id'], $fixture['entity_pk_value']],
        );
        expect($count)->toBeGreaterThanOrEqual(1);
    } finally {
        ratingReviewCleanFixture($fixture);
    }
});

// ─── Requirement 4 ────────────────────────────────────────────────────────────

it('re-aggregates the review summary table without strict GROUP BY errors', function () {
    /** @var Mage_Review_Model_Resource_Review_Summary $summaryResource */
    $summaryResource = Mage::getResourceModel('review/review_summary');

    // reAggregate() issues a GROUP BY query on review_entity_summary.
    // It reads a summary collection from the Mage_Rating_Model_Resource_Rating resource.
    // We call it with an empty summary map (won't touch data) to test the GROUP BY query itself.
    expect(fn() => $summaryResource->reAggregate([]))->not->toThrow(\Throwable::class);
});

// ─── Requirement 5 ────────────────────────────────────────────────────────────

it('loads the Review collection with addReviewsTotalCount applied without strict GROUP BY errors', function () {
    /** @var Mage_Review_Model_Resource_Review_Collection $collection */
    $collection = Mage::getResourceModel('review/review_collection');
    $collection->addReviewsTotalCount();

    $thrownException = null;
    try {
        $collection->load();
    } catch (\Throwable $e) {
        $thrownException = $e;
    }

    expect($thrownException)->toBeNull('Expected no exception, got: ' . ($thrownException?->getMessage() ?? ''));
    expect($collection->isLoaded())->toBeTrue();
});

// ─── Requirement 6 ────────────────────────────────────────────────────────────

it('loads the Review Product collection with store filters applied without strict GROUP BY errors', function () {
    // _applyStoresFilterToSelect uses GROUP BY rt.review_id when multiple store IDs are given.
    // We pass both store 0 (admin) and store 1 (default) to trigger the multi-store path.
    /** @var Mage_Review_Model_Resource_Review_Product_Collection $collection */
    $collection = Mage::getResourceModel('review/review_product_collection');
    $collection->setStoreFilter([0, 1]);
    // Limit to avoid loading all EAV attributes for every product in the catalog.
    $collection->setPageSize(1);

    $thrownException = null;
    try {
        $collection->load();
    } catch (\Throwable $e) {
        $thrownException = $e;
    }

    expect($thrownException)->toBeNull('Expected no exception, got: ' . ($thrownException?->getMessage() ?? ''));
    expect($collection->isLoaded())->toBeTrue();
});
