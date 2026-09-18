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
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:cms',
    description: 'Create or update CMS pages and blocks from CSV files, with bodies read from HTML files',
)]
class ImportCms extends BaseMahoCommand
{
    use ImportCommandTrait;

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Path to cms_pages.csv (identifier, stores, title, content_file, is_home, ...)')]
        ?string $pages = null,
        #[Option(description: 'Path to cms_blocks.csv (identifier, stores, title, content_file), imported first')]
        ?string $blocks = null,
        #[Option(description: 'Folder the content_file paths are relative to (default: content/ next to each CSV)')]
        ?string $contentDir = null,
        #[Option(description: self::DRY_RUN_DESCRIPTION)]
        bool $dryRun = false,
    ): int {
        $this->initMaho();
        $options = [];
        if ($contentDir !== null) {
            $options[AbstractCmsImporter::OPTION_CONTENT_DIR] = $contentDir;
        }
        if ($pages === null && $blocks === null) {
            $output->writeln('<error>Pass a pages CSV, a --blocks CSV, or both</error>');
            return Command::INVALID;
        }
        if ($blocks !== null) {
            $status = $this->runImport(new CmsBlocks(), $blocks, $options, $dryRun, $output);
            if ($status !== Command::SUCCESS) {
                return $status;
            }
        }
        if ($pages !== null) {
            return $this->runImport(new CmsPages(), $pages, $options, $dryRun, $output);
        }
        return Command::SUCCESS;
    }
}
