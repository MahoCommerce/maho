<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Mysql;

uses(Tests\MahoBackendTestCase::class);

/**
 * Helper: create a fresh MySQL adapter instance using the same connection params from local.xml.
 * Bypasses the singleton cache so _initConnection() re-runs.
 */
function createFreshMysqlAdapter(): Mysql
{
    $resource = Mage::getSingleton('core/resource');
    $config = $resource->getConnection('core_write')->getConfig();

    return new Mysql($config);
}

/**
 * Helper: fetch the current SQL_MODE from a live adapter connection.
 */
function fetchSqlMode(Mysql $adapter): string
{
    $result = $adapter->fetchOne('SELECT @@SQL_MODE');
    return (string) $result;
}

/**
 * Helper: whether the live server is MariaDB. MariaDB is exempt from ONLY_FULL_GROUP_BY
 * because it lacks functional-dependency detection (MDEV-11588), but it still gets the
 * rest of the strict baseline.
 */
function isMariaDbServer(Mysql $adapter): bool
{
    return str_contains(strtolower((string) $adapter->fetchOne('SELECT VERSION()')), 'mariadb');
}

/**
 * Skip this test file if the live DB is not MySQL.
 */
beforeEach(function () {
    $liveAdapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    if (!($liveAdapter instanceof Mysql)) {
        $this->markTestSkipped('MySQL-only test: current DB adapter is not MySQL.');
    }
});

it('pins the strict baseline SQL_MODE on connection init', function () {
    $adapter = createFreshMysqlAdapter();
    $sqlMode = fetchSqlMode($adapter);
    expect($sqlMode)->toContain('STRICT_TRANS_TABLES');
    expect($sqlMode)->toContain('NO_ZERO_DATE');
    expect($sqlMode)->toContain('NO_ZERO_IN_DATE');
    expect($sqlMode)->toContain('ERROR_FOR_DIVISION_BY_ZERO');
    expect($sqlMode)->toContain('NO_ENGINE_SUBSTITUTION');
    // Added on top of the MySQL factory default so an explicit 0 is stored in an
    // identity column (matching PostgreSQL/SQLite) instead of triggering auto_increment.
    expect($sqlMode)->toContain('NO_AUTO_VALUE_ON_ZERO');
});

it('includes ONLY_FULL_GROUP_BY in the baseline on genuine MySQL', function () {
    $adapter = createFreshMysqlAdapter();
    if (isMariaDbServer($adapter)) {
        $this->markTestSkipped('Genuine-MySQL-only test: MariaDB is exempt from ONLY_FULL_GROUP_BY.');
    }
    expect(fetchSqlMode($adapter))->toContain('ONLY_FULL_GROUP_BY');
});

it('exempts MariaDB from ONLY_FULL_GROUP_BY but keeps the rest of the strict baseline', function () {
    $adapter = createFreshMysqlAdapter();
    if (!isMariaDbServer($adapter)) {
        $this->markTestSkipped('MariaDB-only test.');
    }
    $sqlMode = fetchSqlMode($adapter);
    expect($sqlMode)->not->toContain('ONLY_FULL_GROUP_BY');
    expect($sqlMode)->toContain('STRICT_TRANS_TABLES');
});

it('honors the sql_mode escape hatch from the connection config', function () {
    $resource = Mage::getSingleton('core/resource');
    $config = $resource->getConnection('core_write')->getConfig();
    $config['sql_mode'] = '';
    $adapter = new Mysql($config);
    expect(fetchSqlMode($adapter))->toBe('');

    $config['sql_mode'] = 'STRICT_TRANS_TABLES';
    $adapter = new Mysql($config);
    expect(fetchSqlMode($adapter))->toBe('STRICT_TRANS_TABLES');
});

it('pins the same SQL_MODE baseline regardless of developer mode', function () {
    $modes = [];
    foreach ([true, false] as $devMode) {
        Mage::setIsDeveloperMode($devMode);
        $adapter = createFreshMysqlAdapter();
        $modes[] = fetchSqlMode($adapter);
    }
    expect($modes[0])->toBe($modes[1]);
});

