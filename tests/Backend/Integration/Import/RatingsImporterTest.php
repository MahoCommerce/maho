<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\Importer\Ratings;
use Maho\Import\RowException;

uses(Tests\MahoBackendTestCase::class);

/**
 * @param list<list<string>> $rows
 */
function ratingsCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'ratings') . '.csv';
    $handle = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    return $path;
}

function ratingsCleanup(): void
{
    $imported = Mage::getModel('rating/rating')->load('Imp Rating', 'rating_code');
    if ($imported->getId()) {
        $imported->delete();
    }
}

beforeEach(fn() => ratingsCleanup());
afterEach(fn() => ratingsCleanup());

it('creates a rating with five options on every store, hides the others and is idempotent', function (): void {
    $path = ratingsCsv([
        ['code', 'position'],
        ['Imp Rating', '1'],
    ]);

    $result = (new Ratings())->import($path);
    expect($result->created)->toBe(1);

    $rating = Mage::getModel('rating/rating')->load('Imp Rating', 'rating_code');
    expect($rating->getId())->not->toBeNull();
    expect((int) $rating->getPosition())->toBe(1);
    expect(array_map('intval', (array) $rating->getStores()))->toContain(1);
    expect(Mage::getResourceModel('rating/rating_option_collection')->addRatingFilter($rating->getId())->count())->toBe(5);
    $quality = Mage::getModel('rating/rating')->load('Quality', 'rating_code');
    expect((array) $quality->getStores())->toBe([]);

    $again = (new Ratings())->import($path);
    expect($again->created)->toBe(0)->and($again->updated)->toBe(1);
    expect(Mage::getResourceModel('rating/rating_option_collection')->addRatingFilter($rating->getId())->count())->toBe(5);
    unlink($path);
});

it('limits a rating to the listed stores', function (): void {
    $store = Mage::app()->getStore(1)->getCode();
    $path = ratingsCsv([
        ['code', 'stores'],
        ['Imp Rating', $store],
    ]);
    (new Ratings())->import($path);
    $rating = Mage::getModel('rating/rating')->load('Imp Rating', 'rating_code');
    expect(array_map('intval', (array) $rating->getStores()))->toBe([1]);
    unlink($path);
});

it('rejects a repeated code, a non numeric position and an unknown store', function (): void {
    $path = ratingsCsv([
        ['code', 'position'],
        ['Imp Rating', '1'],
        ['Imp Rating', '2'],
    ]);
    expect(fn() => (new Ratings())->validate($path))->toThrow(RowException::class, 'line 3');
    unlink($path);

    $path = ratingsCsv([
        ['code', 'position'],
        ['Imp Rating', 'first'],
    ]);
    expect(fn() => (new Ratings())->validate($path))->toThrow(RowException::class, 'position');
    unlink($path);

    $path = ratingsCsv([
        ['code', 'stores'],
        ['Imp Rating', 'no_such_store'],
    ]);
    expect(fn() => (new Ratings())->validate($path))->toThrow(RowException::class, 'no_such_store');
    unlink($path);
});
