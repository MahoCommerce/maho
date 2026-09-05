<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Import\Importer\Customers;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:customers',
    description: 'Import customers from a CSV file in the Import/Export layout',
)]
class ImportCustomers extends BaseMahoCommand
{
    use ImportCommandTrait;

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('csv', InputArgument::REQUIRED, 'Path to customers.csv (email, _website, firstname, lastname, ...)');
        $this->addOption('behavior', null, InputOption::VALUE_REQUIRED, 'append, replace or delete', 'append');
        $this->addDryRunOption();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $options = [Customers::OPTION_BEHAVIOR => $input->getOption('behavior')];
        return $this->runImport(new Customers(), $input->getArgument('csv'), $options, (bool) $input->getOption('dry-run'), $output);
    }
}
