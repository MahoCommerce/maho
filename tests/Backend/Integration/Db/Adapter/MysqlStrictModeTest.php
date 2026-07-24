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

it('keeps the strict baseline active during setup, only adding NO_AUTO_VALUE_ON_ZERO', function () {
    $adapter = createFreshMysqlAdapter();
    $adapter->startSetup();
    // Setup scripts must run under the same strict mode as production, so a bad
    // write in an install/upgrade script is caught instead of silently truncated.
    $modeDuring = fetchSqlMode($adapter);
    $adapter->endSetup();

    expect($modeDuring)->toContain('STRICT_TRANS_TABLES');
    expect($modeDuring)->toContain('NO_ZERO_DATE');
    expect($modeDuring)->toContain('NO_AUTO_VALUE_ON_ZERO');
});

it('preserves the live SQL_MODE in insertForce so the bulk-import path still works', function () {
    $adapter = createFreshMysqlAdapter();
    $modeBefore = fetchSqlMode($adapter);
    expect($modeBefore)->toContain('STRICT_TRANS_TABLES');

    $tempTable = 'test_insertforce_' . uniqid();
    $table = $adapter->newTable($tempTable)
        ->addColumn('id', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity' => true,
            'nullable' => false,
            'primary' => true,
        ], 'ID')
        ->addColumn('val', \Maho\Db\Ddl\Table::TYPE_VARCHAR, 50, [], 'Val');
    $adapter->createTable($table);

    $adapter->insertForce($tempTable, ['id' => 0, 'val' => 'test']);

    // SQL_MODE should be restored to the strict baseline after insertForce
    expect(fetchSqlMode($adapter))->toBe($modeBefore);

    $adapter->dropTable($tempTable);
});
