<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue;

/**
 * A worker pool: one or more `queue:work` processes consuming a subset of the
 * logical queues, with their own limits. Pools keep
 * latency classes apart, so a ten-minute feed build cannot sit in front of an
 * order confirmation email.
 */
final readonly class Pool
{
    public const LOCK_PREFIX = 'queue.worker';

    /**
     * @param list<string> $queues         Consume only these queues; empty consumes every queue not excluded
     * @param list<string> $excludedQueues Never consume these; the catch-all pool excludes every other pool's queues
     * @param ?int         $idleTimeout    Seconds of continuous idleness before exiting; null keeps the worker resident
     */
    public function __construct(
        public string $name,
        public array $queues = [],
        public array $excludedQueues = [],
        public int $count = 1,
        public ?int $idleTimeout = null,
        public string $memoryLimit = '256M',
        public int $timeLimit = 3600,
    ) {}

    /**
     * On-demand pools exit once idle, so the watchdog only starts them when
     * their queues have work due.
     */
    public function isOnDemand(): bool
    {
        return $this->idleTimeout !== null;
    }

    /** Taken machine-local: the flock dies with the process and doubles as the liveness probe. */
    public function lockName(int $index = 0): string
    {
        return self::LOCK_PREFIX . '.' . $this->name . '.' . $index;
    }

    public function consumes(string $queue): bool
    {
        if (in_array($queue, $this->excludedQueues, true)) {
            return false;
        }

        return $this->queues === [] || in_array($queue, $this->queues, true);
    }
}
