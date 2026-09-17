<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Import\Importer\Config;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:config',
    description: 'Save configuration values from a CSV file, scoped by website or store code',
)]
class ImportConfig extends BaseMahoCommand
{
    use ImportCommandTrait;

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Path to config.csv (path, value, scope, scope_code)')]
        string $csv,
        #[Option(description: self::DRY_RUN_DESCRIPTION)]
        bool $dryRun = false,
    ): int {
        $this->initMaho();
        return $this->runImport(new Config(), $csv, [], $dryRun, $output);
    }
}
