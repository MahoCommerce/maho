<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Mysql;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    if (!($adapter instanceof Mysql)) {
        $this->markTestSkipped('MySQL-only test: zero dates only exist on MySQL/MariaDB.');
    }
});

it('runs DDL on a legacy zero-date table between startSetup() and endSetup()', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = 'setup_sql_mode_probe';
    $modeBefore = (string) $adapter->fetchOne('SELECT @@SESSION.sql_mode');

    try {
        // The shape a store migrated from Magento/OpenMage actually has: a zero-date
        // DEFAULT on a NOT NULL column, holding zero-date rows. Seeded under the
        // relaxed mode, the way it got there in the first place.
        $adapter->query("SET SESSION sql_mode=''");
        $adapter->query(
            "CREATE TABLE {$table} ("
            . ' id INT PRIMARY KEY,'
            . ' note VARCHAR(10),'
            . ' idx_col INT,'
            . " updated_at TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00',"
            . ' KEY k_probe (idx_col)'
            . ')',
        );
        $adapter->query("INSERT INTO {$table} VALUES (1, 'x', 5, '0000-00-00 00:00:00')");
        $adapter->query('SET SESSION sql_mode = ' . $adapter->quote($modeBefore));

        // Under the strict baseline, MySQL refuses every ALTER on the table, even
        // one naming a different column entirely (error 1067).
        expect(fn() => $adapter->query("ALTER TABLE {$table} DROP INDEX k_probe"))
            ->toThrow(Exception::class);

        $adapter->startSetup();
        try {
            $adapter->query("ALTER TABLE {$table} DROP INDEX k_probe");
            // The TIMESTAMP→DATETIME conversion of upgrade-1.6.0.10-2.0.0: rebuilding
            // rows that hold zero dates is the other half of the problem (error 1292).
            $adapter->query("ALTER TABLE {$table} MODIFY COLUMN updated_at DATETIME NOT NULL");
        } finally {
            $adapter->endSetup();
        }

        expect((string) $adapter->fetchOne(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, 'updated_at'],
        ))->toBe('datetime');

        // The relaxation is scoped to setup: the strict baseline is back afterwards.
        expect((string) $adapter->fetchOne('SELECT @@SESSION.sql_mode'))->toBe($modeBefore);
    } finally {
        $adapter->query("SET SESSION sql_mode=''");
        $adapter->query("DROP TABLE IF EXISTS {$table}");
        $adapter->query('SET SESSION sql_mode = ' . $adapter->quote($modeBefore));
    }
});

it('restores SQL_MODE only once nested setup blocks have all closed', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $modeBefore = (string) $adapter->fetchOne('SELECT @@SESSION.sql_mode');
    expect($modeBefore)->toContain('NO_ZERO_DATE');

    $adapter->startSetup();
    $adapter->startSetup();
    expect((string) $adapter->fetchOne('SELECT @@SESSION.sql_mode'))->not->toContain('NO_ZERO_DATE');

    $adapter->endSetup();
    expect((string) $adapter->fetchOne('SELECT @@SESSION.sql_mode'))->not->toContain('NO_ZERO_DATE');

    $adapter->endSetup();
    expect((string) $adapter->fetchOne('SELECT @@SESSION.sql_mode'))->toBe($modeBefore);

    // Unbalanced: an endSetup() with no startSetup() must not restore anything
    $adapter->endSetup();
    expect((string) $adapter->fetchOne('SELECT @@SESSION.sql_mode'))->toBe($modeBefore);
});
