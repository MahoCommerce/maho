<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Queue\QueueManager;
use Maho\Queue\Transport\DbTransport;

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    QueueManager::reset();
    clearQueueTable();
});

afterEach(function () {
    clearQueueTable();
    QueueManager::reset();
});

it('does not spawn a second worker while the lock is held', function () {
    QueueManager::dispatch(makeEmailMessage());

    $lock = Mage::getSingleton('core/lock');
    expect($lock->acquire(Maho_Queue_Model_Cron::WORKER_LOCK, machineLocal: true))->toBeTrue();
    try {
        Mage::getModel('queue/cron')->process();
        $rows = fetchQueueRows();
        expect($rows)->toHaveCount(1);
        expect($rows[0]['status'])->toBe(DbTransport::STATUS_PENDING);
    } finally {
        $lock->release(Maho_Queue_Model_Cron::WORKER_LOCK);
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
