<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use MahoCLI\Helper\TableBloatScanner;
use Maho\Db\Adapter\AdapterInterface;
use Maho\Db\Adapter\Pdo\Pgsql;
use Maho\Db\Adapter\Pdo\Sqlite;
use Maho\Db\Schema\Collector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'db:optimize',
    description: 'Rebuild bloated tables to return their free space to the filesystem',
)]
class DBOptimize extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addOption(
            'table',
            null,
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'Optimize this table regardless of how much space it would reclaim (repeatable)',
        );
        $this->addOption(
            'all',
            null,
            InputOption::VALUE_NONE,
            'Optimize every table, not just the ones the bloat thresholds flag',
        );
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Show the statements that would run without executing anything',
        );
        $this->addOption(
            'force',
            null,
            InputOption::VALUE_NONE,
            'Skip the confirmation prompt (for scripted maintenance windows)',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        /** @var AdapterInterface $adapter */
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');

        // Every backend's rebuild is either non-transactional or forbidden inside a
        // transaction block, so refuse rather than fail halfway through the list.
        if ($adapter->getTransactionLevel() > 0) {
            $output->writeln('<error>A transaction is open on this connection; optimization cannot run inside one.</error>');
            return Command::FAILURE;
        }

        /** @var list<string> $requested */
        $requested = $input->getOption('table');
        try {
            $targets = $this->resolveTargets($adapter, $output, $requested, (bool) $input->getOption('all'));
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::INVALID;
        }

        if ($targets === []) {
            return Command::SUCCESS;
        }

        $statements = TableBloatScanner::optimizeStatements($adapter, array_column($targets, 'table'));

        if ($input->getOption('dry-run')) {
            $output->writeln('<info>Dry run: no changes will be applied.</info>');
            foreach ($statements as $statement) {
                $output->writeln('  ' . $statement);
            }
            return Command::SUCCESS;
        }

        $this->warnAboutLocking($adapter, $output);

        if (!$input->getOption('force')) {
            /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                sprintf('<question>Run %d statement(s) now? [y/N]</question> ', count($statements)),
                false,
            );
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Aborted.');
                return Command::SUCCESS;
            }
        }

        return $this->runStatements($adapter, $output, $statements, $targets);
    }

    /**
     * The tables to rebuild, either named explicitly, taken wholesale, or found by
     * the scan. Prints its own explanation when there is nothing to do.
     *
     * @param list<string> $requested
     * @return list<array{table: string, total: int, reclaimable: int, ratio: float, detail: string}>
     * @throws \InvalidArgumentException
     */
    private function resolveTargets(AdapterInterface $adapter, OutputInterface $output, array $requested, bool $all): array
    {
        if ($requested !== [] && $all) {
            throw new \InvalidArgumentException('--table and --all are mutually exclusive.');
        }

        if ($requested !== []) {
            $missing = array_values(array_filter($requested, static fn(string $t): bool => !$adapter->isTableExists($t)));
            if ($missing !== []) {
                throw new \InvalidArgumentException('Unknown table(s): ' . implode(', ', $missing));
            }
            return self::asTargets($requested);
        }

        if ($all) {
            $prefix = Collector::tablePrefix();
            return self::asTargets(array_values(array_filter(
                $adapter->listTables(),
                static fn(string $t): bool => $prefix === '' || str_starts_with($t, $prefix),
            )));
        }

        $findings = TableBloatScanner::scan($adapter, Collector::tablePrefix());
        if ($findings === []) {
            $output->writeln('<info>Nothing to optimize: no table holds enough reclaimable space to be worth a rebuild.</info>');
            $reason = TableBloatScanner::reclaimUnavailableReason($adapter);
            if ($reason !== null) {
                $output->writeln('<comment>Note: ' . $reason . '</comment>');
            }
            $output->writeln('Use --table to rebuild a specific table anyway, or --all for the whole database.');
            return [];
        }

        $this->renderFindings($output, $findings);

        return $findings;
    }

    /**
     * Tables chosen by name rather than by the scan carry no measurement: nothing
     * claimed there would be reclaimed, and runStatements() reports what actually was.
     *
     * @param list<string> $tables
     * @return list<array{table: string, total: int, reclaimable: int, ratio: float, detail: string}>
     */
    private static function asTargets(array $tables): array
    {
        return array_map(
            static fn(string $table): array => [
                'table' => $table,
                'total' => 0,
                'reclaimable' => 0,
                'ratio' => 0.0,
                'detail' => '',
            ],
            $tables,
        );
    }

    /**
     * @param list<array{table: string, total: int, reclaimable: int, ratio: float, detail: string}> $findings
     */
    private function renderFindings(OutputInterface $output, array $findings): void
    {
        $output->writeln(sprintf('<comment>%d table(s) hold reclaimable space:</comment>', count($findings)));

        $helper = Mage::helper('core');
        $table = new Table($output);
        $table->setHeaders(['Table', 'Size on disk', 'Reclaimable', '%', 'Detail']);
        foreach ($findings as $finding) {
            $table->addRow([
                $finding['table'],
                $helper->formatFileSize($finding['total']),
                $helper->formatFileSize($finding['reclaimable']),
                round($finding['ratio'] * 100) . '%',
                $finding['detail'],
            ]);
        }
        $table->render();
        $output->writeln('');
    }

    private function warnAboutLocking(AdapterInterface $adapter, OutputInterface $output): void
    {
        $warning = match (true) {
            $adapter instanceof Sqlite => 'VACUUM rewrites the whole database file and needs free disk equal to its size.',
            $adapter instanceof Pgsql => 'VACUUM FULL holds an ACCESS EXCLUSIVE lock for its entire duration: every read and'
                . ' write against the table blocks until it finishes.',
            default => 'OPTIMIZE TABLE rebuilds the table and needs free disk equal to its size. It is online for'
                . ' InnoDB, but concurrent writes are still slowed for the duration.',
        };

        $output->writeln('<comment>This is a maintenance-window operation.</comment> ' . $warning);
        $output->writeln('');
    }

    /**
     * SQLite collapses any target list into a single whole-file VACUUM, so the
     * statements do not line up one-to-one with the targets there. Measuring
     * against the first target still works: totalSize() reports the whole file.
     *
     * @param list<string> $statements
     * @param list<array{table: string, total: int, reclaimable: int, ratio: float, detail: string}> $targets
     */
    private function runStatements(AdapterInterface $adapter, OutputInterface $output, array $statements, array $targets): int
    {
        $perTable = !($adapter instanceof Sqlite);
        $totalFreed = 0;

        foreach ($statements as $index => $statement) {
            $measured = $targets[$perTable ? $index : 0]['table'];
            $output->write(sprintf('Optimizing %s... ', $perTable ? $measured : 'the database file'));

            $before = TableBloatScanner::totalSize($adapter, $measured) ?? 0;

            try {
                TableBloatScanner::runStatement($adapter, $statement);
            } catch (\Exception $e) {
                $output->writeln('<error>failed: ' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }

            $after = TableBloatScanner::totalSize($adapter, $measured) ?? $before;
            $freed = max(0, $before - $after);
            $totalFreed += $freed;
            $output->writeln(sprintf('<info>done</info> (freed %s)', Mage::helper('core')->formatFileSize($freed)));
        }

        $output->writeln('');
        $output->writeln(sprintf('<info>Reclaimed %s in total.</info>', Mage::helper('core')->formatFileSize($totalFreed)));

        return Command::SUCCESS;
    }
}
