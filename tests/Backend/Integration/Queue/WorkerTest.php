<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Queue\QueueManager;
use Maho\Queue\ServiceLocator;
use Maho\Queue\StopWorkerWhenIdleListener;
use Maho\Queue\Transport\DbTransport;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Worker;

uses(Tests\MahoBackendTestCase::class);

/**
 * Runs a Worker over the real DbTransport with a controllable handler, so the
 * retry-in-place and reject-no-op transport semantics stay pinned even if a
 * Messenger minor release changes the listener/worker interplay.
 */
function runQueueWorker(callable $handler, int $maxRetries, int $retryDelayMs, array $extraSubscribers = []): void
{
    $transport = QueueManager::dbTransport();
    $bus = new MessageBus([
        new HandleMessageMiddleware(new HandlersLocator([
            Mage_Core_Model_Email_SendMessage::class => [new HandlerDescriptor($handler, ['alias' => 'test'])],
        ])),
    ]);

    $dispatcher = new EventDispatcher();
    $dispatcher->addSubscriber(new AddErrorDetailsStampListener());
    $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(
        new ServiceLocator(['db' => $transport]),
        new ServiceLocator(['db' => new MultiplierRetryStrategy($maxRetries, $retryDelayMs, 1, 0, 0)]),
    ));
    $dispatcher->addSubscriber(new StopWorkerWhenIdleListener());
    foreach ($extraSubscribers as $subscriber) {
        $dispatcher->addSubscriber($subscriber);
    }

    (new Worker(['db' => $transport], $bus, $dispatcher))->run(['sleep' => 0]);
}

beforeEach(function () {
    QueueManager::reset();
    queueAdapter()->delete(QueueManager::tableName());
});

afterEach(function () {
    queueAdapter()->delete(QueueManager::tableName());
    QueueManager::reset();
});

it('deletes the row when the handler succeeds', function () {
    QueueManager::dispatch(makeEmailMessage());

    $handled = 0;
    runQueueWorker(function () use (&$handled): void {
        $handled++;
    }, 3, 0);

    expect($handled)->toBe(1);
    expect(fetchQueueRows())->toHaveCount(0);
});

it('re-queues a failed message in place with a backoff delay', function () {
    QueueManager::dispatch(makeEmailMessage());

    runQueueWorker(function (): void {
        throw new RuntimeException('boom');
    }, 3, 60_000);

    $rows = fetchQueueRows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]['status'])->toBe(DbTransport::STATUS_PENDING);
    expect((int) $rows[0]['retries'])->toBe(1);
    expect($rows[0]['error_message'])->toContain('boom');
    expect($rows[0]['available_at'])->toBeGreaterThan(Mage_Core_Model_Locale::nowUtc());
});

it('marks the row failed once retries are exhausted', function () {
    QueueManager::dispatch(makeEmailMessage());

    $attempts = 0;
    runQueueWorker(function () use (&$attempts): void {
        $attempts++;
        throw new RuntimeException('always fails');
    }, 2, 0);

    expect($attempts)->toBe(3);

    $rows = fetchQueueRows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]['status'])->toBe(DbTransport::STATUS_FAILED);
    expect((int) $rows[0]['retries'])->toBe(2);
    expect($rows[0]['error_message'])->toContain('always fails');
    expect($rows[0]['processed_at'])->not->toBeNull();
});

it('fails immediately on an unrecoverable exception', function () {
    QueueManager::dispatch(makeEmailMessage());

    $attempts = 0;
    runQueueWorker(function () use (&$attempts): void {
        $attempts++;
        throw new UnrecoverableMessageHandlingException('bad address');
    }, 3, 0);

    expect($attempts)->toBe(1);

    $rows = fetchQueueRows();
    expect($rows[0]['status'])->toBe(DbTransport::STATUS_FAILED);
    expect((int) $rows[0]['retries'])->toBe(0);
});

it('stops the worker when store configuration changes', function () {
    QueueManager::dispatch(makeEmailMessage('first'));
    QueueManager::dispatch(makeEmailMessage('second'));

    $listener = new \Maho\Queue\StopWorkerOnConfigChangeListener(0);
    try {
        runQueueWorker(function (): void {
            Mage::getConfig()->saveConfig('queue_test/probe/value', uniqid());
        }, 3, 0, [$listener]);

        $rows = fetchQueueRows();
        expect($rows)->toHaveCount(1);
        expect($rows[0]['status'])->toBe(DbTransport::STATUS_PENDING);
    } finally {
        Mage::getConfig()->deleteConfig('queue_test/probe/value');
    }
});

it('keeps completed rows when a retention period is configured', function () {
    Mage::app()->getStore()->setConfig('system/queue/completed_retention', '7');
    QueueManager::reset();
    QueueManager::dispatch(makeEmailMessage());

    runQueueWorker(function (): void {}, 3, 0);

    $rows = fetchQueueRows();
    expect($rows)->toHaveCount(1);
    expect($rows[0]['status'])->toBe(DbTransport::STATUS_COMPLETED);
    expect($rows[0]['processed_at'])->not->toBeNull();
});
