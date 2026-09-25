<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Import\Importer\SearchTerms;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:search-terms',
    description: 'Create or update search terms with their popularity from a CSV file',
)]
class ImportSearchTerms extends BaseMahoCommand
{
    use ImportCommandTrait;

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Path to search_terms.csv (store_code, query_text, popularity, num_results, hours_ago)')]
        string $csv,
        #[Option(description: self::DRY_RUN_DESCRIPTION)]
        bool $dryRun = false,
    ): int {
        $this->initMaho();
        return $this->runImport(new SearchTerms(), $csv, [], $dryRun, $output);
    }
}
