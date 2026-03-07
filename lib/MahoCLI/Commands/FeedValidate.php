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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'feed:validate',
    description: 'Validate an existing feed file without regenerating',
)]
class FeedValidate extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('feed_id', InputArgument::REQUIRED, 'The ID of the feed to validate');
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

        $output->writeln(sprintf('Validating feed: <info>%s</info> (ID: %d)', $feed->getName(), $feed->getId()));

        // Get the feed file path
        $outputDir = Mage::helper('feedmanager')->getOutputDirectory();
        $filename = $feed->getFilename();
        $format = $feed->getFileFormat() ?: 'xml';

        $extension = match ($format) {
            'xml' => 'xml',
            'csv' => 'csv',
            'json' => 'json',
            default => 'xml',
        };

        $filePath = $outputDir . DS . $filename . '.' . $extension;

        // Check for gzipped version
        if (!file_exists($filePath) && file_exists($filePath . '.gz')) {
            $filePath .= '.gz';
        }

        if (!file_exists($filePath)) {
            $output->writeln('<error>Feed file not found: ' . $filePath . '</error>');
            $output->writeln('<comment>Generate the feed first using: ./maho feed:generate ' . $feedId . '</comment>');
            return Command::FAILURE;
        }

        $output->writeln('File: ' . $filePath);
        $output->writeln('Size: ' . $this->humanReadableSize((int) filesize($filePath)));
        $output->writeln('');

        // Validate the file
        $validator = new \Maho_FeedManager_Model_Validator();
        $isValid = $validator->validate($filePath, $format);

        $errors = $validator->getErrors();
        $warnings = $validator->getWarnings();

        if ($isValid && empty($errors)) {
            $output->writeln('<info>Feed is valid!</info>');

            if (!empty($warnings)) {
                $output->writeln('');
                $output->writeln('<comment>Warnings:</comment>');
                foreach ($warnings as $warning) {
                    $output->writeln('  - ' . $warning);
                }
            }

            return Command::SUCCESS;
        }
        $output->writeln('<error>Feed validation failed!</error>');
        $output->writeln('');
        if (!empty($errors)) {
            $output->writeln('<error>Errors:</error>');
            foreach ($errors as $error) {
                $output->writeln('  - ' . $error);
            }
        }
        if (!empty($warnings)) {
            $output->writeln('');
            $output->writeln('<comment>Warnings:</comment>');
            foreach ($warnings as $warning) {
                $output->writeln('  - ' . $warning);
            }
        }
        return Command::FAILURE;
    }
}
