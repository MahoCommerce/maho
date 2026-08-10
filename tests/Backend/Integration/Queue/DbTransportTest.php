<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Queue\QueueManager;
use Maho\Queue\Stamp\DedupeKeyStamp;
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

it('carries the dedupe key without enforcing it when a handler continues its own chain', function () {
    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'abc');
    $envelopes = [...QueueManager::dbTransport()->getFromQueues(['default'])];
    expect($envelopes)->toHaveCount(1);

    // The handler's own row is processing under 'abc', so an enforcing dispatch
    // would swallow the continuation.
    QueueManager::dispatch(makeEmailMessage(), stamps: [new DedupeKeyStamp('abc', enforce: false)]);

    $rows = fetchQueueRows();
    expect($rows)->toHaveCount(2);
    expect(array_column($rows, 'dedupe_key'))->toBe(['abc', 'abc']);

    // The continuation is in flight under the key, so an outside dispatcher
    // still sees the chain and stays out of it.
    QueueManager::dbTransport()->ack($envelopes[0]);
    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'abc');
    expect(fetchQueueRows())->toHaveCount(1);
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
        'claimed_at' => agoUtc(7200),
    ]);
    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'abc');
    expect(fetchQueueRows())->toHaveCount(2);
});

it('never dedupes a failure-transport send against a live chain of the same key', function () {
    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'abc');

    $envelope = (new Envelope(makeEmailMessage()))->with(
        new TransportMessageIdStamp('1712345678901-0'),
        new RedeliveryStamp(0),
        new SentToFailureTransportStamp('origin'),
        new DedupeKeyStamp('abc'),
        ErrorDetailsStamp::create(new RuntimeException('handler blew up')),
    );
    QueueManager::dbTransport()->send($envelope);

    $rows = fetchQueueRows();
    expect($rows)->toHaveCount(2);
    expect($rows[1]['status'])->toBe(DbTransport::STATUS_FAILED);
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
    expect([...$transport->get()])->toHaveCount(1);
    expect(fetchQueueRows()[0]['status'])->toBe(DbTransport::STATUS_PROCESSING);
    expect(QueueManager::retryStoredMessage($id))->toBeFalse();

    // Old enough to belong to a dead worker: nothing else requeues it, so the grid must.
    queueAdapter()->update(QueueManager::tableName(), [
        'claimed_at' => agoUtc(7200),
    ]);
    expect(QueueManager::retryStoredMessage($id))->toBeTrue();
    expect(fetchQueueRows()[0]['status'])->toBe(DbTransport::STATUS_PENDING);

    // The row's owner (its re-claimer, not the stale envelope) can still fail it.
    $reclaimed = [...$transport->get()];
    $transport->reject($reclaimed[0]);
    expect(fetchQueueRows()[0]['status'])->toBe(DbTransport::STATUS_FAILED);
    expect(QueueManager::retryStoredMessage($id))->toBeTrue();
    expect(fetchQueueRows()[0]['status'])->toBe(DbTransport::STATUS_PENDING);
});

