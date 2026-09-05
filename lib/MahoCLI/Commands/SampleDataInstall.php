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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'sample-data:install',
    description: 'Import the sample data packs (stores, attributes, config, media, catalog, content, customers) from a folder or a repository branch',
)]
class SampleDataInstall extends BaseMahoCommand
{
    use ImportCommandTrait;

    #[\Override]
    protected function configure(): void
    {
        $this->addOption('path', null, InputOption::VALUE_REQUIRED, 'Folder that holds packs/ and media/ (a maho-sample-data checkout)');
        $this->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Branch of the maho-sample-data repository to download (default: the branch of this Maho version)');
        $this->addOption('pack', null, InputOption::VALUE_REQUIRED, 'Comma separated pack names to import (default: every pack)');
        $this->addOption('skip-reindex', null, InputOption::VALUE_NONE, 'Do not reindex at the end');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $reporter = $this->consoleReporter($output, false);
        $packs = $input->getOption('pack') !== null ? array_values(array_filter(array_map(trim(...), explode(',', $input->getOption('pack'))))) : null;
        try {
            if ($input->getOption('path') !== null) {
                $package = Package::fromPath($input->getOption('path'));
            } else {
                $branch = $input->getOption('branch') ?? Package::branchForVersion(Mage::getVersion());
                $package = Package::forBranch($branch, $reporter->info(...));
            }
        } catch (\Maho\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        try {
            $result = (new Installer($reporter))->install($package, $packs, !$input->getOption('skip-reindex'));
        } catch (RowException|\Maho\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        } finally {
            $package->cleanup();
        }
        $output->writeln('<info>Sample data installed: ' . $result->summary() . '</info>');
        if ($input->getOption('skip-reindex')) {
            $output->writeln('<comment>Run ./maho index:reindex:all before you open the store</comment>');
        }
        return Command::SUCCESS;
    }
}
