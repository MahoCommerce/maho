<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

use Maho\Db\Adapter\Pdo\Mysql;

uses(Tests\MahoBackendTestCase::class);

/**
 * Helper: create a fresh MySQL adapter instance using the same connection params from local.xml.
 * Bypasses the singleton cache so _initConnection() re-runs with the current developer mode setting.
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
 * Helper: whether the live server is MariaDB. MariaDB is exempt from the developer-mode
 * ONLY_FULL_GROUP_BY toggle because it lacks functional-dependency detection (MDEV-11588).
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

it('sets SQL_MODE to ONLY_FULL_GROUP_BY on MySQL connection init when developer mode is on', function () {
    Mage::setIsDeveloperMode(true);
    $adapter = createFreshMysqlAdapter();
    if (isMariaDbServer($adapter)) {
        $this->markTestSkipped('Genuine-MySQL-only test: MariaDB is exempt from strict mode.');
    }
    $sqlMode = fetchSqlMode($adapter);
    expect($sqlMode)->toContain('ONLY_FULL_GROUP_BY');
});

it('keeps SQL_MODE empty on MariaDB even when developer mode is on', function () {
    Mage::setIsDeveloperMode(true);
    $adapter = createFreshMysqlAdapter();
    if (!isMariaDbServer($adapter)) {
        $this->markTestSkipped('MariaDB-only test.');
    }
    expect(fetchSqlMode($adapter))->toBe('');
});

it('sets SQL_MODE to an empty string on MySQL connection init when developer mode is off', function () {
    Mage::setIsDeveloperMode(false);
    $adapter = createFreshMysqlAdapter();
    $sqlMode = fetchSqlMode($adapter);
    expect($sqlMode)->not->toContain('ONLY_FULL_GROUP_BY');
    expect($sqlMode)->toBe('');
});

it('raises an SQL error on a GROUP BY query missing aggregates when developer mode is on', function () {
    Mage::setIsDeveloperMode(true);
    $adapter = createFreshMysqlAdapter();
    if (isMariaDbServer($adapter)) {
        $this->markTestSkipped('Genuine-MySQL-only test: MariaDB is exempt from strict mode.');
    }
    // core_config_data has scope_id and path columns; selecting path without grouping or aggregating it
    // violates ONLY_FULL_GROUP_BY
    expect(fn() => $adapter->fetchAll(
        'SELECT scope, path FROM core_config_data GROUP BY scope',
    ))->toThrow(\Exception::class);
});

it('does not raise an SQL error on the same non-strict GROUP BY query when developer mode is off', function () {
    Mage::setIsDeveloperMode(false);
    $adapter = createFreshMysqlAdapter();
    $results = $adapter->fetchAll(
        'SELECT scope, path FROM core_config_data GROUP BY scope',
    );
    expect($results)->toBeArray();
});

it('keeps the SET time_zone statement working alongside the SQL_MODE toggle', function () {
    foreach ([true, false] as $devMode) {
        Mage::setIsDeveloperMode($devMode);
        $adapter = createFreshMysqlAdapter();
        $tz = $adapter->fetchOne('SELECT @@time_zone');
        expect($tz)->toBe('+00:00');
    }
});

it('preserves ONLY_FULL_GROUP_BY across startSetup and endSetup when developer mode is on', function () {
    Mage::setIsDeveloperMode(true);
    $adapter = createFreshMysqlAdapter();
    if (isMariaDbServer($adapter)) {
        $this->markTestSkipped('Genuine-MySQL-only test: MariaDB is exempt from strict mode.');
    }
    $adapter->startSetup();
    $adapter->endSetup();
    $sqlMode = fetchSqlMode($adapter);
    expect($sqlMode)->toContain('ONLY_FULL_GROUP_BY');
});

it('preserves the empty SQL_MODE across startSetup and endSetup when developer mode is off', function () {
    Mage::setIsDeveloperMode(false);
    $adapter = createFreshMysqlAdapter();
    $adapter->startSetup();
    $adapter->endSetup();
    $sqlMode = fetchSqlMode($adapter);
    expect($sqlMode)->toBe('');
});

it('preserves the live SQL_MODE in insertForce so the bulk-import path still works in dev mode', function () {
    Mage::setIsDeveloperMode(true);
    $adapter = createFreshMysqlAdapter();
    if (isMariaDbServer($adapter)) {
        $this->markTestSkipped('Genuine-MySQL-only test: MariaDB is exempt from strict mode.');
    }

    // SQL_MODE before
    $modeBefore = fetchSqlMode($adapter);
    expect($modeBefore)->toContain('ONLY_FULL_GROUP_BY');

    // Create a temp table and call insertForce
    $tempTable = 'test_insertforce_' . uniqid();
    $table = $adapter->newTable($tempTable)
        ->addColumn('id', \Maho\Db\Ddl\Table::TYPE_INTEGER, null, [
            'identity' => true,
            'nullable' => false,
            'primary' => true,
        ], 'ID')
        ->addColumn('val', \Maho\Db\Ddl\Table::TYPE_VARCHAR, 50, [], 'Val')
        ->setOption('type', 'TEMPORARY');
    $adapter->createTable($table);

    $adapter->insertForce($tempTable, ['id' => 0, 'val' => 'test']);

    // SQL_MODE should be restored to ONLY_FULL_GROUP_BY after insertForce
    $modeAfter = fetchSqlMode($adapter);
    expect($modeAfter)->toContain('ONLY_FULL_GROUP_BY');
});
