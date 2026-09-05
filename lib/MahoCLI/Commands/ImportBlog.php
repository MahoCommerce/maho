<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Import\Importer\AbstractCmsImporter;
use Maho\Import\Importer\BlogPosts;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:blog',
    description: 'Create or update blog posts from a CSV file, with bodies read from HTML files',
)]
class ImportBlog extends BaseMahoCommand
{
    use ImportCommandTrait;

    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('csv', InputArgument::REQUIRED, 'Path to blog_posts.csv (url_key, stores, title, publish_date, content_file, image, ...)');
        $this->addOption('content-dir', null, InputOption::VALUE_REQUIRED, 'Folder the content_file paths are relative to (default: content/ next to the CSV)');
        $this->addDryRunOption();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $options = [];
        if ($input->getOption('content-dir') !== null) {
            $options[AbstractCmsImporter::OPTION_CONTENT_DIR] = $input->getOption('content-dir');
        }
        return $this->runImport(new BlogPosts(), $input->getArgument('csv'), $options, (bool) $input->getOption('dry-run'), $output);
    }
}
