<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Import\Importer\Attributes;
use Maho\Import\Importer\AttributeSets;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:attributes',
    description: 'Create or update product attributes from a CSV file, with their options and swatches',
)]
class ImportAttributes extends BaseMahoCommand
{
    use ImportCommandTrait;

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('csv', InputArgument::REQUIRED, 'Path to attributes.csv (code, label, input, sets, ...)');
        $this->addOption('options', null, InputOption::VALUE_REQUIRED, 'Path to attribute_options.csv (attribute_code, label, swatch, ...)');
        $this->addOption('sets', null, InputOption::VALUE_REQUIRED, 'Path to attribute_sets.csv (name, skeleton), imported first');
        $this->addDryRunOption();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $dryRun = (bool) $input->getOption('dry-run');
        if ($input->getOption('sets') !== null) {
            $status = $this->runImport(new AttributeSets(), $input->getOption('sets'), [], $dryRun, $output);
            if ($status !== Command::SUCCESS) {
                return $status;
            }
        }
        $options = [];
        if ($input->getOption('options') !== null) {
            $options[Attributes::OPTION_OPTIONS_CSV] = $input->getOption('options');
        }
        return $this->runImport(new Attributes(), $input->getArgument('csv'), $options, $dryRun, $output);
    }
}
