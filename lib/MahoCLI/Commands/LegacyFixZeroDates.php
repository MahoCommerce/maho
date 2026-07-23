<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use MahoCLI\Helper\ZeroDateScanner;
use Maho\Db\Adapter\Pdo\Mysql;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'legacy:fix-zero-dates',
    description: 'Fix legacy zero dates that strict SQL_MODE rejects (dry run by default, apply with --force)',
)]
class LegacyFixZeroDates extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Apply the fixes instead of only reporting them');
        $this->setHelp(<<<'HELP'
            Scans every date/datetime/timestamp column for legacy '0000-00-00' values and
            zero-date column DEFAULTs, typically left behind by stores migrated from
            Magento/OpenMage. Strict SQL_MODE (NO_ZERO_DATE) rejects rewriting such rows
            and inserting via such defaults.

            What it changes, per finding:
            - nullable column with zero-date rows: sets those rows to NULL
              (in Magento 1 semantics a zero date always meant "no value")
            - nullable column with a zero-date DEFAULT: changes it to DEFAULT NULL
              (metadata-only, instant even on huge tables)
            - NOT NULL columns: never touched; listed for a per-column decision
              (make the column nullable, or backfill a meaningful real date)

            Without --force nothing is written: the command prints exactly what it
            would change, with per-column row counts. Re-run with --force to apply.
            HELP);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        if (!($adapter instanceof Mysql)) {
            $output->writeln('<info>Nothing to do: zero dates only exist on MySQL/MariaDB.</info>');
            return Command::SUCCESS;
        }

        $apply = (bool) $input->getOption('force');
        $defaults = ZeroDateScanner::findZeroDateDefaults($adapter);
        $values = ZeroDateScanner::findZeroDateValues($adapter);

        if (empty($defaults) && empty($values)) {
            $output->writeln('<info>No zero dates found, nothing to fix.</info>');
            return Command::SUCCESS;
        }

        if (!$apply) {
            $output->writeln('<comment>Dry run: re-run with --force to apply the changes below.</comment>');
            $output->writeln('');
        }

        $manual = [];

        foreach ($values as $finding) {
            $target = "{$finding['table']}.{$finding['column']}";
            if (!$finding['nullable']) {
                $manual[] = "$target holds {$finding['rows']} zero-date row(s) but is NOT NULL";
                continue;
            }
            if ($apply) {
                $updated = $adapter->update(
                    $finding['table'],
                    [$finding['column'] => null],
                    ZeroDateScanner::zeroDatePredicate($adapter, $finding['column']),
                );
                $output->writeln("Set $updated zero-date row(s) to NULL in $target");
            } else {
                $output->writeln("Would set {$finding['rows']} zero-date row(s) to NULL in $target");
            }
        }

        foreach ($defaults as $finding) {
            $target = "{$finding['table']}.{$finding['column']}";
            if (!$finding['nullable']) {
                $manual[] = "$target has a zero-date DEFAULT but is NOT NULL";
                continue;
            }
            if ($apply) {
                $adapter->query(sprintf(
                    'ALTER TABLE %s ALTER COLUMN %s SET DEFAULT NULL',
                    $adapter->quoteIdentifier($finding['table']),
                    $adapter->quoteIdentifier($finding['column']),
                ));
                $output->writeln("Changed DEFAULT to NULL on $target");
            } else {
                $output->writeln("Would change DEFAULT to NULL on $target");
            }
        }

        if (!empty($manual)) {
            $output->writeln('');
            $output->writeln('<comment>Manual attention needed (a NULL fix would violate the column definition):</comment>');
            foreach ($manual as $line) {
                $output->writeln('- ' . $line);
            }
            $output->writeln('Decide per column: make it nullable, or set a meaningful real date.');
        }

        return Command::SUCCESS;
    }
}
