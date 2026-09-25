<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

use Maho\Import\Importer\SearchTerms;
use Maho\Import\RowException;

uses(Tests\MahoBackendTestCase::class);

/**
 * @param list<list<string>> $rows
 */
function searchTermsCsv(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'search_terms') . '.csv';
    $handle = fopen($path, 'w');
    foreach ([['store_code', 'query_text', 'popularity', 'num_results', 'hours_ago'], ...$rows] as $row) {
        fputcsv($handle, $row, escape: '\\');
    }
    fclose($handle);
    return $path;
}

function searchTermsCleanup(): void
{
    foreach (Mage::getResourceModel('catalogsearch/query_collection')->addFieldToFilter('query_text', ['like' => 'imp search%']) as $query) {
        $query->delete();
    }
}

beforeEach(fn() => searchTermsCleanup());
afterEach(fn() => searchTermsCleanup());

it('creates a search term, dates it back and updates it on rerun', function (): void {
    $store = Mage::app()->getStore(1)->getCode();
    $path = searchTermsCsv([[$store, 'imp search lamp', '7', '3', '5']]);

    $result = (new SearchTerms())->import($path);
    expect($result->created)->toBe(1);

    $query = Mage::getResourceModel('catalogsearch/query_collection')->addFieldToFilter('query_text', 'imp search lamp')->getFirstItem();
    expect((int) $query->getStoreId())->toBe(1);
    expect((int) $query->getPopularity())->toBe(7);
    expect((int) $query->getNumResults())->toBe(3);
    expect((int) $query->getDisplayInTerms())->toBe(1);
    $age = time() - strtotime($query->getUpdatedAt() . ' UTC');
    expect($age)->toBeGreaterThanOrEqual(5 * 3600)->toBeLessThan(5 * 3600 + 60);

    $path = searchTermsCsv([[$store, 'imp search lamp', '9', '3', '5']]);
    $again = (new SearchTerms())->import($path);
    expect($again->created)->toBe(0)->and($again->updated)->toBe(1);
    $terms = Mage::getResourceModel('catalogsearch/query_collection')->addFieldToFilter('query_text', 'imp search lamp');
    expect($terms->count())->toBe(1);
    expect((int) $terms->getFirstItem()->getPopularity())->toBe(9);
    unlink($path);
});

it('rejects an unknown store and a popularity that is not a number', function (): void {
    $importer = new SearchTerms();
    expect(fn() => $importer->validate(searchTermsCsv([['no-such-store', 'imp search lamp', '7', '3', '5']])))
        ->toThrow(RowException::class, "line 2: unknown store code 'no-such-store'");
    expect(fn() => $importer->validate(searchTermsCsv([[Mage::app()->getStore(1)->getCode(), 'imp search lamp', 'many', '3', '5']])))
        ->toThrow(RowException::class, 'popularity must be a whole number');
});
