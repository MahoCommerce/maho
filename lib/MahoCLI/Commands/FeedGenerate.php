<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Maho_FeedManager_Model_Log;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'feed:generate',
    description: 'Generate a specific feed',
)]
class FeedGenerate extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('feed_id', InputArgument::REQUIRED, 'The ID of the feed to generate');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $feedId = (int) $input->getArgument('feed_id');
        $feed = Mage::getModel('feedmanager/feed')->load($feedId);

        if (!$feed->getId()) {
            $output->writeln('<error>Feed not found with ID: ' . $feedId . '</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('Generating feed: <info>%s</info> (ID: %d)', $feed->getName(), $feed->getId()));
        $output->writeln('Platform: ' . ($feed->getPlatform() ?: 'custom') . ', Format: ' . ($feed->getFileFormat() ?: 'xml'));

        $startTime = microtime(true);

        try {
            $generator = new \Maho_FeedManager_Model_Generator();
            $log = $generator->generate($feed);

            $duration = round(microtime(true) - $startTime, 2);

            if ($log->getStatus() === Maho_FeedManager_Model_Log::STATUS_COMPLETED) {
                $output->writeln('');
                $output->writeln(sprintf('<info>Feed generated successfully!</info>'));
                $output->writeln(sprintf('  Products: %d', $log->getProductCount()));
                $output->writeln(sprintf('  File size: %s', $this->humanReadableSize((int) $feed->getLastFileSize())));
                $output->writeln(sprintf('  Duration: %.2fs', $duration));

                // Show any warnings
                $errors = $log->getErrorMessagesArray();
                if (!empty($errors)) {
                    $output->writeln('');
                    $output->writeln('<comment>Warnings:</comment>');
                    foreach ($errors as $error) {
                        if (str_contains($error, 'Warning')) {
                            $output->writeln('  - ' . $error);
                        }
                    }
                }

                return Command::SUCCESS;
            }
            $output->writeln('');
            $output->writeln('<error>Feed generation failed!</error>');
            $errors = $log->getErrorMessagesArray();
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $output->writeln('  - ' . $error);
                }
            }
            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln('');
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
