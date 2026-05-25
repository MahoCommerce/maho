<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Doctrine\DBAL\Schema\Schema;
use Mage;
use Maho\Db\Schema\Applier;
use Maho\Db\Schema\Collector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'db:schema:upgrade',
    description: 'Reconcile the database with all modules\' etc/db_schema.php declarations (declarative migrations)',
)]
class DBSchemaUpgrade extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print SQL without executing it')
            ->addOption('allow-destructive', null, InputOption::VALUE_NONE, 'Allow DROP statements (columns, indexes, FKs, tables)');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $target = new Schema();
        $contributors = Collector::collect($target);

        if ($contributors === []) {
            $output->writeln('<comment>No modules declare etc/db_schema.php yet.</comment>');
            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            '<info>Collected schema from %d module(s):</info> %s',
            count($contributors),
            implode(', ', $contributors),
        ));

        /** @var \Maho\Db\Adapter\AdapterInterface $adapter */
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_setup');
        $connection = $adapter->getConnection();

        $sql = Applier::plan($connection, $target);

        if ($sql === []) {
            $output->writeln('<info>Schema is up to date. Nothing to do.</info>');
            return Command::SUCCESS;
        }

        $destructive = Applier::destructiveStatements($sql);

        if ($destructive !== [] && !$input->getOption('allow-destructive') && !$input->getOption('dry-run')) {
            $output->writeln('<error>Refusing to run destructive statements without --allow-destructive:</error>');
            foreach ($destructive as $stmt) {
                $output->writeln('  ' . $stmt);
            }
            return Command::FAILURE;
        }

        if ($input->getOption('dry-run')) {
            $output->writeln('<comment>-- dry run, no statements executed --</comment>');
            foreach ($sql as $stmt) {
                $output->writeln($stmt . ';');
            }
            return Command::SUCCESS;
        }

        foreach ($sql as $stmt) {
            $output->writeln('<info>Executing:</info> ' . $stmt);
            $connection->executeStatement($stmt);
        }
        $output->writeln(sprintf('<info>Applied %d statement(s).</info>', count($sql)));

        return Command::SUCCESS;
    }
}
