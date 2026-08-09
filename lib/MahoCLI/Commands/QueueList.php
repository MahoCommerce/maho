<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Maho\Db\Expr;
use Maho\Queue\PoolRegistry;
use Maho\Queue\QueueManager;
use Maho\Queue\Transport\DbTransport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'queue:list',
    description: 'Show per-queue message counts and the active transport',
)]
class QueueList extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = QueueManager::tableName();

        $rows = $adapter->fetchAll(
            $adapter->select()
                ->from($tableName, ['queue', 'status', 'total' => new Expr('COUNT(*)'), 'oldest' => new Expr('MIN(available_at)')])
                ->group(['queue', 'status']),
        );

        $queues = [];
        foreach ($rows as $row) {
            $queue = (string) $row['queue'];
            $queues[$queue] ??= [
                DbTransport::STATUS_PENDING => 0,
                DbTransport::STATUS_PROCESSING => 0,
                DbTransport::STATUS_FAILED => 0,
                DbTransport::STATUS_COMPLETED => 0,
                'oldest_pending' => null,
            ];
            $queues[$queue][(string) $row['status']] = (int) $row['total'];
            if ($row['status'] === DbTransport::STATUS_PENDING) {
                $queues[$queue]['oldest_pending'] = (string) $row['oldest'];
            }
        }
        ksort($queues);

        if ($queues === []) {
            $output->writeln('The queue is empty.');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Queue', 'Pool', 'Pending', 'Processing', 'Failed', 'Completed', 'Oldest pending (UTC)']);
        $orphaned = false;
        foreach ($queues as $queue => $counts) {
            $pool = PoolRegistry::poolFor((string) $queue);
            $orphaned = $orphaned || $pool === null;
            $table->addRow([
                $queue,
                $pool === null ? '<error>none</error>' : $pool->name,
                $counts[DbTransport::STATUS_PENDING],
                $counts[DbTransport::STATUS_PROCESSING],
                $counts[DbTransport::STATUS_FAILED],
                $counts[DbTransport::STATUS_COMPLETED],
                $counts['oldest_pending'] ?? '-',
            ]);
        }
        $table->render();

        if ($orphaned) {
            $output->writeln('<error>Queues with no pool are never consumed; mark a pool catch_all under global/queue/pools or route them under global/queue/routing.</error>');
        }

        return Command::SUCCESS;
    }
}
