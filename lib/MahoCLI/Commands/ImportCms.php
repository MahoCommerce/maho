<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Import\Importer\AbstractCmsImporter;
use Maho\Import\Importer\CmsBlocks;
use Maho\Import\Importer\CmsPages;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:cms',
    description: 'Create or update CMS pages and blocks from CSV files, with bodies read from HTML files',
)]
class ImportCms extends BaseMahoCommand
{
    use ImportCommandTrait;

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('pages', InputArgument::OPTIONAL, 'Path to cms_pages.csv (identifier, stores, title, content_file, is_home, ...)');
        $this->addOption('blocks', null, InputOption::VALUE_REQUIRED, 'Path to cms_blocks.csv (identifier, stores, title, content_file), imported first');
        $this->addOption('content-dir', null, InputOption::VALUE_REQUIRED, 'Folder the content_file paths are relative to (default: content/ next to each CSV)');
        $this->addDryRunOption();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $dryRun = (bool) $input->getOption('dry-run');
        $options = [];
        if ($input->getOption('content-dir') !== null) {
            $options[AbstractCmsImporter::OPTION_CONTENT_DIR] = $input->getOption('content-dir');
        }
        if ($input->getArgument('pages') === null && $input->getOption('blocks') === null) {
            $output->writeln('<error>Pass a pages CSV, a --blocks CSV, or both</error>');
            return Command::INVALID;
        }
        if ($input->getOption('blocks') !== null) {
            $status = $this->runImport(new CmsBlocks(), $input->getOption('blocks'), $options, $dryRun, $output);
            if ($status !== Command::SUCCESS) {
                return $status;
            }
        }
        if ($input->getArgument('pages') !== null) {
            return $this->runImport(new CmsPages(), $input->getArgument('pages'), $options, $dryRun, $output);
        }
        return Command::SUCCESS;
    }
}
