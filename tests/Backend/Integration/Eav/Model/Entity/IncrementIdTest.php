<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The increment id allocation must keep a transaction of its own. SQLite has one
 * writer for the whole database, so it never takes a second connection.
 */
function incrementIdAdapter(): Maho\Db\Adapter\AdapterInterface
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
    if ($adapter instanceof Maho\Db\Adapter\Pdo\Sqlite) {
        test()->markTestSkipped('SQLite serialises writers already');
    }
    return $adapter;
}

function storedIncrementLastId(int $entityTypeId, int $storeId): ?string
{
    $resource = Mage::getSingleton('core/resource');
    $fresh = $resource->createUnsharedConnection();
    try {
        $value = $fresh->fetchOne(
            $fresh->select()
                ->from($resource->getTableName('eav/entity_store'), ['increment_last_id'])
                ->where('entity_type_id = ?', $entityTypeId)
                ->where('store_id = ?', $storeId),
        );
    } finally {
        $fresh->closeConnection();
    }
    return $value === false ? null : (string) $value;
}

it('commits the allocation even when the caller rolls back', function () {
    $write = incrementIdAdapter();
    $entityType = Mage::getModel('eav/entity_type')->loadByCode('order');
    $storeId = 1;

    $write->beginTransaction();
    try {
        // Establish the read view, as an order save does before it allocates.
        $write->fetchOne('SELECT COUNT(*) FROM ' . Mage::getSingleton('core/resource')->getTableName('eav/entity_store'));

        $incrementId = $entityType->fetchNewIncrementId($storeId);
        expect($incrementId)->toBeString()->not->toBeEmpty();
        expect($write->getTransactionLevel())->toBe(1);
    } finally {
        $write->rollBack();
    }

    // The number survives the rollback, so the allocation had its own transaction.
    expect(storedIncrementLastId((int) $entityType->getId(), $storeId))->toBe($incrementId);
});

it('never hands out the same number twice', function () {
    $entityType = Mage::getModel('eav/entity_type')->loadByCode('order');
    $seen = [];
    for ($i = 0; $i < 5; $i++) {
        $seen[] = $entityType->fetchNewIncrementId(1);
    }
    expect($seen)->toHaveCount(5);
    expect(array_unique($seen))->toHaveCount(5);
});

it('uses the shared connection when the caller is not in a transaction', function () {
    $write = incrementIdAdapter();
    expect($write->getTransactionLevel())->toBe(0);

    $used = Mage::getSingleton('core/resource')->runOutsideTransaction(fn($connection) => $connection);
    expect($used)->toBe($write);
});

it('takes a connection of its own when the caller is in a transaction', function () {
    $write = incrementIdAdapter();

    $write->beginTransaction();
    try {
        $used = Mage::getSingleton('core/resource')->runOutsideTransaction(function ($connection) {
            expect($connection->getTransactionLevel())->toBe(0);
            return $connection;
        });
        expect($used)->not->toBe($write);
    } finally {
        $write->rollBack();
    }
});
