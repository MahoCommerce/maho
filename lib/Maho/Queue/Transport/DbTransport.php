<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue\Transport;

use Maho\Db\Adapter\AdapterInterface;
use Maho\Queue\Stamp\ClaimTokenStamp;
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
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
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
final class DbTransport implements TransportInterface, QueueReceiverInterface, ListableReceiverInterface, MessageCountAwareInterface, KeepaliveReceiverInterface
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_FAILED = 'failed';
    public const STATUS_COMPLETED = 'completed';

    public const DEFAULT_QUEUE = 'default';

    /** How often a worker refreshes the claim of the message it is handling. */
    public const KEEPALIVE_INTERVAL_SECONDS = 5;

    /**
     * Sixty missed refreshes: a claim this stale belongs to a worker that died.
     * Gates the admin notice, the admin retry and the dedupe check; nothing
     * redelivers on it.
     */
    public const ABANDONED_AFTER_SECONDS = 300;

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
            // The ownership guard mirrors ack(): a row an operator already re-queued,
            // completed, deleted or that another worker has since claimed must
            // survive a stale worker's late re-send.
            $this->adapter->update($this->table, [
                'status' => self::STATUS_PENDING,
                'retries' => $redeliveryStamp->getRetryCount(),
                'available_at' => $availableAt,
                'error_message' => $envelope->last(ErrorDetailsStamp::class)?->getExceptionMessage(),
                'claimed_at' => null,
                'claim_token' => null,
                'updated_at' => $now,
            ], $this->ownershipWhere($envelope));

            return $envelope;
        }

        $dedupeStamp = $envelope->last(DedupeKeyStamp::class);
        // Failure sends are never deduped: a live chain must not swallow the
        // error row. Check-then-insert is racy, so dispatchers of one key
        // serialize on a lock (db lock backend required on multi-server).
        if ($dedupeStamp?->enforce === true
            && $envelope->last(SentToFailureTransportStamp::class) === null) {
            $lock = \Mage::getSingleton('core/lock');
            $lockName = 'queue_dedupe_' . $dedupeStamp->key;
            $locked = $lock->acquire($lockName, blocking: true);
            try {
                if ($this->inFlightRowExists($dedupeStamp->key)) {
                    return $envelope;
                }
                return $this->insertEnvelope($envelope, $availableAt, $now);
            } finally {
                if ($locked) {
                    $lock->release($lockName);
                }
            }
        }

        return $this->insertEnvelope($envelope, $availableAt, $now);
    }

    private function insertEnvelope(Envelope $envelope, string $availableAt, string $now): Envelope
    {
        $encoded = $this->serializer->encode($envelope);
        $isFailure = $envelope->last(SentToFailureTransportStamp::class) !== null;

        $this->adapter->insert($this->table, [
            'queue' => $envelope->last(QueueNameStamp::class)->queue ?? self::DEFAULT_QUEUE,
            'status' => $isFailure ? self::STATUS_FAILED : self::STATUS_PENDING,
            'message_class' => $encoded['headers']['type'],
            'body' => $encoded['body'],
            'error_message' => $isFailure ? $envelope->last(ErrorDetailsStamp::class)?->getExceptionMessage() : null,
            'retries' => (int) ($encoded['headers']['retries'] ?? 0),
            'trace_context' => $encoded['headers']['trace_context'] ?? null,
            'dedupe_key' => $envelope->last(DedupeKeyStamp::class)?->key,
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

    /**
     * The ownership guard mirrors reject() and keepalive(): a row an operator
     * already flipped back to pending, or that another worker has since
     * claimed, must survive the late ack of the worker that used to hold it,
     * or the retry (or the other worker's run) is silently swallowed.
     */
    #[\Override]
    public function ack(Envelope $envelope): void
    {
        $where = $this->ownershipWhere($envelope);
        if ($this->completedRetentionDays > 0) {
            $now = \Mage_Core_Model_Locale::nowUtc();
            $this->adapter->update($this->table, [
                'status' => self::STATUS_COMPLETED,
                'claim_token' => null,
                'processed_at' => $now,
                'updated_at' => $now,
            ], $where);
        } else {
            $this->adapter->delete($this->table, $where);
        }
    }

    #[\Override]
    public function reject(Envelope $envelope): void
    {
        $now = \Mage_Core_Model_Locale::nowUtc();
        // A row the retry listener already re-queued in place stays pending.
        $this->adapter->update($this->table, [
            'status' => self::STATUS_FAILED,
            'claim_token' => null,
            'error_message' => $envelope->last(ErrorDetailsStamp::class)?->getExceptionMessage(),
            'processed_at' => $now,
            'updated_at' => $now,
        ], $this->ownershipWhere($envelope));
    }

    /**
     * Makes claimed_at mean "a worker is alive here", not "a worker started here
     * a while ago". The ownership guard leaves a row an operator already retried
     * (or another worker has since claimed) alone.
     */
    #[\Override]
    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        // The alarm can fire while the handler holds an open transaction on this
        // shared connection; joining it would lock the row against the admin's
        // retry and lose the refresh on rollback. Skip and let the next one land.
        if ($this->adapter->getTransactionLevel() > 0) {
            return;
        }

        $now = \Mage_Core_Model_Locale::nowUtc();
        $this->adapter->update($this->table, [
            'claimed_at' => $now,
            'updated_at' => $now,
        ], $this->ownershipWhere($envelope));
    }

    /**
     * WHERE clause proving the caller still owns the row: the claim token
     * written when it claimed, when the envelope carries one. Status alone
     * cannot tell "my claim" from a newer claim another worker took after an
     * operator retried this one.
     *
     * @return array<string, mixed>
     */
    private function ownershipWhere(Envelope $envelope): array
    {
        $where = [
            'message_id = ?' => $this->messageId($envelope),
            'status = ?' => self::STATUS_PROCESSING,
        ];

        $token = $envelope->last(ClaimTokenStamp::class)?->token;
        if ($token !== null) {
            $where['claim_token = ?'] = $token;
        }

        return $where;
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
        return $this->countRows(['status = ?' => self::STATUS_PENDING]);
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
        return $this->countRows([
            'status = ?' => self::STATUS_PENDING,
            'available_at <= ?' => \Mage_Core_Model_Locale::nowUtc(),
        ], $queues);
    }

    /** Drives the admin notice. A working worker refreshes its claim, so this counts dead ones. */
    public function countAbandoned(int $olderThanSeconds = self::ABANDONED_AFTER_SECONDS): int
    {
        return $this->countRows([
            'status = ?' => self::STATUS_PROCESSING,
            'claimed_at < ?' => self::abandonedBefore($olderThanSeconds),
        ]);
    }

    /**
     * Live claims, so the watchdog can tell a pool's busy workers from its idle ones.
     *
     * @param list<string>|null $queues
     */
    public function countClaimed(?array $queues = null): int
    {
        return $this->countRows([
            'status = ?' => self::STATUS_PROCESSING,
            'claimed_at >= ?' => self::abandonedBefore(),
        ], $queues);
    }

    /**
     * @param array<string, mixed>  $conditions
     * @param list<string>|null     $queues
     */
    private function countRows(array $conditions, ?array $queues = null): int
    {
        $select = $this->adapter->select()->from($this->table, new \Maho\Db\Expr('COUNT(*)'));
        foreach ($conditions as $condition => $value) {
            $select->where($condition, $value);
        }
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

            $token = bin2hex(random_bytes(16));
            $claimed = $this->adapter->update($this->table, [
                'status' => self::STATUS_PROCESSING,
                'claimed_at' => $now,
                'claim_token' => $token,
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
                return [$envelope->with(new ClaimTokenStamp($token))];
            }
        }

        return [];
    }

    /**
     * A pending or freshly claimed row already carries this dedupe key: gates
     * both new dispatches and the admin retry of an older copy.
     */
    public function inFlightRowExists(string $dedupeKey, ?int $excludeMessageId = null): bool
    {
        // A claim a dead worker left behind waits for an operator forever, so it
        // must not keep suppressing new dispatches of the same key: that would
        // silently drop every later send instead of parking one message.
        // Pending rows always have a null claimed_at, so they are never excluded.
        $select = $this->adapter->select()
            ->from($this->table, 'message_id')
            ->where('dedupe_key = ?', $dedupeKey)
            ->where('status IN (?)', [self::STATUS_PENDING, self::STATUS_PROCESSING])
            ->where('claimed_at IS NULL OR claimed_at >= ?', self::abandonedBefore())
            ->limit(1);
        if ($excludeMessageId !== null) {
            $select->where('message_id != ?', $excludeMessageId);
        }
        $existing = $this->adapter->fetchOne($select);

        return $existing !== false && $existing !== null;
    }

    /** UTC cut-off before which a claim counts as abandoned. */
    public static function abandonedBefore(int $olderThanSeconds = self::ABANDONED_AFTER_SECONDS): string
    {
        return gmdate(\Mage_Core_Model_Locale::DATETIME_FORMAT, time() - $olderThanSeconds);
    }

    /** True when a processing row's claim is old enough to belong to a dead worker. */
    public static function isAbandonedClaim(?string $claimedAt): bool
    {
        return $claimedAt !== null && $claimedAt < self::abandonedBefore();
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
                    'trace_context' => (string) ($row['trace_context'] ?? ''),
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
                    'trace_context' => (string) ($row['trace_context'] ?? ''),
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
