<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue;

use Maho\Queue\Stamp\DedupeKeyStamp;
use Maho\Queue\Stamp\QueueNameStamp;
use Maho\Queue\Transport\DbTransport;
use Maho\Queue\Transport\Serializer;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\AddBusNameStampMiddleware;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;

/**
 * Entry point of the Maho message queue: dispatch a message object and a
 * compiled `#[Maho\Config\MessageHandler]` method consumes it asynchronously.
 *
 * ```php
 * \Maho\Queue\QueueManager::dispatch(new My_Module_Model_SomeMessage(...));
 * ```
 *
 * Messages are stored in the maho_queue_message table, so dispatching inside a
 * database transaction participates in it: the message becomes visible only on
 * commit.
 */
final class QueueManager
{
    /** The name this transport is registered under with Messenger. */
    public const TRANSPORT_DB = 'db';

    public const XML_PATH_MAX_RETRIES = 'system/queue/max_retries';
    public const XML_PATH_RETRY_DELAY = 'system/queue/retry_delay';
    public const XML_PATH_RETRY_MULTIPLIER = 'system/queue/retry_multiplier';
    public const XML_PATH_RETRY_MAX_DELAY = 'system/queue/retry_max_delay';
    public const XML_PATH_REDELIVER_AFTER = 'system/queue/redeliver_after';
    public const XML_PATH_COMPLETED_RETENTION = 'system/queue/completed_retention';
    public const XML_PATH_FAILED_RETENTION = 'system/queue/failed_retention';

    private static ?MessageBus $bus = null;
    private static ?DbTransport $dbTransport = null;
    private static ?Serializer $serializer = null;

    /**
     * Dispatch a message for asynchronous handling.
     *
     * @param object               $message      A flat DTO with a compiled `#[Maho\Config\MessageHandler]` handler
     * @param ?int                 $delaySeconds Earliest handling delay
     * @param string               $queue        Logical queue name, consumable in isolation via `queue:work --queue=<name>`
     * @param ?string              $dedupeKey    While a pending or processing message with this key exists, dispatch is a no-op (DB transport)
     * @param list<StampInterface> $stamps       Additional Messenger stamps
     */
    public static function dispatch(
        object $message,
        ?int $delaySeconds = null,
        string $queue = DbTransport::DEFAULT_QUEUE,
        ?string $dedupeKey = null,
        array $stamps = [],
    ): Envelope {
        if (!\Mage::helper('core')->isModuleEnabled('Maho_Queue')) {
            throw new \RuntimeException('Cannot dispatch queue message: the Maho_Queue module is disabled');
        }

        $stamps[] = new QueueNameStamp($queue);
        if ($delaySeconds !== null && $delaySeconds > 0) {
            $stamps[] = new DelayStamp($delaySeconds * 1000);
        }
        if ($dedupeKey !== null) {
            $stamps[] = new DedupeKeyStamp($dedupeKey);
        }

        return self::bus()->dispatch($message, $stamps);
    }

    public static function bus(): MessageBusInterface
    {
        return self::$bus ??= new MessageBus([
            new AddBusNameStampMiddleware('maho'),
            new SendMessageMiddleware(new SendersLocator(
                ['*' => [self::TRANSPORT_DB]],
                new ServiceLocator([self::TRANSPORT_DB => self::dbTransport()]),
            )),
            new HandleMessageMiddleware(HandlerRegistry::handlersLocator()),
        ]);
    }

    public static function dbTransport(): DbTransport
    {
        return self::$dbTransport ??= new DbTransport(
            self::writeAdapter(),
            self::tableName(),
            self::serializer(),
            (int) \Mage::getStoreConfig(self::XML_PATH_REDELIVER_AFTER),
            (int) \Mage::getStoreConfig(self::XML_PATH_COMPLETED_RETENTION),
        );
    }

    /**
     * The transport a pool's worker consumes from: the shared one unless the
     * pool narrows what it sees, overrides the redelivery window, or the caller
     * claims under a worker id, in which case it gets its own instance.
     *
     * @param ?string $workerId Only a pool worker holding its lock passes one; see WorkerIdentity
     */
    public static function workerTransport(?Pool $pool = null, ?string $workerId = null): DbTransport
    {
        if ($pool === null) {
            return self::dbTransport();
        }

        if ($workerId === null && $pool->excludedQueues === [] && $pool->redeliverAfter === null) {
            return self::dbTransport();
        }

        return new DbTransport(
            self::writeAdapter(),
            self::tableName(),
            self::serializer(),
            $pool->redeliverAfter ?? (int) \Mage::getStoreConfig(self::XML_PATH_REDELIVER_AFTER),
            (int) \Mage::getStoreConfig(self::XML_PATH_COMPLETED_RETENTION),
            $pool->excludedQueues,
            $workerId,
        );
    }

    public static function serializer(): Serializer
    {
        return self::$serializer ??= new Serializer();
    }

    /**
     * Re-queue a stored failed message from the admin grid or CLI, flipping the
     * row back to pending with a fresh retry budget. Only failed rows are
     * retryable: flipping a claimed row would race the worker's ack.
     */
    public static function retryStoredMessage(int $messageId): bool
    {
        $adapter = self::writeAdapter();
        $table = self::tableName();
        $row = $adapter->fetchRow(
            $adapter->select()->from($table)->where('message_id = ?', $messageId),
        );
        if ($row === false || $row['status'] !== DbTransport::STATUS_FAILED) {
            return false;
        }

        $now = \Mage_Core_Model_Locale::nowUtc();

        return $adapter->update($table, [
            'status' => DbTransport::STATUS_PENDING,
            'retries' => 0,
            'available_at' => $now,
            'claimed_at' => null,
            'claimed_by' => null,
            'processed_at' => null,
            'updated_at' => $now,
        ], [
            'message_id = ?' => $messageId,
            'status = ?' => DbTransport::STATUS_FAILED,
        ]) === 1;
    }

    public static function discardStoredMessage(int $messageId): bool
    {
        return self::writeAdapter()->delete(self::tableName(), ['message_id = ?' => $messageId]) > 0;
    }

    public static function tableName(): string
    {
        return \Mage::getSingleton('core/resource')->getTableName('queue/message');
    }

    /**
     * Drop all memoised services, e.g. between tests or after a config change.
     */
    public static function reset(): void
    {
        self::$bus = null;
        self::$dbTransport = null;
        self::$serializer = null;
        HandlerRegistry::reset();
        PoolRegistry::reset();
    }

    private static function writeAdapter(): \Maho\Db\Adapter\AdapterInterface
    {
        return \Mage::getSingleton('core/resource')->getConnection('core_write');
    }
}
