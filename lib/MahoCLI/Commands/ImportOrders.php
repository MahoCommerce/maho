<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Import\Importer\Orders;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:orders',
    description: 'Place sales orders from a CSV file, dated back from now; a rerun skips the orders it placed',
)]
class ImportOrders extends BaseMahoCommand
{
    use ImportCommandTrait;

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Path to orders.csv (reference, store_code, hours_ago, status, email, address columns, items as sku:qty|sku:qty, ...)')]
        string $csv,
        #[Option(description: self::DRY_RUN_DESCRIPTION)]
        bool $dryRun = false,
    ): int {
        $this->initMaho();
        return $this->runImport(new Orders(), $csv, [], $dryRun, $output);
    }
}
