<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Queue\Pool;
use Maho\Queue\PoolRegistry;
use Maho\Queue\WorkerFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('pool', null, InputOption::VALUE_REQUIRED, 'Consume as this configured worker pool, taking its queues and limits as defaults')
            ->addOption('index', null, InputOption::VALUE_REQUIRED, 'Which worker of the pool this process is, when the pool runs more than one', '0')
            ->addOption('queue', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only consume these queues (repeatable); default all')
            ->addOption('exclude-queue', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Never consume these queues (repeatable); how a catch-all worker leaves another pool its own')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Stop after handling this many messages')
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Stop after this many seconds')
            ->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Stop once memory usage exceeds this limit (e.g. 256M)')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Seconds to sleep when the queue is empty', '1')
            ->addOption('idle-timeout', null, InputOption::VALUE_REQUIRED, 'Stop after this many seconds with nothing to do; 0 stops on the first empty poll')
            ->addOption('stop-when-empty', null, InputOption::VALUE_NONE, 'Stop as soon as the queue is empty (same as --idle-timeout=0)')
            ->addOption('exclusive', null, InputOption::VALUE_NONE, 'Hold the pool worker lock and refuse to run when another exclusive worker holds it (used by the cron watchdog)');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $pool = null;
        $poolName = $input->getOption('pool');
        if ($poolName !== null) {
            $pool = PoolRegistry::get((string) $poolName);
            if ($pool === null) {
                $output->writeln("<error>Unknown queue pool: {$poolName}</error>");
                return Command::INVALID;
            }
        }

        $index = (int) $input->getOption('index');
        if ($pool !== null && ($index < 0 || $index >= $pool->count)) {
            // Out of range takes a lock the watchdog never probes, so it would
            // spawn a duplicate worker for the index this one is impersonating.
            $output->writeln("<error>Pool {$pool->name} runs {$pool->count} worker(s); --index must be 0.." . ($pool->count - 1) . '</error>');
            return Command::INVALID;
        }

        if ($input->getOption('exclusive')) {
            $lockName = $pool?->lockName($index) ?? Pool::LOCK_PREFIX;
            if (!\Mage::getSingleton('core/lock')->acquire($lockName, machineLocal: true)) {
                $output->writeln("<error>Another exclusive queue worker already holds {$lockName}</error>");
                return Command::INVALID;
            }
        }

        // Unbounded unless asked: a hand-run worker keeps the limits it had before pools existed.
        $base = $pool ?? new Pool(name: 'ad-hoc', memoryLimit: '', timeLimit: 0);
        $effective = new Pool(
            name: $base->name,
            queues: $input->getOption('queue') ?: $base->queues,
            excludedQueues: $input->getOption('exclude-queue') ?: $base->excludedQueues,
            idleTimeout: match (true) {
                $input->getOption('idle-timeout') !== null => max(0, (int) $input->getOption('idle-timeout')),
                (bool) $input->getOption('stop-when-empty') => 0,
                default => $base->idleTimeout,
            },
            memoryLimit: (string) ($input->getOption('memory-limit') ?? $base->memoryLimit),
            timeLimit: (int) ($input->getOption('time-limit') ?? $base->timeLimit),
        );

        $memoryLimit = null;
        if ($effective->memoryLimit !== '') {
            $memoryLimit = $this->parseMemoryLimit($effective->memoryLimit);
            if ($memoryLimit === null) {
                $output->writeln("<error>Invalid memory limit: {$effective->memoryLimit}</error>");
                return Command::INVALID;
            }
        }

        $this->worker = WorkerFactory::create([
            'limit' => $input->getOption('limit') !== null ? (int) $input->getOption('limit') : null,
            'memoryLimit' => $memoryLimit,
            'idleTimeout' => $effective->idleTimeout,
            'pool' => $effective,
        ]);

        $output->writeln(sprintf(
            '<info>Consuming messages%s%s%s (press Ctrl-C to stop gracefully)</info>',
            $pool !== null ? ', pool: ' . $pool->name : '',
            $effective->queues !== [] ? ', queues: ' . implode(', ', $effective->queues) : '',
            $effective->excludedQueues !== [] ? ', excluding: ' . implode(', ', $effective->excludedQueues) : '',
        ));

        $options = ['sleep' => (int) $input->getOption('sleep') * 1_000_000];
        if ($effective->timeLimit > 0) {
            $options['time_limit'] = $effective->timeLimit;
        }
        if ($effective->queues !== []) {
            $options['queues'] = $effective->queues;
        }

        $this->worker->run($options);

        return Command::SUCCESS;
    }

    /**
     * @return list<int>
     */
    #[\Override]
    public function getSubscribedSignals(): array
    {
        return SignalRegistry::isSupported() ? [\SIGTERM, \SIGINT] : [];
    }

    #[\Override]
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        // Finish the in-flight message, then exit cleanly.
        $this->worker?->stop();

        return false;
    }

    private function parseMemoryLimit(string $limit): ?int
    {
        if (!preg_match('/^(\d+)([KMG]?)$/i', trim($limit), $matches)) {
            return null;
        }

        return (int) $matches[1] * match (strtoupper($matches[2])) {
            'K' => 1024,
            'M' => 1024 ** 2,
            'G' => 1024 ** 3,
            default => 1,
        };
    }
}