it('never hands out a claim again on its own, however old it is', function () {
    QueueManager::dispatch(makeEmailMessage());
    $transport = QueueManager::dbTransport();
    expect([...$transport->get()])->toHaveCount(1);

    queueAdapter()->update(QueueManager::tableName(), [
        'claimed_at' => agoUtc(7200),
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

it('keeps a live claim out of the abandoned set', function () {
    QueueManager::dispatch(makeEmailMessage());

    $transport = QueueManager::dbTransport();
    $envelopes = [...$transport->get()];
    expect($envelopes)->toHaveCount(1);

    queueAdapter()->update(QueueManager::tableName(), [
        'claimed_at' => agoUtc(DbTransport::ABANDONED_AFTER_SECONDS + 60),
    ], ['message_id = ?' => (int) fetchQueueRows()[0]['message_id']]);
    expect($transport->countAbandoned())->toBe(1);

    $transport->keepalive($envelopes[0]);

    expect($transport->countAbandoned())->toBe(0);
    expect($transport->countClaimed())->toBe(1);
});

it('does not revive a claim that is no longer processing', function () {
    QueueManager::dispatch(makeEmailMessage());

    $transport = QueueManager::dbTransport();
    $envelopes = [...$transport->get()];
    queueAdapter()->update(QueueManager::tableName(), [
        'status' => DbTransport::STATUS_PENDING,
        'claimed_at' => null,
    ], ['message_id = ?' => (int) fetchQueueRows()[0]['message_id']]);

    $transport->keepalive($envelopes[0]);

    $row = fetchQueueRows()[0];
    expect($row['status'])->toBe(DbTransport::STATUS_PENDING);
    expect($row['claimed_at'])->toBeNull();
});

it('does not let a late ack swallow a row an operator already retried', function () {
    QueueManager::dispatch(makeEmailMessage());

    $transport = QueueManager::dbTransport();
    $envelopes = [...$transport->get()];
    $id = (int) fetchQueueRows()[0]['message_id'];

    // The claim goes stale (the worker looks dead), so the operator re-queues it.
    queueAdapter()->update(QueueManager::tableName(), [
        'claimed_at' => agoUtc(7200),
    ], ['message_id = ?' => $id]);
    expect(QueueManager::retryStoredMessage($id))->toBeTrue();

    // The worker was alive after all and finishes: its ack must not delete or
    // complete the pending row, or the operator's retry silently vanishes.
    $transport->ack($envelopes[0]);

    $rows = fetchQueueRows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]['status'])->toBe(DbTransport::STATUS_PENDING);
});

it('does not let a stale worker touch a row another worker has since claimed', function () {
    QueueManager::dispatch(makeEmailMessage());

    $transport = QueueManager::dbTransport();
    $staleEnvelopes = [...$transport->get()];
    $id = (int) fetchQueueRows()[0]['message_id'];

    // The claim goes stale (worker A looks dead), the operator re-queues it,
    // and worker B claims the row again.
    queueAdapter()->update(QueueManager::tableName(), ['claimed_at' => agoUtc(7200)], ['message_id = ?' => $id]);
    expect(QueueManager::retryStoredMessage($id))->toBeTrue();
    $freshEnvelopes = [...$transport->get()];
    expect($freshEnvelopes)->toHaveCount(1);

    // Worker A was alive after all: its claim token no longer matches, so its
    // late ack, reject and re-send must all leave B's claim alone.
    $transport->ack($staleEnvelopes[0]);
    expect(fetchQueueRows()[0]['status'])->toBe(DbTransport::STATUS_PROCESSING);
    $transport->reject($staleEnvelopes[0]);
    expect(fetchQueueRows()[0]['status'])->toBe(DbTransport::STATUS_PROCESSING);
    $transport->send($staleEnvelopes[0]->with(
        new RedeliveryStamp(1),
        ErrorDetailsStamp::create(new RuntimeException('handler blew up')),
    ));
    expect(fetchQueueRows()[0]['status'])->toBe(DbTransport::STATUS_PROCESSING);

    // B still owns the row and its ack lands.
    $transport->ack($freshEnvelopes[0]);
    expect(fetchQueueRows())->toHaveCount(0);
});

it('refuses to retry a parked claim while a newer copy of its dedupe key is in flight', function () {
    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'abc');
    $transport = QueueManager::dbTransport();
    expect([...$transport->get()])->toHaveCount(1);
    $id = (int) fetchQueueRows()[0]['message_id'];

    // The claim is abandoned, so a later dispatch of the same key inserts a
    // fresh copy that supersedes the parked one.
    queueAdapter()->update(QueueManager::tableName(), ['claimed_at' => agoUtc(7200)], ['message_id = ?' => $id]);
    QueueManager::dispatch(makeEmailMessage(), dedupeKey: 'abc');
    expect(fetchQueueRows())->toHaveCount(2);

    // Retrying the parked claim would run the deduped job twice; discard is the way out.
    expect(QueueManager::retryStoredMessage($id))->toBeFalse();

    // Once the fresh copy is gone, the parked claim is retryable again.
    $freshId = (int) fetchQueueRows()[1]['message_id'];
    expect(QueueManager::discardStoredMessage($freshId))->toBeTrue();
    expect(QueueManager::retryStoredMessage($id))->toBeTrue();
    expect(fetchQueueRows()[0]['status'])->toBe(DbTransport::STATUS_PENDING);
});

it('does not let a late retry re-send overwrite a row an operator already retried', function () {
    QueueManager::dispatch(makeEmailMessage());

    $transport = QueueManager::dbTransport();
    $envelopes = [...$transport->get()];
    $id = (int) fetchQueueRows()[0]['message_id'];

    // The claim goes stale (the worker looks dead), so the operator re-queues it.
    queueAdapter()->update(QueueManager::tableName(), [
        'claimed_at' => agoUtc(7200),
    ], ['message_id = ?' => $id]);
    expect(QueueManager::retryStoredMessage($id))->toBeTrue();

    // The worker was alive after all and its handler fails: the retry listener's
    // in-place re-send must leave the operator's fresh pending row alone.
    $transport->send($envelopes[0]->with(
        new RedeliveryStamp(1),
        ErrorDetailsStamp::create(new RuntimeException('handler blew up')),
    ));

    $rows = fetchQueueRows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]['status'])->toBe(DbTransport::STATUS_PENDING);
    expect((int) $rows[0]['retries'])->toBe(0);
});

it('skips the keepalive refresh while the shared connection is inside a transaction', function () {
    QueueManager::dispatch(makeEmailMessage());

    $transport = QueueManager::dbTransport();
    $envelopes = [...$transport->get()];
    $id = (int) fetchQueueRows()[0]['message_id'];

    $stale = agoUtc(7200);
    queueAdapter()->update(QueueManager::tableName(), ['claimed_at' => $stale], ['message_id = ?' => $id]);

    queueAdapter()->beginTransaction();
    try {
        $transport->keepalive($envelopes[0]);
        expect(fetchQueueRows()[0]['claimed_at'])->toBe($stale);
    } finally {
        queueAdapter()->rollBack();
    }

    $transport->keepalive($envelopes[0]);
    expect(fetchQueueRows()[0]['claimed_at'])->not->toBe($stale);
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
