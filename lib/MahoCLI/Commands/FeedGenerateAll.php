<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Maho_FeedManager_Model_Log;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'feed:generate:all',
    description: 'Generate all enabled feeds',
)]
class FeedGenerateAll extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addOption('include-disabled', null, InputOption::VALUE_NONE, 'Also generate disabled feeds');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $collection = Mage::getModel('feedmanager/feed')->getCollection();

        if (!$input->getOption('include-disabled')) {
            $collection->addFieldToFilter('is_enabled', 1);
        }

        if ($collection->getSize() === 0) {
            $output->writeln('<comment>No feeds to generate.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('Generating <info>%d</info> feed(s)...', $collection->getSize()));
        $output->writeln('');

        $totalStartTime = microtime(true);
        $successCount = 0;
        $failureCount = 0;

        foreach ($collection as $feed) {
            $output->write(sprintf('  [%d] %s... ', $feed->getId(), $feed->getName()));
            $startTime = microtime(true);

            try {
                $generator = new \Maho_FeedManager_Model_Generator();
                $log = $generator->generate($feed);

                $duration = round(microtime(true) - $startTime, 2);

                if ($log->getStatus() === Maho_FeedManager_Model_Log::STATUS_COMPLETED) {
                    $output->writeln(sprintf(
                        '<info>done!</info> (%d products, %s, %.2fs)',
                        $log->getProductCount(),
                        $this->humanReadableSize((int) $feed->getLastFileSize()),
                        $duration,
                    ));
                    $successCount++;
                } else {
                    $output->writeln('<error>failed!</error>');
                    $errors = $log->getErrorMessagesArray();
                    if (!empty($errors)) {
                        foreach ($errors as $error) {
                            $output->writeln('      - ' . $error);
                        }
                    }
                    $failureCount++;
                }
            } catch (\Exception $e) {
                $output->writeln('<error>error!</error>');
                $output->writeln('      - ' . $e->getMessage());
                $failureCount++;
            }
        }

        $totalDuration = round(microtime(true) - $totalStartTime, 2);
        $output->writeln('');

        if ($failureCount === 0) {
            $output->writeln(sprintf('<info>All %d feeds generated successfully in %.2fs</info>', $successCount, $totalDuration));
        } else {
            $output->writeln(sprintf(
                '<comment>Completed: %d successful, %d failed (%.2fs)</comment>',
                $successCount,
                $failureCount,
                $totalDuration,
            ));
        }

        return $failureCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
