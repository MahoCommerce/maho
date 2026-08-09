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
use Maho\Queue\WorkerIdentity;
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
     * @param list<string> $excludedQueues Queues this instance never consumes, so a pool worker can be "everything but"
     * @param ?string      $workerId       Stamped on every claim; set only by a worker holding its lock, so a free lock proves the claim is orphaned
     */
    public function __construct(
        private readonly AdapterInterface $adapter,
        private readonly string $table,
        private readonly Serializer $serializer,
        private readonly int $redeliverAfterSeconds,
        private readonly int $completedRetentionDays,
        private readonly array $excludedQueues = [],
        private readonly ?string $workerId = null,
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
                'claimed_by' => null,
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
            'claimed_by' => null,
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
     * Work this instance would pick up right now: rows past their availability
     * plus rows a dead worker abandoned. Messages scheduled for the future are
     * deliberately excluded, or the watchdog would respawn an on-demand worker
     * every cron tick until a delayed campaign's send date.
     *
     * @param list<string>|null $queues
     */
    public function countDue(?array $queues = null): int
    {
        $clauses = ['(' . $this->adapter->quoteInto('status = ?', self::STATUS_PENDING)
            . ' AND ' . $this->adapter->quoteInto('available_at <= ?', \Mage_Core_Model_Locale::nowUtc()) . ')'];

        $abandoned = $this->abandonedClaimClause();
        if ($abandoned !== null) {
            $clauses[] = '(' . $this->adapter->quoteInto('status = ?', self::STATUS_PROCESSING)
                . ' AND ' . $abandoned . ')';
        }

        $select = $this->adapter->select()
            ->from($this->table, new \Maho\Db\Expr('COUNT(*)'))
            ->where(implode(' OR ', $clauses));
        $this->applyQueueFilter($select, $queues);

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
        $this->requeueStaleClaims($queues);
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
                'claimed_by' => $this->workerId,
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
     * Crash recovery: a row still processing whose worker died without ack or
     * reject goes back up for grabs.
     *
     * Scoped to the queues this instance consumes, because pools carry their own
     * window: a fast worker must not requeue a feed a slow worker is still running.
     *
     * @param list<string>|null $queues
     */
    private function requeueStaleClaims(?array $queues): void
    {
        $abandoned = $this->abandonedClaimClause();
        if ($abandoned === null) {
            return;
        }

        $where = [
            'status = ?' => self::STATUS_PROCESSING,
            $abandoned,
        ];
        if ($queues !== null && $queues !== []) {
            $where['queue IN (?)'] = $queues;
        }
        if ($this->excludedQueues !== []) {
            $where['queue NOT IN (?)'] = $this->excludedQueues;
        }

        $this->adapter->update($this->table, [
            'status' => self::STATUS_PENDING,
            'claimed_at' => null,
            'claimed_by' => null,
            'updated_at' => \Mage_Core_Model_Locale::nowUtc(),
        ], $where);
    }

    /**
     * Rows whose claiming worker is gone, to AND with `status = processing`.
     *
     * A claim from this machine is settled by its worker lock, not by the clock:
     * a free lock proves the process died, a held one proves the handler is
     * still running however long it takes. Claims from another machine have no
     * lock to read here, so they keep the redeliver_after timer, as do rows with
     * no id (mid-upgrade, or a worker running without --exclusive).
     */
    private function abandonedClaimClause(): ?string
    {
        if ($this->redeliverAfterSeconds <= 0) {
            return null;
        }

        $clauses = [];

        $dead = WorkerIdentity::deadLocalIds();
        if ($dead !== []) {
            $clauses[] = $this->adapter->quoteInto('claimed_by IN (?)', $dead);
        }

        $timer = $this->adapter->quoteInto('claimed_at < ?', $this->staleClaimCutoff());
        $local = WorkerIdentity::localIds();
        if ($local !== []) {
            $timer .= ' AND (claimed_by IS NULL OR '
                . $this->adapter->quoteInto('claimed_by NOT IN (?)', $local) . ')';
        }
        $clauses[] = $timer;

        // Wrapped whole: the caller ANDs this with a status and a queue filter.
        return '((' . implode(') OR (', $clauses) . '))';
    }

    private function staleClaimCutoff(): string
    {
        return gmdate(\Mage_Core_Model_Locale::DATETIME_FORMAT, time() - $this->redeliverAfterSeconds);
    }

    private function inFlightRowExists(string $dedupeKey): bool
    {
        $existing = $this->adapter->fetchOne(
            $this->adapter->select()
                ->from($this->table, 'message_id')
                ->where('dedupe_key = ?', $dedupeKey)
                ->where('status IN (?)', [self::STATUS_PENDING, self::STATUS_PROCESSING])
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
