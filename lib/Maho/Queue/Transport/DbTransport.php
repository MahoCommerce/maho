<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue\Transport;

use Maho\Db\Adapter\AdapterInterface;
use Maho\Queue\Stamp\DedupeKeyStamp;
use Maho\Queue\Stamp\QueueNameStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Receiver\QueueReceiverInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Database transport on Maho's own DBAL adapter, portable across
 * MySQL/PostgreSQL/SQLite. Messages are claimed with an atomic
 * `UPDATE ... WHERE status='pending'` (affected-rows check), the same pattern
 * cron_schedule uses, so concurrent workers and the cron consumer never
 * double-process a row. Retries are applied in place: the retry listener's
 * re-send UPDATEs the existing row back to pending, and the subsequent
 * reject() sees the pending status and leaves it alone. Rows are never
 * silently dropped: a final failure lands as status='failed'.
 *
 * The table is declared in Maho_Queue's sql/schema.php and is never
 * auto-created here.
 */
final class DbTransport implements TransportInterface, QueueReceiverInterface, ListableReceiverInterface, MessageCountAwareInterface
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_FAILED = 'failed';
    public const STATUS_COMPLETED = 'completed';

    public const DEFAULT_QUEUE = 'default';

    /**
     * A claim held longer than this is read as abandoned by a worker that died.
     * Nothing redelivers on it: it gates the admin notice, the admin retry, and
     * the dedupe check, so an honest handler that overruns costs a notice rather
     * than a second run.
     */
    public const ABANDONED_AFTER_SECONDS = 3600;

    /**
     * @param list<string> $excludedQueues Queues this instance never consumes, so a pool worker can be "everything but"
     */
    public function __construct(
        private readonly AdapterInterface $adapter,
        private readonly string $table,
        private readonly Serializer $serializer,
        private readonly int $completedRetentionDays,
        private readonly array $excludedQueues = [],
    ) {}

    #[\Override]
    public function send(Envelope $envelope): Envelope
    {
        $now = \Mage_Core_Model_Locale::nowUtc();
        $delayMs = $envelope->last(DelayStamp::class)?->getDelay() ?? 0;
        $availableAt = $delayMs > 0
            ? gmdate(\Mage_Core_Model_Locale::DATETIME_FORMAT, time() + (int) ceil($delayMs / 1000))
            : $now;

        $messageIdStamp = $envelope->last(TransportMessageIdStamp::class);
        $redeliveryStamp = $envelope->last(RedeliveryStamp::class);
        // A failure-transport send carries both stamps too, but its id belongs to the origin transport: insert, not update.
        if ($messageIdStamp !== null && $redeliveryStamp !== null
            && $envelope->last(SentToFailureTransportStamp::class) === null) {
            $this->adapter->update($this->table, [
                'status' => self::STATUS_PENDING,
                'retries' => $redeliveryStamp->getRetryCount(),
                'available_at' => $availableAt,
                'error_message' => $envelope->last(ErrorDetailsStamp::class)?->getExceptionMessage(),
                'claimed_at' => null,
                'updated_at' => $now,
            ], ['message_id = ?' => (int) $messageIdStamp->getId()]);

            return $envelope;
        }

        $dedupeKey = $envelope->last(DedupeKeyStamp::class)?->key;
        if ($dedupeKey !== null && $this->inFlightRowExists($dedupeKey)) {
            return $envelope;
        }

        $encoded = $this->serializer->encode($envelope);
        $isFailure = $envelope->last(SentToFailureTransportStamp::class) !== null;

        $this->adapter->insert($this->table, [
            'queue' => $envelope->last(QueueNameStamp::class)->queue ?? self::DEFAULT_QUEUE,
            'status' => $isFailure ? self::STATUS_FAILED : self::STATUS_PENDING,
            'message_class' => $encoded['headers']['type'],
            'body' => $encoded['body'],
            'error_message' => $isFailure ? $envelope->last(ErrorDetailsStamp::class)?->getExceptionMessage() : null,
            'retries' => (int) ($encoded['headers']['retries'] ?? 0),
            'dedupe_key' => $dedupeKey,
            'available_at' => $availableAt,
            'claimed_at' => null,
            'processed_at' => $isFailure ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $envelope->with(new TransportMessageIdStamp((int) $this->adapter->lastInsertId($this->table)));
    }

    #[\Override]
    public function get(): iterable
    {
        return $this->claimNext(null);
    }

    #[\Override]
    public function getFromQueues(array $queueNames): iterable
    {
        return $this->claimNext($queueNames);
    }

    #[\Override]
    public function ack(Envelope $envelope): void
    {
        $messageId = $this->messageId($envelope);
        if ($this->completedRetentionDays > 0) {
            $now = \Mage_Core_Model_Locale::nowUtc();
            $this->adapter->update($this->table, [
                'status' => self::STATUS_COMPLETED,
                'processed_at' => $now,
                'updated_at' => $now,
            ], ['message_id = ?' => $messageId]);
        } else {
            $this->adapter->delete($this->table, ['message_id = ?' => $messageId]);
        }
    }

    #[\Override]
    public function reject(Envelope $envelope): void
    {
        $now = \Mage_Core_Model_Locale::nowUtc();
        // A row the retry listener already re-queued in place stays pending.
        $this->adapter->update($this->table, [
            'status' => self::STATUS_FAILED,
            'error_message' => $envelope->last(ErrorDetailsStamp::class)?->getExceptionMessage(),
            'processed_at' => $now,
            'updated_at' => $now,
        ], [
            'message_id = ?' => $this->messageId($envelope),
            'status = ?' => self::STATUS_PROCESSING,
        ]);
    }

    #[\Override]
    public function all(?int $limit = null): iterable
    {
        $select = $this->adapter->select()
            ->from($this->table)
            ->order('message_id ASC');
        if ($limit !== null) {
            $select->limit($limit);
        }

        foreach ($this->adapter->fetchAll($select) as $row) {
            $envelope = $this->hydrate($row);
            if ($envelope !== null) {
                yield $envelope;
            }
        }
    }

    #[\Override]
    public function find(mixed $id): ?Envelope
    {
        $row = $this->adapter->fetchRow(
            $this->adapter->select()->from($this->table)->where('message_id = ?', (int) $id),
        );

        return $row === false ? null : $this->hydrate($row);
    }

    #[\Override]
    public function getMessageCount(): int
    {
        $select = $this->adapter->select()
            ->from($this->table, new \Maho\Db\Expr('COUNT(*)'))
            ->where('status = ?', self::STATUS_PENDING);
        $this->applyQueueFilter($select, null);

        return (int) $this->adapter->fetchOne($select);
    }

    /**
     * Work this instance would pick up right now. Messages scheduled for the
     * future are deliberately excluded, or the watchdog would respawn an
     * on-demand worker every cron tick until a delayed campaign's send date.
     *
     * @param list<string>|null $queues
     */
    public function countDue(?array $queues = null): int
    {
        $select = $this->adapter->select()
            ->from($this->table, new \Maho\Db\Expr('COUNT(*)'))
            ->where('status = ?', self::STATUS_PENDING)
            ->where('available_at <= ?', \Mage_Core_Model_Locale::nowUtc());
        $this->applyQueueFilter($select, $queues);

        return (int) $this->adapter->fetchOne($select);
    }

    /**
     * Claims old enough to be read as abandoned by a worker that died. Nothing
     * acts on this: it drives the admin notice, so an honest handler that
     * overruns costs a notice rather than a second run.
     */
    public function countAbandoned(int $olderThanSeconds = self::ABANDONED_AFTER_SECONDS): int
    {
        $select = $this->adapter->select()
            ->from($this->table, new \Maho\Db\Expr('COUNT(*)'))
            ->where('status = ?', self::STATUS_PROCESSING)
            ->where('claimed_at < ?', self::abandonedBefore($olderThanSeconds));
        $this->applyQueueFilter($select, null);

        return (int) $this->adapter->fetchOne($select);
    }

    /**
     * @param list<string>|null $queues
     */
    private function applyQueueFilter(\Maho\Db\Select $select, ?array $queues): void
    {
        if ($queues !== null && $queues !== []) {
            $select->where('queue IN (?)', $queues);
        }
        if ($this->excludedQueues !== []) {
            $select->where('queue NOT IN (?)', $this->excludedQueues);
        }
    }

    /**
     * @param  list<string>|null $queues
     * @return list<Envelope>
     */
    private function claimNext(?array $queues): array
    {
        $now = \Mage_Core_Model_Locale::nowUtc();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $select = $this->adapter->select()
                ->from($this->table)
                ->where('status = ?', self::STATUS_PENDING)
                ->where('available_at <= ?', $now)
                ->order(['available_at ASC', 'message_id ASC'])
                ->limit(1);
            $this->applyQueueFilter($select, $queues);

            $row = $this->adapter->fetchRow($select);
            if ($row === false) {
                return [];
            }

            $claimed = $this->adapter->update($this->table, [
                'status' => self::STATUS_PROCESSING,
                'claimed_at' => $now,
                'updated_at' => $now,
            ], [
                'message_id = ?' => (int) $row['message_id'],
                'status = ?' => self::STATUS_PENDING,
            ]);
            if ($claimed !== 1) {
                // Another consumer won the row: pick the next candidate.
                continue;
            }

            $envelope = $this->hydrateOrFail($row);
            if ($envelope !== null) {
                return [$envelope];
            }
        }

        return [];
    }

    private function inFlightRowExists(string $dedupeKey): bool
    {
        // A claim a dead worker left behind waits for an operator forever, so it
        // must not keep suppressing new dispatches of the same key: that would
        // silently drop every later send instead of parking one message.
        // Pending rows always have a null claimed_at, so they are never excluded.
        $existing = $this->adapter->fetchOne(
            $this->adapter->select()
                ->from($this->table, 'message_id')
                ->where('dedupe_key = ?', $dedupeKey)
                ->where('status IN (?)', [self::STATUS_PENDING, self::STATUS_PROCESSING])
                ->where('claimed_at IS NULL OR claimed_at >= ?', self::abandonedBefore())
                ->limit(1),
        );

        return $existing !== false && $existing !== null;
    }

    /** UTC cut-off before which a claim counts as abandoned. */
    public static function abandonedBefore(int $olderThanSeconds = self::ABANDONED_AFTER_SECONDS): string
    {
        return gmdate(\Mage_Core_Model_Locale::DATETIME_FORMAT, time() - $olderThanSeconds);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ?Envelope
    {
        try {
            $envelope = $this->serializer->decode([
                'body' => (string) $row['body'],
                'headers' => [
                    'type' => (string) $row['message_class'],
                    'retries' => (string) $row['retries'],
                ],
            ]);
        } catch (MessageDecodingFailedException) {
            return null;
        }

        return $envelope->with(new TransportMessageIdStamp((int) $row['message_id']));
    }

    /**
     * Like hydrate(), but a decode failure marks the row failed so it surfaces
     * in the admin grid instead of being retried forever.
     *
     * @param array<string, mixed> $row
     */
    private function hydrateOrFail(array $row): ?Envelope
    {
        try {
            $envelope = $this->serializer->decode([
                'body' => (string) $row['body'],
                'headers' => [
                    'type' => (string) $row['message_class'],
                    'retries' => (string) $row['retries'],
                ],
            ]);
        } catch (MessageDecodingFailedException $e) {
            $now = \Mage_Core_Model_Locale::nowUtc();
            $this->adapter->update($this->table, [
                'status' => self::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'processed_at' => $now,
                'updated_at' => $now,
            ], ['message_id = ?' => (int) $row['message_id']]);

            return null;
        }

        return $envelope->with(new TransportMessageIdStamp((int) $row['message_id']));
    }

    private function messageId(Envelope $envelope): int
    {
        $stamp = $envelope->last(TransportMessageIdStamp::class)
            ?? throw new TransportException('Envelope is missing its TransportMessageIdStamp');

        return (int) $stamp->getId();
    }
}
