<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Maho_Queue_Model_Message;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'email:queue:clear',
    description: 'Clear email messages from the queue',
)]
class EmailQueueClear extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addOption('status', 's', InputOption::VALUE_OPTIONAL, 'Clear only emails with specific status (pending|failed|completed|all)', 'all');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompt');
        $this->addOption('older-than', null, InputOption::VALUE_OPTIONAL, 'Clear only emails older than X days');

        $this->setHelp(
            'This command clears email messages from the queue based on status and age criteria.

<info>Usage examples:</info>
  Clear all email messages (default):
    <comment>./maho email:queue:clear</comment>

  Clear all email messages without confirmation:
    <comment>./maho email:queue:clear --force</comment>

  Clear only pending email messages:
    <comment>./maho email:queue:clear --status=pending</comment>

  Clear failed email messages older than 7 days:
    <comment>./maho email:queue:clear --status=failed --older-than=7</comment>',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $status = $input->getOption('status');
        $force = $input->getOption('force');
        $olderThan = $input->getOption('older-than');

        $collection = Mage::getModel('queue/message')->getCollection()
            ->addFieldToFilter('queue', \Mage_Core_Model_Email_Queue::QUEUE_NAME);

        $validStatuses = [
            Maho_Queue_Model_Message::STATUS_PENDING,
            Maho_Queue_Model_Message::STATUS_FAILED,
            Maho_Queue_Model_Message::STATUS_COMPLETED,
        ];
        $statusLabel = $status;
        if (in_array($status, $validStatuses, true)) {
            $collection->addFieldToFilter('status', $status);
        } elseif ($status !== 'all') {
            $output->writeln('<error>Invalid status. Use: pending, failed, completed, or all</error>');
            return Command::FAILURE;
        }

        if ($olderThan) {
            $collection->addFieldToFilter('created_at', [
                'lt' => gmdate(\Mage_Core_Model_Locale::DATETIME_FORMAT, time() - (int) $olderThan * 86400),
            ]);
            $statusLabel .= " (older than {$olderThan} days)";
        }

        $count = $collection->getSize();
        if ($count === 0) {
            $output->writeln("<info>No {$statusLabel} email messages found in queue.</info>");
            return Command::SUCCESS;
        }

        $output->writeln("<comment>Found {$count} {$statusLabel} email message(s) to clear:</comment>");

        $sample = clone $collection;
        $sample->setPageSize(5)->setCurPage(1);
        foreach ($sample as $message) {
            $output->writeln(sprintf(
                '  - [%s] #%d %s (%s)',
                $message->getCreatedAt(),
                $message->getId(),
                $message->getMessageClass(),
                $message->getStatus(),
            ));
        }
        if ($count > 5) {
            $output->writeln('  ... and ' . ($count - 5) . ' more');
        }

        if (!$force) {
            /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "\n<question>Are you sure you want to delete these {$count} email message(s)? [y/N]</question> ",
                false,
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Operation cancelled.</comment>');
                return Command::SUCCESS;
            }
        }

        $deleted = 0;
        foreach ($collection as $message) {
            $message->delete();
            $deleted++;
        }

        $output->writeln("<info>Deleted {$deleted} email message(s) from the queue.</info>");

        return Command::SUCCESS;
    }
}
