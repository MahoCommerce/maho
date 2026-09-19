<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Import\Importer\Customers;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:customers',
    description: 'Import customers from a CSV file in the Import/Export layout',
)]
class ImportCustomers extends BaseMahoCommand
{
    use ImportCommandTrait;

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Path to customers.csv (email, _website, firstname, lastname, ...)')]
        string $csv,
        #[Option(description: 'append, replace or delete')]
        string $behavior = 'append',
        #[Option(description: self::DRY_RUN_DESCRIPTION)]
        bool $dryRun = false,
    ): int {
        $this->initMaho();
        $options = [Customers::OPTION_BEHAVIOR => $behavior];
        return $this->runImport(new Customers(), $csv, $options, $dryRun, $output);
    }
}
