<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Return the text that Mage_CatalogSearch_Model_Resource_Fulltext::prepareResult() binds to :query.
 */
function boundSearchQuery(
    Mage_CatalogSearch_Model_Resource_Helper_Mysql|Mage_CatalogSearch_Model_Resource_Helper_Sqlite|Mage_CatalogSearch_Model_Resource_Helper_Pgsql $helper,
    string $searchText,
    int $maxWords = 0,
    int $minWordLength = 0,
): string {
    return implode(' ', $helper->prepareTerms($searchText, $maxWords, $minWordLength)[0]);
}

it('gives MySQL each word as a quoted phrase and drops operators and brackets', function (string $searchText, string $expected) {
    $helper = new Mage_CatalogSearch_Model_Resource_Helper_Mysql('catalogsearch');

    expect(boundSearchQuery($helper, $searchText))->toBe($expected);
})->with([
    'trailing minus' => ['test -', '"test"'],
    'trailing plus' => ['ETM +', '"ETM"'],
    'trailing tilde' => ['test ~', '"test"'],
    'two trailing operators' => ['foo - -', '"foo"'],
    'operator between words' => ['test - brush', '"test" "brush"'],
    'standalone asterisk' => ['test * brush', '"test" "brush"'],
    'brackets' => [') test (brush -)', '"test" "brush"'],
]);

it('gives SQLite the words without operators and brackets', function (string $searchText, string $expected) {
    $helper = new Mage_CatalogSearch_Model_Resource_Helper_Sqlite('catalogsearch');

    expect(boundSearchQuery($helper, $searchText))->toBe($expected);
})->with([
    'trailing minus' => ['test -', 'test'],
    'brackets' => ['(test)', 'test'],
]);

it('drops the words that are too short and keeps the first words up to the limit', function (string $helperClass, string $expected) {
    expect(boundSearchQuery(new $helperClass('catalogsearch'), 'x1 red x1 shirt with sleeves', 3, 3))->toBe($expected);
})->with([
    'MySQL' => [Mage_CatalogSearch_Model_Resource_Helper_Mysql::class, '"red" "shirt" "with"'],
    'SQLite' => [Mage_CatalogSearch_Model_Resource_Helper_Sqlite::class, 'red shirt with'],
    'PostgreSQL' => [Mage_CatalogSearch_Model_Resource_Helper_Pgsql::class, 'red shirt with'],
]);
