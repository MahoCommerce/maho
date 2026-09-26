<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Import\Importer\ProductViews;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:product-views',
    description: 'Add product view events from a CSV file, up to the number of views of each row',
)]
class ImportProductViews extends BaseMahoCommand
{
    use ImportCommandTrait;

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Path to product_views.csv (sku, store_code, views, days)')]
        string $csv,
        #[Option(description: self::DRY_RUN_DESCRIPTION)]
        bool $dryRun = false,
    ): int {
        $this->initMaho();
        return $this->runImport(new ProductViews(), $csv, [], $dryRun, $output);
    }
}
