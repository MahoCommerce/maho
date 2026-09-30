<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Return the text that the MySQL helper binds to :query in MATCH ... AGAINST (:query IN BOOLEAN MODE).
 */
function mysqlBooleanQuery(string $searchText): string
{
    $helper = new Mage_CatalogSearch_Model_Resource_Helper_Mysql('catalogsearch');

    return trim(implode(' ', $helper->prepareTerms($searchText)[0]));
}

it('removes each operator and bracket that MySQL rejects in boolean mode', function (string $searchText, string $expected) {
    expect(mysqlBooleanQuery($searchText))->toBe($expected);
})->with([
    'trailing minus' => ['test -', '"test"'],
    'trailing plus' => ['ETM +', '"ETM"'],
    'trailing tilde' => ['test ~', '"test"'],
    'trailing less than' => ['test <', '"test"'],
    'trailing greater than' => ['test >', '"test"'],
    'trailing pipe' => ['Trish Suhr |', '"Trish" "Suhr"'],
    'two trailing operators' => ['foo - -', '"foo"'],
    'operator before a closing bracket' => ['(test -)', '( "test" )'],
    'two operators before a term' => ['test - - brush', '"test" - "brush"'],
    'two operators before a bracket' => ['test + - (brush)', '"test" - ( "brush" )'],
    'standalone asterisk' => ['test * brush', '"test" "brush"'],
    'closing bracket before the opening bracket' => [') test (', '"test" ( )'],
]);
