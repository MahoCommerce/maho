<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Import\Importer\Categories;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:categories',
    description: 'Create or update categories from a CSV file keyed by root name and url key path',
)]
class ImportCategories extends BaseMahoCommand
{
    use ImportCommandTrait;

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('csv', InputArgument::REQUIRED, 'Path to categories.csv (root, path, name, ...)');
        $this->addOption('media-dir', null, InputOption::VALUE_REQUIRED, 'Folder holding the category pictures (default: media/catalog/category next to the CSV)');
        $this->addDryRunOption();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $options = [];
        if ($input->getOption('media-dir') !== null) {
            $options[Categories::OPTION_MEDIA_DIR] = $input->getOption('media-dir');
        }
        return $this->runImport(new Categories(), $input->getArgument('csv'), $options, (bool) $input->getOption('dry-run'), $output);
    }
}
