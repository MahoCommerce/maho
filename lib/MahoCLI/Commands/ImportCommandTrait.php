<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Import\ImporterInterface;
use Maho\Import\Reporter;
use Maho\Import\RowException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

trait ImportCommandTrait
{
    protected function addDryRunOption(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Validate the file and write nothing');
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function runImport(ImporterInterface $importer, string $csv, array $options, bool $dryRun, OutputInterface $output): int
    {
        try {
            if ($dryRun) {
                $importer->validate($csv, $options);
                $output->writeln('<info>' . basename($csv) . ' is valid, nothing written</info>');
                return Command::SUCCESS;
            }
            $result = $importer->import($csv, $options, $this->consoleReporter($output));
        } catch (RowException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        foreach ($result->notices as $notice) {
            $output->writeln('<comment>' . $notice . '</comment>');
        }
        $output->writeln('<info>' . basename($csv) . ': ' . $result->summary() . '</info>');
        return Command::SUCCESS;
    }

    protected function consoleReporter(OutputInterface $output, bool $quietProgress = true): Reporter
    {
        return new readonly class ($output, $quietProgress) implements Reporter {
            public function __construct(private OutputInterface $output, private bool $quietProgress) {}

            #[\Override]
            public function info(string $message): void
            {
                $this->output->writeln($message, OutputInterface::VERBOSITY_VERBOSE);
            }

            #[\Override]
            public function warning(string $message): void
            {
                $this->output->writeln('<comment>' . $message . '</comment>');
            }

            #[\Override]
            public function progress(int $done, int $total, string $label = ''): void
            {
                $verbosity = $this->quietProgress ? OutputInterface::VERBOSITY_VERBOSE : OutputInterface::VERBOSITY_NORMAL;
                $this->output->writeln(sprintf('[%d/%d] %s', $done, $total, $label), $verbosity);
            }
        };
    }
}
