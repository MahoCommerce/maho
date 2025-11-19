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
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'email:queue:clear',
    description: 'Clear emails from the queue',
)]
class EmailQueueClear extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addOption('status', 's', InputOption::VALUE_OPTIONAL, 'Clear only emails with specific status (pending|processed|all)', 'all');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompt');
        $this->addOption('older-than', null, InputOption::VALUE_OPTIONAL, 'Clear only emails older than X days');

        $this->setHelp(
            'This command clears emails from the queue based on status and age criteria.

<info>Usage examples:</info>
  Clear all emails (default):
    <comment>./maho email:queue:clear</comment>

  Clear all emails without confirmation:
    <comment>./maho email:queue:clear --force</comment>

  Clear only pending emails:
    <comment>./maho email:queue:clear --status=pending</comment>

  Clear processed emails older than 7 days:
    <comment>./maho email:queue:clear --status=processed --older-than=7</comment>

<info>Options:</info>
  <comment>--status (-s)</comment>     Filter by email status:
                    - all: Both pending and processed emails (default)
                    - pending: Emails not yet sent
                    - processed: Emails already sent

  <comment>--force (-f)</comment>      Skip the confirmation prompt

  <comment>--older-than</comment>     Only clear emails older than X days
                    Can be combined with any status filter',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $status = $input->getOption('status');
        $force = $input->getOption('force');
        $olderThan = $input->getOption('older-than');

        // Get the collection based on status
        $collection = Mage::getResourceModel('core/email_queue_collection');

        switch ($status) {
            case 'pending':
                $collection->addFieldToFilter('processed_at', ['null' => true]);
                $statusLabel = 'pending';
                break;
            case 'processed':
                $collection->addFieldToFilter('processed_at', ['notnull' => true]);
                $statusLabel = 'processed';
                break;
            case 'all':
                $statusLabel = 'all';
                break;
            default:
                $output->writeln('<error>Invalid status. Use: pending, processed, or all</error>');
                return Command::FAILURE;
        }

        // Apply age filter if specified
        if ($olderThan) {
            $date = new \DateTime();
            $date->modify("-{$olderThan} days");
            $collection->addFieldToFilter('created_at', ['lt' => $date->format(\Mage_Core_Model_Locale::DATETIME_FORMAT)]);
            $statusLabel .= " (older than {$olderThan} days)";
        }

        $count = $collection->getSize();

        if ($count === 0) {
            if ($statusLabel === 'all') {
                $output->writeln('<info>No emails found in queue.</info>');
            } else {
                $output->writeln("<info>No {$statusLabel} emails found in queue.</info>");
            }
            return Command::SUCCESS;
        }

        // Show what will be deleted
        if ($statusLabel === 'all') {
            $output->writeln("<comment>Found {$count} emails to clear:</comment>");
        } else {
            $output->writeln("<comment>Found {$count} {$statusLabel} emails to clear:</comment>");
        }

        // Show a sample of emails to be deleted
        $sample = clone $collection;
        $sample->setPageSize(5)->setCurPage(1);

        foreach ($sample as $message) {
            $parameters = $message->getMessageParameters();
            if (is_string($parameters)) {
                $parameters = json_decode($parameters, true);
            }
            $recipients = $message->getRecipients();
            $toEmails = array_filter($recipients, fn($r) => $r[2] == 0);

            $output->writeln(sprintf(
                '  - [%s] %s to %s',
                $message->getCreatedAt(),
                $parameters['subject'] ?? 'No subject',
                implode(', ', array_column($toEmails, 0)),
            ));
        }

        if ($count > 5) {
            $output->writeln('  ... and ' . ($count - 5) . ' more');
        }

        // Confirm deletion
        if (!$force) {
            /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "\n<question>Are you sure you want to delete these {$count} emails? [y/N]</question> ",
                false,
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Operation cancelled.</comment>');
                return Command::SUCCESS;
            }
        }

        // Delete the emails
        $output->write("Deleting {$count} emails...");

        try {
            foreach ($collection as $message) {
                $message->delete();
            }

            $output->writeln(' <info>✓</info>');
            $output->writeln("<info>Successfully deleted {$count} emails from the queue.</info>");

            // If we deleted processed emails, suggest running cleanup
            if ($status === 'processed' || $status === 'all') {
                $output->writeln('');
                $output->writeln('Note: The cron job <comment>core_email_queue_clean_up</comment> normally handles cleanup of processed emails.');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln(' <error>✗</error>');
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}
