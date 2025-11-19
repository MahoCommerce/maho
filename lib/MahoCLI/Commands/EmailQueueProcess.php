<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'email:queue:process',
    description: 'Manually process the email queue',
)]
class EmailQueueProcess extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        // Get pending emails count for reporting
        $collection = Mage::getModel('core/email_queue')->getCollection()
            ->addOnlyForSendingFilter();
        $pendingCount = $collection->getSize();

        if ($pendingCount === 0) {
            $output->writeln('<info>No emails in queue to process.</info>');
            return Command::SUCCESS;
        }

        // Check if email sending is enabled
        $dsn = Mage::helper('core')->getMailerDsn();
        if (!$dsn) {
            $output->writeln('<error>Email sending is disabled.</error>');
            return Command::FAILURE;
        }

        $output->writeln("<info>Processing email queue ({$pendingCount} emails pending)...</info>");
        $output->writeln('');

        try {
            Mage::getModel('core/email_queue')->send();

            $output->writeln('<info>Queue processing completed successfully!</info>');
            $limitPerRun = \Mage_Core_Model_Email_Queue::MESSAGES_LIMIT_PER_CRON_RUN;
            $output->writeln("<comment>Note: This processes up to {$limitPerRun} emails per run (same as cron).</comment>");

            // Check remaining emails
            $remainingCollection = Mage::getModel('core/email_queue')->getCollection()
                ->addOnlyForSendingFilter();
            $remainingCount = $remainingCollection->getSize();

            if ($remainingCount > 0) {
                $output->writeln("<comment>Remaining emails in queue: {$remainingCount}</comment>");
                $output->writeln('<comment>Run the command again to process more emails.</comment>');
            } else {
                $output->writeln('<info>All emails have been processed!</info>');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('<error>Error processing queue:</error>');
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}
