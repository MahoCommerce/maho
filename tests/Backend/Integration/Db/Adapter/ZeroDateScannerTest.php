<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use MahoCLI\Helper\ZeroDateScanner;
use Maho\Db\Adapter\Pdo\Mysql;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    if (!($adapter instanceof Mysql)) {
        $this->markTestSkipped('MySQL-only test: zero dates only exist on MySQL/MariaDB.');
    }
});

it('detects legacy zero-date values and defaults, and rescans clean after the NULL fix', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = 'zero_date_scanner_probe';
    $modeBefore = (string) $adapter->fetchOne('SELECT @@SESSION.sql_mode');

    try {
        // Seed legacy data the way it actually got there: under the relaxed mode
        $adapter->query("SET SESSION sql_mode=''");
        $adapter->query(
            "CREATE TABLE {$table} ("
            . ' id INT PRIMARY KEY,'
            . ' d DATE NULL,'
            . ' dt DATETIME NULL,'
            . " d_def DATE NULL DEFAULT '0000-00-00',"
            . ' locked DATE NOT NULL'
            . ')',
        );
        $adapter->query("INSERT INTO {$table} VALUES (1, '0000-00-00', '0000-00-00 00:00:00', NULL, '0000-00-00')");
        $adapter->query('SET SESSION sql_mode = ' . $adapter->quote($modeBefore));

        $values = array_values(array_filter(
            ZeroDateScanner::findZeroDateValues($adapter),
            fn(array $f) => $f['table'] === $table,
        ));
        $byColumn = array_column($values, null, 'column');
        expect(array_keys($byColumn))->toEqualCanonicalizing(['d', 'dt', 'locked']);
        expect($byColumn['d']['nullable'])->toBeTrue();
        expect($byColumn['d']['rows'])->toBe(1);
        expect($byColumn['locked']['nullable'])->toBeFalse();

        $defaults = array_values(array_filter(
            ZeroDateScanner::findZeroDateDefaults($adapter),
            fn(array $f) => $f['table'] === $table,
        ));
        expect(array_column($defaults, 'column'))->toBe(['d_def']);

        // The fix the legacy:fix-zero-dates command applies for nullable columns
        foreach (['d', 'dt'] as $column) {
            $adapter->update(
                $table,
                [$column => null],
                ZeroDateScanner::zeroDatePredicate($adapter, $column),
            );
        }
        $adapter->query("ALTER TABLE {$table} ALTER COLUMN d_def SET DEFAULT NULL");

        $remainingValues = array_values(array_filter(
            ZeroDateScanner::findZeroDateValues($adapter),
            fn(array $f) => $f['table'] === $table,
        ));
        // Only the NOT NULL column is left, flagged for manual attention
        expect(array_column($remainingValues, 'column'))->toBe(['locked']);
        $remainingDefaults = array_filter(
            ZeroDateScanner::findZeroDateDefaults($adapter),
            fn(array $f) => $f['table'] === $table,
        );
        expect($remainingDefaults)->toBeEmpty();
    } finally {
        $adapter->query("DROP TABLE IF EXISTS {$table}");
        $adapter->query('SET SESSION sql_mode = ' . $adapter->quote($modeBefore));
    }
});
