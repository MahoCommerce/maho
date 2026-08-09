<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Queue\QueueManager;
use Maho\Queue\Transport\DbTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

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

it('skips dispatch while a pending or processing message with the same dedupe key exists', function () {
    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'abc');
    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'abc');
    expect(fetchQueueRows())->toHaveCount(1);

    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'other');
    expect(fetchQueueRows())->toHaveCount(2);

    $transport = QueueManager::dbTransport();
    $envelopes = [...$transport->getFromQueues(['default'])];
    expect($envelopes)->toHaveCount(1);
    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'abc');
    expect(fetchQueueRows())->toHaveCount(2);

    $transport->ack($envelopes[0]);
    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'abc');
    expect(fetchQueueRows())->toHaveCount(2);
});

it('lets a dedupe key be dispatched again once its claim is abandoned', function () {
    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'abc');
    $transport = QueueManager::dbTransport();
    expect([...$transport->getFromQueues(['default'])])->toHaveCount(1);

    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'abc');
    expect(fetchQueueRows())->toHaveCount(1);

    // A claim nobody will finish must stop suppressing later sends, or every
    // future dispatch of this key is silently dropped instead of one being parked.
    queueAdapter()->update(QueueManager::tableName(), [
        'claimed_at' => gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT, time() - 7200),
    ]);
    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'abc');
    expect(fetchQueueRows())->toHaveCount(2);
});

it('inserts a failure-transport send as a failed row instead of updating by the foreign message id', function () {
    $envelope = (new Envelope(makeEmailMessage()))->with(
        new TransportMessageIdStamp('1712345678901-0'),
        new RedeliveryStamp(0),
        new SentToFailureTransportStamp('origin'),
        ErrorDetailsStamp::create(new RuntimeException('handler blew up')),
    );

    QueueManager::dbTransport()->send($envelope);

    $rows = fetchQueueRows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]['status'])->toBe(DbTransport::STATUS_FAILED);
    expect($rows[0]['error_message'])->toContain('handler blew up');
});

it('retries a failed row and an abandoned claim, but not a pending or freshly claimed one', function () {
    QueueManager::dispatch(makeEmailMessage());
    $transport = QueueManager::dbTransport();
    $id = (int) fetchQueueRows()[0]['message_id'];

    // Pending needs no help.
    expect(QueueManager::retryStoredMessage($id))->toBeFalse();

    // A live worker is still inside this one: re-queueing it runs the handler twice.
    $envelopes = [...$transport->get()];
    expect(fetchQueueRows()[0]['status'])->toBe(DbTransport::STATUS_PROCESSING);
    expect(QueueManager::retryStoredMessage($id))->toBeFalse();

    // Old enough to belong to a dead worker: nothing else requeues it, so the grid must.
    queueAdapter()->update(QueueManager::tableName(), [
        'claimed_at' => gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT, time() - 7200),
    ]);
    expect(QueueManager::retryStoredMessage($id))->toBeTrue();
    expect(fetchQueueRows()[0]['status'])->toBe(DbTransport::STATUS_PENDING);

    [...$transport->get()];
    $transport->reject($envelopes[0]);
    expect(fetchQueueRows()[0]['status'])->toBe(DbTransport::STATUS_FAILED);
    expect(QueueManager::retryStoredMessage($id))->toBeTrue();
    expect(fetchQueueRows()[0]['status'])->toBe(DbTransport::STATUS_PENDING);
});

it('never hands out a claim again on its own, however old it is', function () {
    QueueManager::dispatch(makeEmailMessage());
    $transport = QueueManager::dbTransport();
    expect([...$transport->get()])->toHaveCount(1);

    queueAdapter()->update(QueueManager::tableName(), [
        'claimed_at' => gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT, time() - 7200),
    ]);

    // A claim is parked for an operator, never redelivered on a timer: running
    // a handler a second time is not something a clock gets to decide.
    expect([...$transport->get()])->toHaveCount(0);
    expect(fetchQueueRows()[0]['status'])->toBe(DbTransport::STATUS_PROCESSING);

    // It is still reported, so the admin notice can point an operator at it.
    expect($transport->countAbandoned(3600))->toBe(1);
    expect($transport->countAbandoned(4 * 3600))->toBe(0);
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
