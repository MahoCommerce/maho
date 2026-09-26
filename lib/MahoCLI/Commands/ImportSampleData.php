<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Maho\Import\RowException;
use Maho\Import\SampleData\Installer;
use Maho\Import\SampleData\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:sample-data',
    description: 'Import the sample data packs (stores, attributes, config, media, catalog, content, customers, orders, product views, search terms) from a folder or a repository branch',
)]
class ImportSampleData extends BaseMahoCommand
{
    use ImportCommandTrait;

    public function __invoke(
        OutputInterface $output,
        #[Option(description: 'Folder that holds packs/ and media/ (a maho-sample-data checkout)')]
        ?string $path = null,
        #[Option(description: 'Branch of the maho-sample-data repository to download (default: the branch of this Maho version)')]
        ?string $branch = null,
        #[Option(description: 'Comma separated pack names to import (default: every pack)')]
        ?string $pack = null,
        #[Option(description: 'Do not reindex at the end')]
        bool $skipReindex = false,
    ): int {
        $this->initMaho();
        $reporter = $this->consoleReporter($output, false);
        $packs = $pack !== null ? array_values(array_filter(array_map(trim(...), explode(',', $pack)))) : null;
        try {
            if ($path !== null) {
                $package = Package::fromPath($path);
            } else {
                $branch ??= Package::branchForVersion(Mage::getVersion());
                $package = Package::forBranch($branch, $reporter->info(...));
            }
        } catch (\Maho\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        try {
            $result = new Installer($reporter)->install($package, $packs, !$skipReindex);
        } catch (RowException|\Maho\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        } finally {
            $package->cleanup();
        }
        $output->writeln('<info>Sample data installed: ' . $result->summary() . '</info>');
        if ($skipReindex) {
            $output->writeln('<comment>Run ./maho index:reindex:all before you open the store</comment>');
        }
        return Command::SUCCESS;
    }
}
