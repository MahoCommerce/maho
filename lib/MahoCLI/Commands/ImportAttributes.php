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
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:attributes',
    description: 'Create or update product attributes from a CSV file, with their options and swatches',
)]
class ImportAttributes extends BaseMahoCommand
{
    use ImportCommandTrait;

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Path to attributes.csv (code, label, input, sets, ...; a row with a store_code sets the label of that store view)')]
        string $csv,
        #[Option(description: 'Path to attribute_options.csv (attribute_code, label, swatch, ...)', name: 'options')]
        ?string $optionsCsv = null,
        #[Option(description: 'Path to attribute_sets.csv (name, skeleton), imported first')]
        ?string $sets = null,
        #[Option(description: self::DRY_RUN_DESCRIPTION)]
        bool $dryRun = false,
    ): int {
        $this->initMaho();
        if ($sets !== null) {
            $status = $this->runImport(new AttributeSets(), $sets, [], $dryRun, $output);
            if ($status !== Command::SUCCESS) {
                return $status;
            }
        }
        $options = [];
        if ($optionsCsv !== null) {
            $options[Attributes::OPTION_OPTIONS_CSV] = $optionsCsv;
        }
        return $this->runImport(new Attributes(), $csv, $options, $dryRun, $output);
    }
}
