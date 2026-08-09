<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Maho\Queue\WorkerFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'email:queue:process',
    description: 'Drain pending email messages from the queue',
)]
class EmailQueueProcess extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $dsn = Mage::helper('core')->getMailerDsn();
        if (!$dsn) {
            $output->writeln('<error>Email sending is disabled.</error>');
            return Command::FAILURE;
        }

        $pendingCount = $this->getPendingCount();
        if ($pendingCount === 0) {
            $output->writeln('<info>No emails in queue to process.</info>');
            return Command::SUCCESS;
        }
        $output->writeln("<info>Processing email queue ({$pendingCount} emails pending)...</info>");

        try {
            $worker = WorkerFactory::create(['idleTimeout' => 0]);
            $worker->run(['queues' => [\Mage_Core_Model_Email_Queue::QUEUE_NAME]]);

            $output->writeln('<info>Queue processing completed.</info>');
            $failedCount = $this->getFailedCount();
            if ($failedCount > 0) {
                $output->writeln("<comment>{$failedCount} email(s) are in failed state; inspect them in System > Tools > Message Queue or with ./maho queue:list.</comment>");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Error processing queue:</error>');
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }

    private function getPendingCount(): int
    {
        return Mage::getModel('queue/message')->getCollection()
            ->addFieldToFilter('queue', \Mage_Core_Model_Email_Queue::QUEUE_NAME)
            ->addFieldToFilter('status', \Maho_Queue_Model_Message::STATUS_PENDING)
            ->getSize();
    }

    private function getFailedCount(): int
    {
        return Mage::getModel('queue/message')->getCollection()
            ->addFieldToFilter('queue', \Mage_Core_Model_Email_Queue::QUEUE_NAME)
            ->addFieldToFilter('status', \Maho_Queue_Model_Message::STATUS_FAILED)
            ->getSize();
    }
}
