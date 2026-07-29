<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Db\Adapter\Pdo\Mysql;
use Maho\Db\Schema\Applier;

uses(Tests\MahoBackendTestCase::class);

/**
 * The CI MySQL jobs run with GTID enforced, but only paths a test exercises
 * would catch a regression, so assert the invariant against the whole schema.
 */
beforeEach(function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    if (!($adapter instanceof Mysql)) {
        $this->markTestSkipped('MySQL-only test: other backends have no storage-engine concept.');
    }
});

it('has no table left on a non-transactional storage engine', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');

    expect(Applier::legacyEngineTables($adapter->getConnection()))->toBe([]);
});

it('converts a table that a migrated store would still have on MyISAM', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = 'test_engine_' . uniqid();

    $adapter->query(sprintf('CREATE TABLE %s (id INT NOT NULL) ENGINE=MyISAM', $adapter->quoteIdentifier($table)));

    try {
        expect(Applier::legacyEngineTables($adapter->getConnection()))->toHaveKey($table, 'MyISAM');

        foreach (Applier::plan($adapter->getConnection(), new Doctrine\DBAL\Schema\Schema()) as $statement) {
            $adapter->query($statement);
        }

        expect(Applier::legacyEngineTables($adapter->getConnection()))->not->toHaveKey($table);
    } finally {
        $adapter->dropTable($table);
    }
});

it('scopes the conversion to the prefix when the database is shared', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $table = 'otherapp_engine_' . uniqid();

    $adapter->query(sprintf('CREATE TABLE %s (id INT NOT NULL) ENGINE=MyISAM', $adapter->quoteIdentifier($table)));

    try {
        expect(Applier::legacyEngineTables($adapter->getConnection(), 'maho_'))->not->toHaveKey($table);
    } finally {
        $adapter->dropTable($table);
    }
});
