<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Maho\Db\Expr;
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

        $transportName = QueueManager::transportName();
        $output->writeln("<info>Active transport: {$transportName}</info>");
        if ($transportName === QueueManager::TRANSPORT_REDIS) {
            $output->writeln('<comment>Pending messages live in Redis; the counts below only cover messages stored in the database (failures).</comment>');
        }

        if ($queues === []) {
            $output->writeln('The queue is empty.');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Queue', 'Pending', 'Processing', 'Failed', 'Completed', 'Oldest pending (UTC)']);
        foreach ($queues as $queue => $counts) {
            $table->addRow([
                $queue,
                $counts[DbTransport::STATUS_PENDING],
                $counts[DbTransport::STATUS_PROCESSING],
                $counts[DbTransport::STATUS_FAILED],
                $counts[DbTransport::STATUS_COMPLETED],
                $counts['oldest_pending'] ?? '-',
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
