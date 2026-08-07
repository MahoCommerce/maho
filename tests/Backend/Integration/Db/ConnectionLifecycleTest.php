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

it('leaves no connection open behind Mage::reset()', function () {
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    $adapter->fetchOne('SELECT 1');
    $dbal = $adapter->getConnection();

    Mage::reset();

    expect($dbal->isConnected())->toBeFalse();
});
