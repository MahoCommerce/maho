<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Core_Model_Design_Content_Bundle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'theme:content:build',
    description: 'Compile the Tailwind classes of the CMS, blog and catalog content into a copy of the css/styles.css of each theme',
)]
class ThemeContentBuild extends BaseMahoCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        $this->initMaho();

        $bundle = Mage::getSingleton('core/design_content_bundle');
        if ($bundle->getTailwindBinary() === null) {
            $io->error([
                'The Tailwind CLI is not installed in ' . MAHO_ROOT_DIR . '/node_modules.',
                'Install Node.js and run "npm install" in that folder, then run this command again.',
                'Until then, the store serves the committed css/styles.css.',
            ]);
            return Command::FAILURE;
        }

        $sources = array_keys(Mage::getSingleton('core/design_content_collector')->getSources());
        $io->text('Sources: ' . ($sources === [] ? 'none' : implode(', ', $sources)));

        $status = $bundle->build();
        if ($status === null) {
            $io->error('Another build of the content classes is running. Run this command again when it ends.');
            return Command::FAILURE;
        }
        if ($status === []) {
            $io->warning('No theme has a ' . Mage_Core_Model_Design_Content_Bundle::ENTRY_FILE . ' file.');
            return Command::SUCCESS;
        }

        $manifest = $bundle->getManifest();
        $rows = [];
        foreach ($status as $theme => $result) {
            $file = $manifest[$theme]['file'] ?? null;
            $rows[] = [$theme, $result, $file ?? '-'];
        }
        $io->table(['Theme', 'Result', 'File on the media mount'], $rows);

        if (in_array(Mage_Core_Model_Design_Content_Bundle::STATUS_FAILED, $status, true)) {
            $io->error('The build failed for a theme. The log has the Tailwind output.');
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
