<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Queue\Pool;
use Maho\Queue\PoolRegistry;
use Maho\Queue\QueueManager;
use Maho\Queue\Transport\DbTransport;

uses(Tests\MahoBackendTestCase::class);

/**
 * @return list<string> "pool:index" for each worker the watchdog would start
 */
function pendingWorkers(): array
{
    return array_map(
        fn(array $worker) => $worker[0]->name . ':' . $worker[1],
        Mage::getModel('queue/cron')->workersToSpawn(),
    );
}

/**
 * @param callable():void $body
 */
function withAllPoolLocks(callable $body): void
{
    $lock = Mage::getSingleton('core/lock');
    $held = [];
    foreach (PoolRegistry::all() as $pool) {
        for ($index = 0; $index < $pool->count; $index++) {
            expect($lock->acquire($pool->lockName($index), machineLocal: true))->toBeTrue();
            $held[] = $pool->lockName($index);
        }
    }

    try {
        $body();
    } finally {
        foreach ($held as $name) {
            $lock->release($name);
        }
    }
}

beforeEach(function () {
    QueueManager::reset();
    clearQueueTable();
});

afterEach(function () {
    clearQueueTable();
    QueueManager::reset();
});

it('does not spawn a second worker while the pool lock is held', function () {
    QueueManager::dispatch(makeEmailMessage());

    withAllPoolLocks(function () {
        expect(pendingWorkers())->toBe([]);

        Mage::getModel('queue/cron')->process();
        $rows = fetchQueueRows();
        expect($rows)->toHaveCount(1);
        expect($rows[0]['status'])->toBe(DbTransport::STATUS_PENDING);
    });
});

it('keeps the resident tier running even with nothing queued', function () {
    expect(pendingWorkers())->toBe(['fast:0']);
});

it('starts the on-demand tier only once its queues have work due', function () {
    QueueManager::dispatch(
        makeEmailMessage(),
        delaySeconds: 7 * 86400,
        queue: 'newsletter',
    );
    expect(pendingWorkers())->toBe(['fast:0']);

    QueueManager::dispatch(makeEmailMessage('due now'), queue: 'newsletter');
    expect(pendingWorkers())->toBe(['fast:0', 'slow:0']);
});

it('stands aside for a hand-run exclusive worker', function () {
    QueueManager::dispatch(makeEmailMessage());

    $lock = Mage::getSingleton('core/lock');
    expect($lock->acquire(Pool::LOCK_PREFIX, machineLocal: true))->toBeTrue();
    try {
        expect(pendingWorkers())->toBe([]);
    } finally {
        $lock->release(Pool::LOCK_PREFIX);
    }
});

it('counts the on-demand workers already alive against the due budget', function () {
    $node = Mage::getConfig()->getNode('global/queue');
    $node->extend(new Maho\Simplexml\Element('<queue><pools><slow><count>3</count></slow></pools></queue>'), true);
    QueueManager::reset();

    $lock = Mage::getSingleton('core/lock');
    $slow = PoolRegistry::get('slow');
    expect($slow?->count)->toBe(3);
    $held = $slow->lockName(0);
    expect($lock->acquire($held, machineLocal: true))->toBeTrue();

    try {
        QueueManager::dispatch(makeEmailMessage('due now'), queue: 'newsletter');

        // One message, one worker already consuming it: a second process would
        // boot only to idle straight back out.
        expect(pendingWorkers())->toBe(['fast:0']);
    } finally {
        $lock->release($held);
        unset($node->pools->slow->count);
        QueueManager::reset();
    }
});

it('removes old failed messages during cleanup', function () {
    $now = Mage_Core_Model_Locale::nowUtc();
    queueAdapter()->insert(QueueManager::tableName(), [
        'queue' => 'default',
        'status' => DbTransport::STATUS_FAILED,
        'message_class' => Mage_Core_Model_Email_SendMessage::class,
        'body' => serialize(makeEmailMessage()),
        'available_at' => $now,
        'processed_at' => gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT, time() - 40 * 86400),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    queueAdapter()->insert(QueueManager::tableName(), [
        'queue' => 'default',
        'status' => DbTransport::STATUS_FAILED,
        'message_class' => Mage_Core_Model_Email_SendMessage::class,
        'body' => serialize(makeEmailMessage()),
        'available_at' => $now,
        'processed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    Mage::getModel('queue/cron')->cleanup();

    $rows = fetchQueueRows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]['processed_at'])->toBe($now);
});
