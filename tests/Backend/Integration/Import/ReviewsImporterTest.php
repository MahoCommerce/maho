<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\Importer\Reviews;
use Maho\Import\RowException;

uses(Tests\MahoBackendTestCase::class);

/**
 * @param list<list<string>> $rows
 */
function reviewsCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'reviews') . '.csv';
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    return $path;
}

function reviewsCleanup(): void
{
    $reviews = Mage::getModel('review/review')->getCollection()->addFieldToFilter('nickname', 'Imp Reviewer');
    foreach ($reviews as $review) {
        $review->delete();
    }
}

beforeEach(fn() => reviewsCleanup());
afterEach(fn() => reviewsCleanup());

it('creates a review with rating votes, updates it on rerun and aggregates', function (): void {
    $product = loadSimplePricedProduct();
    $store = Mage::app()->getStore(1)->getCode();
    $path = reviewsCsv([
        ['sku', 'store_code', 'nickname', 'title', 'detail', 'quality', 'value', 'price', 'created_at'],
        [$product->getSku(), $store, 'Imp Reviewer', 'Imp Title', 'Imp detail text', '5', '3', '4', '2026-01-02 10:00:00'],
    ]);

    $result = (new Reviews())->import($path);
    expect($result->created)->toBe(1);

    $review = Mage::getModel('review/review')->getCollection()->addFieldToFilter('nickname', 'Imp Reviewer')->getFirstItem();
    expect($review->getTitle())->toBe('Imp Title');
    expect((int) $review->getEntityPkValue())->toBe((int) $product->getId());
    expect((int) $review->getStatusId())->toBe(Mage_Review_Model_Review::STATUS_APPROVED);
    expect($review->getCreatedAt())->toBe('2026-01-02 10:00:00');
    $votes = Mage::getModel('rating/rating_option_vote')->getCollection()->addFieldToFilter('review_id', $review->getId());
    expect($votes->count())->toBe(3);
    $summary = Mage::getModel('review/review_summary')->load($product->getId());
    expect((int) $summary->getReviewsCount())->toBeGreaterThanOrEqual(1);
    $storeSummary = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchRow(
        'SELECT reviews_count, rating_summary FROM ' . Mage::getSingleton('core/resource')->getTableName('review/review_aggregate') . ' WHERE entity_pk_value = ? AND store_id = ?',
        [(int) $product->getId(), 1],
    );
    expect((int) $storeSummary['reviews_count'])->toBe(1);
    expect((int) $storeSummary['rating_summary'])->toBe(80);

    $again = (new Reviews())->import($path);
    expect($again->created)->toBe(0)->and($again->updated)->toBe(1);
    expect(Mage::getModel('review/review')->getCollection()->addFieldToFilter('nickname', 'Imp Reviewer')->count())->toBe(1);
    expect(Mage::getModel('rating/rating_option_vote')->getCollection()->addFieldToFilter('review_id', $review->getId())->count())->toBe(3);
    unlink($path);
});

it('rejects an unknown sku, a bad status and a vote outside 1 to 5', function (): void {
    $product = loadSimplePricedProduct();
    $store = Mage::app()->getStore(1)->getCode();
    $importer = new Reviews();
    $header = ['sku', 'store_code', 'nickname', 'title', 'detail', 'status', 'quality'];

    $path = reviewsCsv([$header, ['NO-SUCH-SKU', $store, 'Imp Reviewer', 'T', 'D', '', '']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, "line 2: unknown sku 'NO-SUCH-SKU'");
    unlink($path);

    $path = reviewsCsv([$header, [$product->getSku(), $store, 'Imp Reviewer', 'T', 'D', 'maybe', '']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, "status 'maybe'");
    unlink($path);

    $path = reviewsCsv([$header, [$product->getSku(), $store, 'Imp Reviewer', 'T', 'D', '', '9']]);
    expect(fn() => $importer->validate($path))->toThrow(RowException::class, 'quality must be a whole number from 1 to 5');
    unlink($path);
});
