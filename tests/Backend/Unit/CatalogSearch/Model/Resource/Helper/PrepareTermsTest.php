<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Return the text that the helper binds to :query in its chooseFulltext() condition.
 */
function boundSearchQuery(Mage_CatalogSearch_Model_Resource_Helper_Mysql|Mage_CatalogSearch_Model_Resource_Helper_Sqlite $helper, string $searchText): string
{
    return implode(' ', $helper->prepareTerms($searchText)[0]);
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
