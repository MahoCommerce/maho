<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Import\Importer\Stores;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:stores',
    description: 'Create or update websites, store groups, root categories and store views from a CSV file',
)]
class ImportStores extends BaseMahoCommand
{
    use ImportCommandTrait;

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('csv', InputArgument::REQUIRED, 'Path to stores.csv (website_code, root_category, store_code, ...)');
        $this->addDryRunOption();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        return $this->runImport(new Stores(), $input->getArgument('csv'), [], (bool) $input->getOption('dry-run'), $output);
    }
}
