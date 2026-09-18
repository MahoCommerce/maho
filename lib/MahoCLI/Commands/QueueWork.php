<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Queue\Pool;
use Maho\Queue\PoolRegistry;
use Maho\Queue\Transport\DbTransport;
use Maho\Queue\WorkerFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SignalRegistry\SignalRegistry;
use Symfony\Component\Messenger\Worker;

#[AsCommand(
    name: 'queue:work',
    description: 'Consume messages from the queue (long-running worker; cron keeps one running automatically)',
)]
class QueueWork extends BaseMahoCommand implements SignalableCommandInterface
{
    private ?Worker $worker = null;

    // TraceMessageListener traces each message instead
    #[\Override]
    protected bool $traceWholeCommand = false;

    public function __invoke(
        OutputInterface $output,
        #[Option(description: 'Consume as this configured worker pool, taking its queues and limits as defaults', name: 'pool')]
        ?string $poolName = null,
        #[Option(description: 'Which worker of the pool this process is, when the pool runs more than one')]
        int $index = 0,
        #[Option(description: 'Only consume these queues (repeatable); default all', name: 'queue')]
        array $queues = [],
        #[Option(description: 'Never consume these queues (repeatable); how a catch-all worker leaves another pool its own')]
        array $excludeQueue = [],
        #[Option(description: 'Stop after handling this many messages')]
        ?int $limit = null,
        #[Option(description: 'Stop after this many seconds')]
        ?int $timeLimit = null,
        #[Option(description: 'Stop once memory usage exceeds this limit (e.g. 256M)')]
        ?string $memoryLimit = null,
        #[Option(description: 'Seconds to sleep when the queue is empty')]
        int $sleep = 1,
        #[Option(description: 'Stop after this many seconds with nothing to do; 0 stops on the first empty poll')]
        ?int $idleTimeout = null,
        #[Option(description: 'Stop as soon as the queue is empty (same as --idle-timeout=0)')]
        bool $stopWhenEmpty = false,
        #[Option(description: 'Hold the pool worker lock and refuse to run when another exclusive worker holds it (used by the cron watchdog)')]
        bool $exclusive = false,
    ): int {
        $this->initMaho();

        $pool = null;
        if ($poolName !== null) {
            $pool = PoolRegistry::get($poolName);
            if ($pool === null) {
                $output->writeln("<error>Unknown queue pool: {$poolName}</error>");
                return Command::INVALID;
            }
        }

        if ($pool !== null && ($index < 0 || $index >= $pool->count)) {
            // Out of range takes a lock the watchdog never probes, so it would
            // spawn a duplicate worker for the index this one is impersonating.
            $output->writeln("<error>Pool {$pool->name} runs {$pool->count} worker(s); --index must be 0.." . ($pool->count - 1) . '</error>');
            return Command::INVALID;
        }

        if ($exclusive) {
            // The exclusive lock tells the watchdog this process covers the whole
            // roster (or a whole pool); a queue filter would make that a lie and
            // silently starve every queue the filter leaves out.
            if ($queues !== [] || $excludeQueue !== []) {
                $output->writeln('<error>--exclusive cannot be combined with --queue or --exclude-queue: the lock claims coverage the filter takes away</error>');
                return Command::INVALID;
            }
            $lockName = $pool?->lockName($index) ?? Pool::LOCK_PREFIX;
            if (!\Mage::getSingleton('core/lock')->acquire($lockName, machineLocal: true)) {
                $output->writeln("<error>Another exclusive queue worker already holds {$lockName}</error>");
                return Command::INVALID;
            }
            if ($pool === null && ($running = $this->livePoolWorkers()) !== []) {
                // The bare lock only stops the watchdog from spawning more; it
                // does not evict workers already running.
                $output->writeln(sprintf(
                    '<comment>Pool worker(s) %s are still running and keep consuming until their own limits stop them</comment>',
                    implode(', ', $running),
                ));
            }
        }

        // Unbounded unless asked: a hand-run worker keeps the limits it had before pools existed.
        $base = $pool ?? new Pool(name: 'ad-hoc', memoryLimit: '', timeLimit: 0);
        $effective = new Pool(
            name: $base->name,
            queues: $queues ?: $base->queues,
            // An explicit allow-list already narrows the worker, and keeping the
            // pool's "everything but" list on top of it would leave
            // `--pool=slow --queue=email` consuming nothing at all. Without an
            // allow-list the pool's own exclusions stay: they are the catch-all's
            // isolation boundary, and an extra --exclude-queue must not erase it.
            excludedQueues: $queues
                ? $excludeQueue
                : array_values(array_unique(array_merge($base->excludedQueues, $excludeQueue))),
            idleTimeout: match (true) {
                $idleTimeout !== null => max(0, $idleTimeout),
                $stopWhenEmpty => 0,
                default => $base->idleTimeout,
            },
            memoryLimit: $memoryLimit ?? $base->memoryLimit,
            timeLimit: $timeLimit ?? $base->timeLimit,
        );

        $memoryLimitBytes = null;
        if ($effective->memoryLimit !== '') {
            $memoryLimitBytes = Pool::parseMemoryLimit($effective->memoryLimit);
            if ($memoryLimitBytes === null) {
                $output->writeln("<error>Invalid memory limit: {$effective->memoryLimit}</error>");
                return Command::INVALID;
            }
        }

        $this->worker = WorkerFactory::create([
            'limit' => $limit,
            'memoryLimit' => $memoryLimitBytes,
            'idleTimeout' => $effective->idleTimeout,
            'pool' => $effective,
        ]);

        $output->writeln(sprintf(
            '<info>Consuming messages%s%s%s (press Ctrl-C to stop gracefully)</info>',
            $pool !== null ? ', pool: ' . $pool->name : '',
            $effective->queues !== [] ? ', queues: ' . implode(', ', $effective->queues) : '',
            $effective->excludedQueues !== [] ? ', excluding: ' . implode(', ', $effective->excludedQueues) : '',
        ));

        $options = ['sleep' => $sleep * 1_000_000];
        if ($effective->timeLimit > 0) {
            $options['time_limit'] = $effective->timeLimit;
        }
        if ($effective->queues !== []) {
            $options['queues'] = $effective->queues;
        }

        // Drives keepalive(): without it a slow handler is indistinguishable from a dead worker.
        if (SignalRegistry::isSupported()) {
            $this->getApplication()?->setAlarmInterval(DbTransport::KEEPALIVE_INTERVAL_SECONDS);
        } else {
            $output->writeln(sprintf(
                '<comment>pcntl is unavailable, so claims are not refreshed while a handler runs: a handler longer than %d seconds is reported as abandoned even though its worker is alive</comment>',
                DbTransport::ABANDONED_AFTER_SECONDS,
            ));
        }

        try {
            $this->worker->run($options);
        } finally {
            // The recurring alarm outlives run(): the command's return pops the
            // SIGALRM handler back to SIG_DFL while an alarm is still pending,
            // and SIG_DFL terminates the process. Harmless for a detached worker
            // about to exit, fatal for a parent command (email:queue:process)
            // that invoked queue:work in-process and still has work to do.
            if (SignalRegistry::isSupported()) {
                $this->getApplication()?->setAlarmInterval(null);
                pcntl_alarm(0);
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<int>
     */
    #[\Override]
    public function getSubscribedSignals(): array
    {
        return SignalRegistry::isSupported() ? [\SIGTERM, \SIGINT, \SIGALRM] : [];
    }

    #[\Override]
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        if ($signal === \SIGALRM) {
            try {
                $this->worker?->keepalive($this->getApplication()?->getAlarmInterval());
            } catch (\Exception $e) {
                // The alarm lands mid-handler, so an escaping error would unwind it half-done.
                \Mage::log('Queue worker could not refresh its claim: ' . $e->getMessage(), \Mage::LOG_WARNING);
            }

            return false;
        }

        // Finish the in-flight message, then exit cleanly.
        $this->worker?->stop();

        return false;
    }

    /**
     * @return list<string>
     */
    private function livePoolWorkers(): array
    {
        $lock = \Mage::getSingleton('core/lock');
        $running = [];
        foreach (PoolRegistry::all() as $pool) {
            for ($index = 0; $index < $pool->count; $index++) {
                if ($lock->isHeld($pool->lockName($index), machineLocal: true)) {
                    $running[] = "{$pool->name}.{$index}";
                }
            }
        }

        return $running;
    }
}
