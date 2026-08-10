<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Queue\Pool;
use Maho\Queue\PoolRegistry;
use Maho\Queue\QueueManager;
use Maho\Queue\StopWorkerWhenIdleListener;
use Maho\Queue\Transport\DbTransport;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\Worker;

uses(Tests\MahoBackendTestCase::class);

function queuePool(string $name): Pool
{
    return PoolRegistry::get($name) ?? throw new RuntimeException("pool {$name} is missing");
}

function poolTransport(string $name): DbTransport
{
    $transport = QueueManager::workerTransport(queuePool($name));
    assert($transport instanceof DbTransport);

    return $transport;
}

/**
 * Merge extra queue config the way another module's config.xml would, run the
 * assertions, then take it back out again.
 *
 * @param callable():void $body
 */
function withQueueConfig(string $xml, callable $body): void
{
    $node = Mage::getConfig()->getNode('global/queue');
    $node->extend(new Maho\Simplexml\Element($xml), true);
    QueueManager::reset();

    try {
        $body();
    } finally {
        foreach (new Maho\Simplexml\Element($xml) as $section => $children) {
            foreach (array_keys((array) $children->children()) as $name) {
                unset($node->{$section}->{$name});
            }
        }
        QueueManager::reset();
    }
}

function insertQueueRow(string $queue, string $status, ?string $claimedAt = null): void
{
    $now = Mage_Core_Model_Locale::nowUtc();
    queueAdapter()->insert(QueueManager::tableName(), [
        'queue' => $queue,
        'status' => $status,
        'message_class' => Mage_Core_Model_Email_SendMessage::class,
        'body' => serialize(makeEmailMessage()),
        'available_at' => $now,
        'claimed_at' => $claimedAt,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function agoUtc(int $seconds): string
{
    return gmdate(Mage_Core_Model_Locale::DATETIME_FORMAT, time() - $seconds);
}

/**
 * A Worker that only records stop(), so the idle listener can be driven
 * directly without a transport behind it.
 */
class RecordingWorker extends Worker
{
    public int $stopped = 0;

    public function __construct() {}

    #[\Override]
    public function stop(): void
    {
        $this->stopped++;
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

it('ships a resident fast tier and an on-demand slow catch-all', function () {
    expect(array_keys(PoolRegistry::all()))->toBe(['fast', 'slow']);
    expect(queuePool('fast')->queues)->toBe([Mage_Core_Model_Email_Queue::QUEUE_NAME]);
    expect(queuePool('fast')->isOnDemand())->toBeFalse();
    expect(queuePool('slow')->queues)->toBe([]);
    expect(queuePool('slow')->isOnDemand())->toBeTrue();
});

it('keeps the catch-all off the queues another pool claims', function () {
    expect(queuePool('slow')->excludedQueues)->toBe([Mage_Core_Model_Email_Queue::QUEUE_NAME]);
    expect(queuePool('slow')->consumes(Mage_Core_Model_Email_Queue::QUEUE_NAME))->toBeFalse();
    expect(queuePool('slow')->consumes('newsletter'))->toBeTrue();
});

it('routes an unclassified queue to the slow tier rather than leaving it unconsumed', function () {
    expect(PoolRegistry::poolFor('some_third_party_queue')?->name)->toBe('slow');
    expect(PoolRegistry::poolFor(DbTransport::DEFAULT_QUEUE)?->name)->toBe('slow');
});

it('lets another module route its own queue without clobbering the existing map', function () {
    withQueueConfig('<queue><routing><zz_scratch>fast</zz_scratch></routing></queue>', function () {
        expect(queuePool('fast')->queues)->toBe([Mage_Core_Model_Email_Queue::QUEUE_NAME, 'zz_scratch']);
        expect(queuePool('slow')->excludedQueues)->toContain('zz_scratch');
    });
});

it('hands a queue routed to an unknown pool back to the catch-all', function () {
    withQueueConfig('<queue><routing><zz_scratch>nowhere</zz_scratch></routing></queue>', function () {
        expect(PoolRegistry::poolFor('zz_scratch')?->name)->toBe('slow');
    });
});

it('skips a pool with nothing routed to it instead of letting it rival the catch-all', function () {
    withQueueConfig('<queue><pools><zz_spare><sort_order>5</sort_order></zz_spare></pools></queue>', function () {
        expect(array_keys(PoolRegistry::all()))->toBe(['fast', 'slow']);
    });
});

it('reads pool flags with core config semantics, so "true" means on', function () {
    withQueueConfig('<queue><routing><zz_q>zz_flag</zz_q></routing><pools><zz_flag><active>true</active><sort_order>5</sort_order></zz_flag></pools></queue>', function () {
        expect(PoolRegistry::get('zz_flag'))->not->toBeNull();
    });
});

it('drops a pool declared inactive with "false"', function () {
    withQueueConfig('<queue><routing><zz_q>zz_flag</zz_q></routing><pools><zz_flag><active>false</active><sort_order>5</sort_order></zz_flag></pools></queue>', function () {
        expect(PoolRegistry::get('zz_flag'))->toBeNull();
        expect(PoolRegistry::poolFor('zz_q')?->name)->toBe('slow');
    });
});

it('keeps memory_limit 0 as unbounded instead of falling back to the default', function () {
    withQueueConfig('<queue><routing><zz_q>zz_pool</zz_q></routing><pools><zz_pool><sort_order>5</sort_order><memory_limit>0</memory_limit></zz_pool></pools></queue>', function () {
        expect(queuePool('zz_pool')->memoryLimit)->toBe('0');
    });
});

it('does not hand the catch-all worker a message belonging to another pool', function () {
    QueueManager::dispatch(makeEmailMessage(), queue: Mage_Core_Model_Email_Queue::QUEUE_NAME);
    QueueManager::dispatch(makeEmailMessage('newsletter batch'), queue: 'newsletter');

    expect(iterator_to_array(poolTransport('slow')->get()))->toHaveCount(1);

    $processing = array_values(array_filter(
        fetchQueueRows(),
        fn($row) => $row['status'] === DbTransport::STATUS_PROCESSING,
    ));
    expect($processing)->toHaveCount(1);
    expect($processing[0]['queue'])->toBe('newsletter');
});

it('counts only work that is due, not a campaign scheduled for later', function () {
    // The regression this guards: a campaign queued as a long-delayed message
    // would make a watchdog probing raw pending counts respawn the on-demand
    // worker every cron tick until its send date.
    QueueManager::dispatch(
        makeEmailMessage(),
        delaySeconds: 7 * 86400,
        queue: 'newsletter',
    );

    expect(poolTransport('slow')->getMessageCount())->toBe(1);
    expect(poolTransport('slow')->countDue())->toBe(0);
});

it('never counts a claim as due, however old, so no worker is respawned for it', function () {
    insertQueueRow('newsletter', DbTransport::STATUS_PROCESSING, agoUtc(4 * 3600));

    // An abandoned claim waits for an operator, so the watchdog must not treat
    // it as work: respawning for it would start a worker with nothing to do.
    expect(poolTransport('slow')->countDue())->toBe(0);
    expect(iterator_to_array(poolTransport('slow')->get()))->toHaveCount(0);
    expect(fetchQueueRows()[0]['status'])->toBe(DbTransport::STATUS_PROCESSING);
});

it('stops an idle worker immediately when no grace period is set', function () {
    $worker = new RecordingWorker();
    (new StopWorkerWhenIdleListener(0))->onWorkerRunning(new WorkerRunningEvent($worker, true));

    expect($worker->stopped)->toBe(1);
});

it('holds an idle worker open for the grace period', function () {
    $worker = new RecordingWorker();
    $listener = new StopWorkerWhenIdleListener(3600);

    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

    expect($worker->stopped)->toBe(0);
});

it('restarts the grace period when work arrives', function () {
    $worker = new RecordingWorker();
    $listener = new StopWorkerWhenIdleListener(1);

    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));
    sleep(2);
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, false));
    $listener->onWorkerRunning(new WorkerRunningEvent($worker, true));

    expect($worker->stopped)->toBe(0);
});
