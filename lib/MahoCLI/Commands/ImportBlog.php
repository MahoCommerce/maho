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
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:blog',
    description: 'Create or update blog posts from a CSV file, with bodies read from HTML files',
)]
class ImportBlog extends BaseMahoCommand
{
    use ImportCommandTrait;

    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Path to blog_posts.csv (url_key, stores, title, publish_date, content_file, image, ...)')]
        string $csv,
        #[Option(description: 'Folder the content_file paths are relative to (default: content/ next to the CSV)')]
        ?string $contentDir = null,
        #[Option(description: self::DRY_RUN_DESCRIPTION)]
        bool $dryRun = false,
    ): int {
        $this->initMaho();
        $options = [];
        if ($contentDir !== null) {
            $options[AbstractCmsImporter::OPTION_CONTENT_DIR] = $contentDir;
        }
        return $this->runImport(new BlogPosts(), $csv, $options, $dryRun, $output);
    }
}
