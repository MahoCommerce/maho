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

it('roundtrips send, get, and ack', function () {
    QueueManager::dispatch(makeEmailMessage());

    $rows = fetchQueueRows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]['status'])->toBe(DbTransport::STATUS_PENDING);
    expect($rows[0]['queue'])->toBe('default');
    expect($rows[0]['message_class'])->toBe(Mage_Core_Model_Email_SendMessage::class);

    $transport = QueueManager::dbTransport();
    $envelopes = [...$transport->get()];
    expect($envelopes)->toHaveCount(1);
    $message = $envelopes[0]->getMessage();
    expect($message)->toBeInstanceOf(Mage_Core_Model_Email_SendMessage::class);
    expect($message->subject)->toBe('Test subject');

    $rows = fetchQueueRows();
    expect($rows[0]['status'])->toBe(DbTransport::STATUS_PROCESSING);

    $transport->ack($envelopes[0]);
    expect(fetchQueueRows())->toHaveCount(0);
});

it('claims a message atomically so a second consumer gets nothing', function () {
    QueueManager::dispatch(makeEmailMessage());

    $transport = QueueManager::dbTransport();
    expect([...$transport->get()])->toHaveCount(1);
    expect([...$transport->get()])->toHaveCount(0);
});

it('honors the dispatch delay via available_at', function () {
    QueueManager::dispatch(makeEmailMessage(), delaySeconds: 3600);

    $rows = fetchQueueRows();
    expect($rows[0]['available_at'])->toBeGreaterThan(Mage_Core_Model_Locale::nowUtc());
    expect([...QueueManager::dbTransport()->get()])->toHaveCount(0);
});

it('routes messages to named queues and consumes them in isolation', function () {
    QueueManager::dispatch(makeEmailMessage('email one'), queue: 'email');
    QueueManager::dispatch(makeEmailMessage('other one'), queue: 'other');

    $transport = QueueManager::dbTransport();
    $envelopes = [...$transport->getFromQueues(['email'])];
    expect($envelopes)->toHaveCount(1);
    expect($envelopes[0]->getMessage()->subject)->toBe('email one');
    expect([...$transport->getFromQueues(['email'])])->toHaveCount(0);
    expect([...$transport->getFromQueues(['other'])])->toHaveCount(1);
});

it('skips dispatch while a pending message with the same dedupe key exists', function () {
    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'abc');
    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'abc');
    expect(fetchQueueRows())->toHaveCount(1);

    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'other');
    expect(fetchQueueRows())->toHaveCount(2);
});

it('re-queues stale processing claims after redeliver_after', function () {
    QueueManager::dispatch(makeEmailMessage());
    $transport = QueueManager::dbTransport();
    expect([...$transport->get()])->toHaveCount(1);

    queueAdapter()->update(QueueManager::tableName(), [
        'claimed_at' => gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT, time() - 7200),
    ]);

    $envelopes = [...$transport->get()];
    expect($envelopes)->toHaveCount(1);
});

it('fails a claimed row whose message class has no registered handler', function () {
    $now = Mage_Core_Model_Locale::nowUtc();
    queueAdapter()->insert(QueueManager::tableName(), [
        'queue' => 'default',
        'status' => DbTransport::STATUS_PENDING,
        'message_class' => 'Nonexistent_Evil_Class',
        'body' => serialize(new stdClass()),
        'available_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    expect([...QueueManager::dbTransport()->get()])->toHaveCount(0);

    $rows = fetchQueueRows();
    expect($rows[0]['status'])->toBe(DbTransport::STATUS_FAILED);
    expect($rows[0]['error_message'])->toContain('Nonexistent_Evil_Class');
});

it('counts pending messages and finds stored ones', function () {
    QueueManager::dispatch(makeEmailMessage());
    QueueManager::dispatch(makeEmailMessage('second'));

    $transport = QueueManager::dbTransport();
    expect($transport->getMessageCount())->toBe(2);

    $rows = fetchQueueRows();
    $envelope = $transport->find((int) $rows[0]['message_id']);
    expect($envelope)->not->toBeNull();
    expect($envelope->getMessage()->subject)->toBe('Test subject');
    expect($transport->find(999999))->toBeNull();

    expect([...$transport->all()])->toHaveCount(2);
});
