<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'export:categories',
    description: 'Export categories to a CSV file in the Import/Export layout',
)]
class ExportCategories extends BaseMahoCommand
{
    use ExportCommandTrait;

    /**
     * @param list<string> $filter
     * @param list<string> $skip
     */
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: self::CSV_DESCRIPTION)]
        ?string $csv = null,
        #[Option(description: self::FILTER_DESCRIPTION)]
        array $filter = [],
        #[Option(description: self::SKIP_DESCRIPTION)]
        array $skip = [],
    ): int {
        $this->initMaho();
        return $this->runExport('catalog_category', $csv, $filter, $skip, $output);
    }
}
