<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Queue\QueueManager;
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
            ->addOption('queue', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only consume these queues (repeatable); default all')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Stop after handling this many messages')
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Stop after this many seconds')
            ->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Stop once memory usage exceeds this limit (e.g. 256M)')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Seconds to sleep when the queue is empty', '1')
            ->addOption('stop-when-empty', null, InputOption::VALUE_NONE, 'Stop as soon as the queue is empty')
            ->addOption('exclusive', null, InputOption::VALUE_NONE, 'Hold the queue.worker lock and refuse to run when another exclusive worker is active (used by the cron watchdog)');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        if ($input->getOption('exclusive')
            && !\Mage::getSingleton('core/lock')->acquire(\Maho_Queue_Model_Cron::WORKER_LOCK, machineLocal: true)
        ) {
            $output->writeln('<error>Another exclusive queue worker is already running</error>');
            return Command::INVALID;
        }

        $memoryLimit = null;
        $memoryLimitOption = $input->getOption('memory-limit');
        if ($memoryLimitOption !== null) {
            $memoryLimit = $this->parseMemoryLimit((string) $memoryLimitOption);
            if ($memoryLimit === null) {
                $output->writeln("<error>Invalid memory limit: {$memoryLimitOption}</error>");
                return Command::INVALID;
            }
        }

        $this->worker = WorkerFactory::create([
            'limit' => $input->getOption('limit') !== null ? (int) $input->getOption('limit') : null,
            'memoryLimit' => $memoryLimit,
            'stopWhenIdle' => (bool) $input->getOption('stop-when-empty'),
        ]);

        $queues = $input->getOption('queue');
        $output->writeln(sprintf(
            '<info>Consuming messages from the %s transport%s (press Ctrl-C to stop gracefully)</info>',
            QueueManager::transportName(),
            $queues !== [] ? ', queues: ' . implode(', ', $queues) : '',
        ));

        $options = ['sleep' => (int) $input->getOption('sleep') * 1_000_000];
        if ($input->getOption('time-limit') !== null) {
            $options['time_limit'] = (int) $input->getOption('time-limit');
        }
        if ($queues !== []) {
            $options['queues'] = $queues;
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
