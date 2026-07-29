<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use MahoCLI\Helper\TableBloatScanner;
use Maho\Db\Adapter\Pdo\Pgsql;
use Maho\Db\Adapter\Pdo\Sqlite;

uses(Tests\MahoBackendTestCase::class);

/**
 * Each backend measures bloat differently and reclaims it with different syntax,
 * so the value of these tests is that they run unchanged on all three: a query or
 * statement that only parses on MySQL fails here on PostgreSQL and SQLite.
 *
 * Deliberately absent: a test that creates bloat and asserts the scan finds it.
 * MySQL caches information_schema statistics for a day by default and PostgreSQL
 * updates its tuple counters asynchronously, so that assertion would be flaky
 * everywhere except SQLite.
 */
function bloatProbeTable(\Maho\Db\Adapter\AdapterInterface $adapter): string
{
    $table = 'bloat_scanner_probe';
    $adapter->query(sprintf(
        'CREATE TABLE %s (id INTEGER NOT NULL, payload VARCHAR(255) NOT NULL)',
        $adapter->quoteIdentifier($table),
    ));

    for ($i = 1; $i <= 200; $i++) {
        $adapter->insert($table, ['id' => $i, 'payload' => str_repeat('x', 200)]);
    }
    $adapter->delete($table, 'id > 10');

    return $table;
}

it('runs its bloat introspection against this backend', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');

    $findings = TableBloatScanner::scan($adapter);

    foreach ($findings as $finding) {
        expect($finding)->toHaveKeys(['table', 'total', 'reclaimable', 'ratio', 'detail']);
        expect($finding['total'])->toBeGreaterThanOrEqual(TableBloatScanner::MIN_TOTAL_BYTES);
        expect($finding['ratio'])->toBeGreaterThanOrEqual(TableBloatScanner::MIN_RECLAIMABLE_RATIO);
    }
});

it('honors the thresholds it is given', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');

    // A ratio no table can reach: whatever the backend reports, nothing survives.
    expect(TableBloatScanner::scan($adapter, '', 0, 1.01))->toBe([]);
});

it('scopes the scan to the table prefix when the database is shared', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    if ($adapter instanceof Sqlite) {
        $this->markTestSkipped('SQLite reports the database file, which no table prefix can narrow.');
    }

    expect(TableBloatScanner::scan($adapter, 'someotherapp_', 0, 0.0))->toBe([]);
});

it('emits a rebuild statement this backend accepts and leaves the rows intact', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = bloatProbeTable($adapter);

    try {
        $statements = TableBloatScanner::optimizeStatements($adapter, [$table]);
        expect($statements)->toHaveCount(1);

        $expected = match (true) {
            $adapter instanceof Sqlite => 'VACUUM',
            $adapter instanceof Pgsql => 'VACUUM (FULL, ANALYZE) ' . $adapter->quoteIdentifier($table),
            default => 'OPTIMIZE TABLE ' . $adapter->quoteIdentifier($table),
        };
        expect($statements[0])->toBe($expected);

        TableBloatScanner::runStatement($adapter, $statements[0]);

        // MySQL returns a status result set from OPTIMIZE TABLE that has to be
        // consumed before the connection accepts anything else; this read fails
        // with "commands out of sync" if runStatement() stops doing that.
        expect((int) $adapter->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $adapter->quoteIdentifier($table))))->toBe(10);
    } finally {
        $adapter->dropTable($table);
    }
});

it('asks for no statement when there is nothing to optimize', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');

    expect(TableBloatScanner::optimizeStatements($adapter, []))->toBe([]);
});

it('measures a table footprint on this backend', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = bloatProbeTable($adapter);

    try {
        expect(TableBloatScanner::totalSize($adapter, $table))->toBeInt()->toBeGreaterThan(0);
    } finally {
        $adapter->dropTable($table);
    }
});

it('reports no footprint for a table the server does not have', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    if ($adapter instanceof Sqlite) {
        $this->markTestSkipped('SQLite has no per-table storage accounting: totalSize() reports the database file.');
    }

    expect(TableBloatScanner::totalSize($adapter, 'no_such_table_' . uniqid()))->toBeNull();
});