it('raises an SQL error on a GROUP BY query missing aggregates even with developer mode off', function () {
    Mage::setIsDeveloperMode(false);
    $adapter = createFreshMysqlAdapter();
    if (isMariaDbServer($adapter)) {
        $this->markTestSkipped('Genuine-MySQL-only test: MariaDB is exempt from ONLY_FULL_GROUP_BY.');
    }
    // core_config_data has scope and path columns; selecting path without grouping or
    // aggregating it violates ONLY_FULL_GROUP_BY
    expect(fn() => $adapter->fetchAll(
        'SELECT scope, path FROM core_config_data GROUP BY scope',
    ))->toThrow(\Exception::class);
});

it('rejects writes that would be silently truncated under the legacy relaxed mode', function () {
    $adapter = createFreshMysqlAdapter();
    $tempTable = 'test_strict_' . uniqid();
    $table = $adapter->newTable($tempTable)
        ->addColumn('id', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity' => true,
            'nullable' => false,
            'primary' => true,
        ], 'ID')
        ->addColumn('val', \Maho\Db\Ddl\Table::TYPE_VARCHAR, 5, [], 'Val');
    $adapter->createTable($table);

    expect(fn() => $adapter->insert($tempTable, ['val' => 'longer than five']))
        ->toThrow(\Exception::class);

    $adapter->dropTable($tempTable);
});

it('keeps the SET time_zone statement working alongside the SQL_MODE baseline', function () {
    $adapter = createFreshMysqlAdapter();
    $tz = $adapter->fetchOne('SELECT @@time_zone');
    expect($tz)->toBe('+00:00');
});

it('preserves the strict baseline across startSetup and endSetup', function () {
    $adapter = createFreshMysqlAdapter();
    $modeBefore = fetchSqlMode($adapter);
    $adapter->startSetup();
    $adapter->endSetup();
    expect(fetchSqlMode($adapter))->toBe($modeBefore);
});

it('keeps the strict baseline active during setup without touching SQL_MODE', function () {
    $adapter = createFreshMysqlAdapter();
    $modeBefore = fetchSqlMode($adapter);
    $adapter->startSetup();
    // Setup scripts must run under the same strict mode as production, so a bad
    // write in an install/upgrade script is caught instead of silently truncated.
    // The baseline already carries NO_AUTO_VALUE_ON_ZERO, so setup leaves SQL_MODE alone.
    $modeDuring = fetchSqlMode($adapter);
    $adapter->endSetup();

    expect($modeDuring)->toBe($modeBefore);
    expect($modeDuring)->toContain('STRICT_TRANS_TABLES');
    expect($modeDuring)->toContain('NO_ZERO_DATE');
    expect($modeDuring)->toContain('NO_AUTO_VALUE_ON_ZERO');
});

it('stores an explicit 0 in an identity column via both insert and insertForce', function () {
    $adapter = createFreshMysqlAdapter();

    $tempTable = 'test_zero_identity_' . uniqid();
    $table = $adapter->newTable($tempTable)
        ->addColumn('id', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity' => true,
            'nullable' => false,
            'primary' => true,
        ], 'ID')
        ->addColumn('val', \Maho\Db\Ddl\Table::TYPE_VARCHAR, 50, [], 'Val');
    $adapter->createTable($table);

    try {
        // Permanent NO_AUTO_VALUE_ON_ZERO: an explicit 0 is stored verbatim rather
        // than triggering auto_increment, identical to the PostgreSQL/SQLite adapters.
        $adapter->insert($tempTable, ['id' => 0, 'val' => 'via-insert']);
        expect((int) $adapter->fetchOne("SELECT id FROM {$tempTable} WHERE val = 'via-insert'"))->toBe(0);

        // insertForce is now a thin wrapper over insert and behaves identically.
        $adapter->insertForce($tempTable, ['id' => 5, 'val' => 'via-force']);
        expect((int) $adapter->fetchOne("SELECT id FROM {$tempTable} WHERE val = 'via-force'"))->toBe(5);

        // A new row still auto-generates when the PK is omitted.
        $adapter->insert($tempTable, ['val' => 'auto']);
        expect((int) $adapter->fetchOne("SELECT id FROM {$tempTable} WHERE val = 'auto'"))->toBeGreaterThan(0);

        // The baseline is unchanged by the inserts.
        expect(fetchSqlMode($adapter))->toContain('NO_AUTO_VALUE_ON_ZERO');
    } finally {
        $adapter->dropTable($tempTable);
    }
});
