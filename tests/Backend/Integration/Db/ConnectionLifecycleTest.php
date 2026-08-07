<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('closes every open connection', function () {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_write');
    $adapter->fetchOne('SELECT 1');

    // Captured up front: asking the adapter for it again would reconnect.
    $dbal = $adapter->getConnection();
    expect($dbal->isConnected())->toBeTrue();

    $resource->closeConnections();

    expect($dbal->isConnected())->toBeFalse();
});

it('reconnects on demand after a close', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->fetchOne('SELECT 1');
    $adapter->closeConnection();

    expect((int) $adapter->fetchOne('SELECT 1'))->toBe(1);
});

it('abandons open setup state when the connection is closed', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    if (!$adapter instanceof Maho\Db\Adapter\Pdo\Mysql) {
        $this->markTestSkipped('Setup session state is MySQL-specific');
    }

    $adapter->startSetup();
    $adapter->closeConnection();

    expect((new ReflectionProperty($adapter, '_setupNesting'))->getValue($adapter))->toBe(0);

    // The relaxed SQL_MODE died with the connection, so this has to count as a
    // fresh setup and relax it again rather than nest inside the closed one.
    $adapter->startSetup();
    expect((string) $adapter->fetchOne('SELECT @@SESSION.sql_mode'))->not->toContain('NO_ZERO_DATE');
    $adapter->endSetup();
});

it('discards an open transaction when the connection is closed', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->beginTransaction();
    $adapter->closeConnection();

    expect($adapter->getTransactionLevel())->toBe(0);
    expect($adapter->isTransaction())->toBeFalse();

    $adapter->beginTransaction();
    expect($adapter->getTransactionLevel())->toBe(1);
    $adapter->rollBack();

    expect((int) $adapter->fetchOne('SELECT 1'))->toBe(1);
});

it('leaves no connection open behind Mage::reset()', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->fetchOne('SELECT 1');
    $dbal = $adapter->getConnection();

    Mage::reset();

    expect($dbal->isConnected())->toBeFalse();
});
