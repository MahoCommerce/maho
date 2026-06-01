<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Core_Model_Resource_Setup;
use Maho\Db\Schema\Applier;
use Maho\Db\Schema\Collector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'migrate',
    description: 'Apply pending database schema and data updates from modules',
)]
class Migrate extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Show the pending schema/data changes without applying anything',
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        if ($input->getOption('dry-run')) {
            return $this->dryRun($output);
        }

        $output->writeln('<info>Applying pending updates...</info>');

        // Replicate the exact logic from CacheController::flushSystemAction()
        Mage::app()->getCache()->banUse('config');
        Mage::getConfig()->reinit();
        Mage::getConfig()->getCacheSaveLock(30, true);

        try {
            Mage::app()->cleanCache();
            $output->writeln('✓ Cleaned cache');

            /** @var \Maho\Db\Adapter\AdapterInterface $adapter */
            $adapter = Mage::getSingleton('core/resource')->getConnection('core_setup');
            try {
                $result = Applier::applyAll($adapter);
            } catch (\Maho\Db\Schema\UnsupportedMigrationException $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }
            if ($result['contributors'] === []) {
                $output->writeln('✓ Applied declarative schema (no modules declare sql/schema.php)');
            } elseif ($result['executed'] === []) {
                $output->writeln(sprintf(
                    '✓ Applied declarative schema (%d module(s), already up to date)',
                    count($result['contributors']),
                ));
            } else {
                $output->writeln(sprintf(
                    '✓ Applied declarative schema (%d module(s), %d statement(s) executed)',
                    count($result['contributors']),
                    count($result['executed']),
                ));
            }

            Mage_Core_Model_Resource_Setup::applyAllUpdates();
            $output->writeln('✓ Applied legacy schema/data updates');

            Mage_Core_Model_Resource_Setup::applyAllDataUpdates();
            $output->writeln('✓ Applied data updates');

            Mage_Core_Model_Resource_Setup::applyAllMahoUpdates();
            $output->writeln('✓ Applied Maho updates');

            Mage::app()->getCache()->unbanUse('config');
            Mage::getConfig()->saveCache();
            $output->writeln('✓ Saved cache configuration');
        } finally {
            Mage::getConfig()->releaseCacheSaveLock();
        }

        Mage::dispatchEvent('adminhtml_cache_flush_system');

        $output->writeln('<info>All updates applied successfully!</info>');
        return Command::SUCCESS;
    }

    /**
     * Preview pending changes without touching the database.
     *
     * The declarative half is exact: Applier::plan() returns the literal SQL it
     * would execute, so DROP statements for indexes/foreign keys that exist in
     * the live database but no module declares show up here, before they run.
     * The setup-script half can only list the imperative scripts that would
     * run, not the SQL they emit (unknowable without executing them).
     */
    private function dryRun(OutputInterface $output): int
    {
        $output->writeln('<info>Dry run: no changes will be applied.</info>');
        $output->writeln('');

        /** @var \Maho\Db\Adapter\AdapterInterface $adapter */
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_setup');

        $output->writeln('<comment>Declarative schema</comment>');
        [$target, $contributors] = Collector::collect();
        $drops = 0;
        if ($contributors === []) {
            $output->writeln('  No modules declare sql/schema.php');
        } else {
            try {
                $sql = Applier::plan($adapter->getConnection(), $target);
            } catch (\Maho\Db\Schema\UnsupportedMigrationException $e) {
                $output->writeln('  <error>' . $e->getMessage() . '</error>');
                return Command::FAILURE;
            }
            if ($sql === []) {
                $output->writeln(sprintf('  %d module(s) declared, already up to date', count($contributors)));
            } else {
                $lines = $this->compactPlan($sql);
                $drops = count(array_filter($lines, static fn(array $l): bool => $l['destructive']));
                $output->writeln(sprintf(
                    '  %d module(s) declared, %d statement(s) pending, %d destructive:',
                    count($contributors),
                    count($sql),
                    $drops,
                ));
                foreach ($lines as $line) {
                    $output->writeln($line['destructive'] ? '    <error>' . $line['text'] . '</error>' : '    ' . $line['text']);
                }
                if ($output->isVerbose()) {
                    $output->writeln('');
                    $output->writeln('  <comment>Raw SQL (-v):</comment>');
                    foreach ($sql as $stmt) {
                        $output->writeln('    ' . preg_replace('/\s+/', ' ', trim($stmt)));
                    }
                }
            }
        }

        if ($drops > 0) {
            $output->writeln('');
            $output->writeln(sprintf(
                '  <error>%d destructive statement(s) above.</error> An index or foreign key on a managed table'
                . ' that no module declares in sql/schema.php is dropped on convergence. To keep a custom one,'
                . ' declare it in a module\'s sql/schema.php.',
                $drops,
            ));
        }

        $output->writeln('');
        $output->writeln('<comment>Setup scripts</comment>');
        $pending = Mage_Core_Model_Resource_Setup::getAllPendingUpdates();
        if ($pending === []) {
            $output->writeln('  No pending scripts');
        } else {
            $basePath = rtrim(Mage::getBaseDir(), '/') . '/';
            foreach ($pending as $resName => $updates) {
                $output->writeln('  <info>' . $resName . '</info>');
                foreach ($updates as $update) {
                    $file = $update['fileName'];
                    if (str_starts_with($file, $basePath)) {
                        $file = substr($file, strlen($basePath));
                    }
                    $output->writeln(sprintf(
                        '    [%s] → %s  %s',
                        $update['type'],
                        $update['toVersion'],
                        $file,
                    ));
                }
            }
            $output->writeln('');
            $output->writeln(
                '  <comment>Note:</comment> setup scripts are imperative PHP; only the files that would run are'
                . ' listed, not the SQL they emit.',
            );
        }

        return Command::SUCCESS;
    }

    /**
     * Reduce the raw plan to one readable line per change. A SQLite table
     * rebuild (drop + recreate + reinsert) collapses to a single "rebuilt"
     * line; every other statement is flattened to a compact verb form. Pass
     * -v to see the raw SQL instead.
     *
     * @param list<string> $sql
     * @return list<array{text: string, destructive: bool}>
     */
    private function compactPlan(array $sql): array
    {
        // A table is rebuilt in place when the plan both drops and recreates
        // it (SQLite's only way to alter a table). Fold the whole sequence.
        $created = [];
        $droppedReal = [];
        foreach ($sql as $stmt) {
            if (preg_match('/^\s*CREATE\s+TABLE\s+"?([^"\s(]+)/i', $stmt, $m)) {
                $created[$m[1]] = true;
            } elseif (preg_match('/^\s*DROP\s+TABLE\s+"?([^"\s;]+)/i', $stmt, $m)
                && !str_starts_with($m[1], '__maho_tmp_')
            ) {
                $droppedReal[$m[1]] = true;
            }
        }
        $rebuilt = array_intersect_key($created, $droppedReal);

        $lines = [];
        $seenRebuild = [];
        foreach ($sql as $stmt) {
            $table = $this->statementTable($stmt);
            if ($table !== null && isset($rebuilt[$table])) {
                if (!isset($seenRebuild[$table])) {
                    $seenRebuild[$table] = true;
                    $lines[] = ['text' => $table . ': rebuilt to declarative target', 'destructive' => false];
                }
                continue;
            }
            $lines[] = $this->compactStatement($stmt);
        }

        return $lines;
    }

    /**
     * Best-effort base table a statement touches, mapping the SQLite rebuild
     * temp table (__maho_tmp_X) back to X. Null when no table is identifiable.
     */
    private function statementTable(string $stmt): ?string
    {
        if (preg_match('/__maho_tmp_([^"\s;(]+)/i', $stmt, $m)) {
            return $m[1];
        }
        if (preg_match('/^\s*(?:CREATE\s+(?:TEMPORARY\s+)?TABLE|DROP\s+TABLE|INSERT\s+INTO|ALTER\s+TABLE)\s+"?([^"\s;(]+)/i', $stmt, $m)) {
            return $m[1];
        }
        if (preg_match('/^\s*(?:CREATE\s+(?:UNIQUE\s+)?INDEX\s+\S+|DROP\s+INDEX\s+\S+)\s+ON\s+"?([^"\s;(]+)/i', $stmt, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Flatten a single statement to a compact, readable line and flag whether
     * it destroys schema a module no longer declares. Unrecognized statements
     * fall back to their whitespace-collapsed, truncated SQL.
     *
     * @return array{text: string, destructive: bool}
     */
    private function compactStatement(string $stmt): array
    {
        $stmt = trim($stmt);
        $destructive = preg_match('/\bDROP\s+(INDEX|FOREIGN\s+KEY|CONSTRAINT|COLUMN|PRIMARY\s+KEY)\b/i', $stmt) === 1;

        if (preg_match('/^CREATE\s+TABLE\s+"?([^"\s(]+)/i', $stmt, $m)) {
            return ['text' => "create table {$m[1]}", 'destructive' => false];
        }
        if (preg_match('/^CREATE\s+(UNIQUE\s+)?INDEX\s+"?([^"\s]+?)"?\s+ON\s+"?([^"\s(]+)"?\s*\(([^)]*)\)/i', $stmt, $m)) {
            $kind = $m[1] !== '' ? 'unique index' : 'index';
            return ['text' => "add {$kind} {$m[2]} on {$m[3]} ({$m[4]})", 'destructive' => false];
        }
        if (preg_match('/^DROP\s+INDEX\s+"?([^"\s;]+?)"?(?:\s+ON\s+"?([^"\s;]+))?/i', $stmt, $m)) {
            $on = isset($m[2]) ? " on {$m[2]}" : '';
            return ['text' => "drop index {$m[1]}{$on}", 'destructive' => true];
        }
        if (preg_match('/^ALTER\s+TABLE\s+"?([^"\s(]+)"?\s+(.+)$/is', $stmt, $m)) {
            $body = $this->truncate((string) preg_replace('/\s+/', ' ', trim($m[2])), 120);
            return ['text' => "alter {$m[1]}: {$body}", 'destructive' => $destructive];
        }

        $flat = (string) preg_replace('/\s+/', ' ', $stmt);
        return ['text' => $this->truncate($flat, 140), 'destructive' => $destructive];
    }

    private function truncate(string $value, int $length): string
    {
        return strlen($value) > $length ? substr($value, 0, $length - 3) . '...' : $value;
    }
}
