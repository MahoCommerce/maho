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

    public function __construct(
        private readonly AdapterInterface $adapter,
        private readonly string $table,
        private readonly Serializer $serializer,
        private readonly int $redeliverAfterSeconds,
        private readonly int $completedRetentionDays,
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
        if ($messageIdStamp !== null && $redeliveryStamp !== null) {
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
        if ($dedupeKey !== null && $this->pendingRowExists($dedupeKey)) {
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
        $messageId = $this->messageId($envelope);
        $status = $this->adapter->fetchOne(
            $this->adapter->select()->from($this->table, 'status')->where('message_id = ?', $messageId),
        );
        if ($status === false || $status === null || $status === self::STATUS_PENDING) {
            // Already re-queued in place by the retry listener (or gone): not a final failure.
            return;
        }

        $now = \Mage_Core_Model_Locale::nowUtc();
        $this->adapter->update($this->table, [
            'status' => self::STATUS_FAILED,
            'error_message' => $envelope->last(ErrorDetailsStamp::class)?->getExceptionMessage(),
            'processed_at' => $now,
            'updated_at' => $now,
        ], ['message_id = ?' => $messageId]);
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
        return (int) $this->adapter->fetchOne(
            $this->adapter->select()
                ->from($this->table, new \Maho\Db\Expr('COUNT(*)'))
                ->where('status = ?', self::STATUS_PENDING),
        );
    }

    /**
     * @param  list<string>|null $queues
     * @return list<Envelope>
     */
    private function claimNext(?array $queues): array
    {
        $this->requeueStaleClaims();
        $now = \Mage_Core_Model_Locale::nowUtc();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $select = $this->adapter->select()
                ->from($this->table)
                ->where('status = ?', self::STATUS_PENDING)
                ->where('available_at <= ?', $now)
                ->order(['available_at ASC', 'message_id ASC'])
                ->limit(1);
            if ($queues !== null && $queues !== []) {
                $select->where('queue IN (?)', $queues);
            }

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

    /**
     * Crash recovery: rows claimed longer ago than redeliver_after belong to a
     * worker that died without ack/reject; put them back up for grabs.
     */
    private function requeueStaleClaims(): void
    {
        if ($this->redeliverAfterSeconds <= 0) {
            return;
        }

        $this->adapter->update($this->table, [
            'status' => self::STATUS_PENDING,
            'claimed_at' => null,
            'updated_at' => \Mage_Core_Model_Locale::nowUtc(),
        ], [
            'status = ?' => self::STATUS_PROCESSING,
            'claimed_at < ?' => gmdate(\Mage_Core_Model_Locale::DATETIME_FORMAT, time() - $this->redeliverAfterSeconds),
        ]);
    }

    private function pendingRowExists(string $dedupeKey): bool
    {
        $existing = $this->adapter->fetchOne(
            $this->adapter->select()
                ->from($this->table, 'message_id')
                ->where('dedupe_key = ?', $dedupeKey)
                ->where('status = ?', self::STATUS_PENDING)
                ->limit(1),
        );

        return $existing !== false && $existing !== null;
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
