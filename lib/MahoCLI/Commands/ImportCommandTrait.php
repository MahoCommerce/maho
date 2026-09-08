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
use Symfony\Component\Console\Helper\ProgressBar;
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

    /**
     * Steps draw a progress bar on a terminal and print one line each elsewhere; finer progress and info lines show with -v.
     */
    protected function consoleReporter(OutputInterface $output, bool $quietProgress = true): Reporter
    {
        return new class ($output, $quietProgress) implements Reporter {
            private ?ProgressBar $bar = null;

            public function __construct(private readonly OutputInterface $output, private readonly bool $quietProgress) {}

            #[\Override]
            public function info(string $message): void
            {
                $this->writeln($message, OutputInterface::VERBOSITY_VERBOSE);
            }

            #[\Override]
            public function warning(string $message): void
            {
                $this->writeln('<comment>' . $message . '</comment>', OutputInterface::VERBOSITY_NORMAL);
            }

            #[\Override]
            public function step(int $done, int $total, string $label): void
            {
                if ($this->quietProgress || !$this->output->isDecorated()) {
                    $verbosity = $this->quietProgress ? OutputInterface::VERBOSITY_VERBOSE : OutputInterface::VERBOSITY_NORMAL;
                    $this->output->writeln(sprintf('[%d/%d] %s', $done, $total, $label), $verbosity);
                    return;
                }
                if ($this->bar === null) {
                    $this->bar = new ProgressBar($this->output, $total);
                    $this->bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%  %message%%detail%');
                    $this->bar->setMessage($label);
                    $this->bar->setMessage('', 'detail');
                    $this->bar->start();
                }
                $this->bar->setMaxSteps($total);
                $this->bar->setMessage($label);
                $this->bar->setMessage('', 'detail');
                $this->bar->setProgress($done - 1);
            }

            #[\Override]
            public function progress(int $done, int $total, string $label = ''): void
            {
                if ($this->bar === null) {
                    $this->writeln(sprintf('[%d/%d] %s', $done, $total, $label), OutputInterface::VERBOSITY_VERBOSE);
                    return;
                }
                $this->bar->setMessage(sprintf(' <fg=gray>%s (%d/%d)</>', mb_strimwidth($label, 0, 40, '…'), $done, $total), 'detail');
                $this->bar->display();
            }

            #[\Override]
            public function finish(): void
            {
                if ($this->bar !== null) {
                    $this->bar->setMessage('', 'detail');
                    $this->bar->finish();
                    $this->output->writeln('');
                    $this->bar = null;
                }
            }

            private function writeln(string $message, int $verbosity): void
            {
                if ($this->output->getVerbosity() < $verbosity) {
                    return;
                }
                $this->bar?->clear();
                $this->output->writeln($message);
                $this->bar?->display();
            }
        };
    }
}
