<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Import\Importer\Categories;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:categories',
    description: 'Create or update categories from a CSV file keyed by root name and url key path',
)]
class ImportCategories extends BaseMahoCommand
{
    use ImportCommandTrait;

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Path to categories.csv (root, path, name, ...)')]
        string $csv,
        #[Option(description: 'Folder holding the category pictures (default: media/catalog/category next to the CSV)')]
        ?string $mediaDir = null,
        #[Option(description: self::DRY_RUN_DESCRIPTION)]
        bool $dryRun = false,
    ): int {
        $this->initMaho();
        $options = [];
        if ($mediaDir !== null) {
            $options[Categories::OPTION_MEDIA_DIR] = $mediaDir;
        }
        return $this->runImport(new Categories(), $csv, $options, $dryRun, $output);
    }
}
