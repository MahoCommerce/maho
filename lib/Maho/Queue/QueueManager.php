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
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Entry point of the Maho message queue: dispatch a message object and a
 * compiled `#[Maho\Config\MessageHandler]` method consumes it asynchronously.
 *
 * ```php
 * \Maho\Queue\QueueManager::dispatch(new My_Module_Model_SomeMessage(...));
 * ```
 *
 * The default transport stores messages in the maho_queue_message table; a
 * `<queue><dsn>redis://...</dsn></queue>` node under `<global>` in
 * app/etc/local.xml switches to Redis (requires symfony/redis-messenger).
 * With the DB transport, dispatching inside a database transaction
 * participates in it: the message becomes visible only on commit.
 */
final class QueueManager
{
    public const TRANSPORT_DB = 'db';
    public const TRANSPORT_REDIS = 'redis';

    public const XML_PATH_MAX_RETRIES = 'system/queue/max_retries';
    public const XML_PATH_RETRY_DELAY = 'system/queue/retry_delay';
    public const XML_PATH_RETRY_MULTIPLIER = 'system/queue/retry_multiplier';
    public const XML_PATH_RETRY_MAX_DELAY = 'system/queue/retry_max_delay';
    public const XML_PATH_REDELIVER_AFTER = 'system/queue/redeliver_after';
    public const XML_PATH_COMPLETED_RETENTION = 'system/queue/completed_retention';
    public const XML_PATH_FAILED_RETENTION = 'system/queue/failed_retention';

    private static ?MessageBus $bus = null;
    private static ?TransportInterface $transport = null;
    private static ?DbTransport $dbTransport = null;
    private static ?Serializer $serializer = null;
    private static ?string $transportName = null;

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
                ['*' => [self::transportName()]],
                new ServiceLocator([self::transportName() => self::transport()]),
            )),
            new HandleMessageMiddleware(HandlerRegistry::handlersLocator()),
        ]);
    }

    public static function transport(): TransportInterface
    {
        if (self::$transport !== null) {
            return self::$transport;
        }

        if (self::transportName() === self::TRANSPORT_REDIS) {
            $factory = new \Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransportFactory(); // @phpstan-ignore class.notFound
            return self::$transport = $factory->createTransport((string) self::redisDsn(), [], self::serializer()); // @phpstan-ignore class.notFound
        }

        return self::$transport = self::dbTransport();
    }

    public static function transportName(): string
    {
        if (self::$transportName !== null) {
            return self::$transportName;
        }

        $dsn = self::redisDsn();
        if ($dsn !== null && !class_exists('Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransportFactory')) {
            throw new \RuntimeException('global/queue/dsn is set in app/etc/local.xml but the symfony/redis-messenger package is not installed; run "composer require symfony/redis-messenger" or remove the node');
        }

        return self::$transportName = $dsn !== null ? self::TRANSPORT_REDIS : self::TRANSPORT_DB;
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

    public static function serializer(): Serializer
    {
        return self::$serializer ??= new Serializer();
    }

    /**
     * Re-queue a stored failed message from the admin grid or CLI. DB mode
     * flips the row back to pending with a fresh retry budget; Redis failure
     * rows are re-dispatched through the bus and the stored row removed.
     * Only failed rows are retryable: flipping a claimed row would race the
     * worker's ack.
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

        if (self::transportName() === self::TRANSPORT_DB) {
            $now = \Mage_Core_Model_Locale::nowUtc();
            return $adapter->update($table, [
                'status' => DbTransport::STATUS_PENDING,
                'retries' => 0,
                'available_at' => $now,
                'claimed_at' => null,
                'processed_at' => null,
                'updated_at' => $now,
            ], [
                'message_id = ?' => $messageId,
                'status = ?' => DbTransport::STATUS_FAILED,
            ]) === 1;
        }

        $envelope = self::serializer()->decode([
            'body' => (string) $row['body'],
            'headers' => ['type' => (string) $row['message_class']],
        ]);
        self::bus()->dispatch($envelope->getMessage(), [new QueueNameStamp((string) $row['queue'])]);
        $adapter->delete($table, ['message_id = ?' => $messageId]);

        return true;
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
        self::$transport = null;
        self::$dbTransport = null;
        self::$serializer = null;
        self::$transportName = null;
        HandlerRegistry::reset();
    }

    private static function redisDsn(): ?string
    {
        $node = \Mage::getConfig()->getNode('global/queue/dsn');
        if ($node === false) {
            return null;
        }
        $dsn = trim((string) $node);

        return $dsn === '' ? null : $dsn;
    }

    private static function writeAdapter(): \Maho\Db\Adapter\AdapterInterface
    {
        return \Mage::getSingleton('core/resource')->getConnection('core_write');
    }
}
