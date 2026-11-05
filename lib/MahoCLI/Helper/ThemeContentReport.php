<?php

/**
 * Runs the build of the classes of the content, and prints one row for each theme.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Helper;

use Mage;
use Mage_Core_Model_Design_Content_Bundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

class ThemeContentReport
{
    /**
     * Build, and print the sources and the result of each theme. Maho must be initialized.
     *
     * @return int a Command exit code
     */
    public static function build(SymfonyStyle $io): int
    {
        $sources = array_keys(Mage::getSingleton('core/design_content_collector')->getSources());
        $io->text('Sources of the content: ' . ($sources === [] ? 'none' : implode(', ', $sources)));

        $bundle = Mage::getSingleton('core/design_content_bundle');
        $status = $bundle->build();
        if ($status === null) {
            $io->error('Another build of the content classes is running. Run "./maho theme:content:build" when it ends.');
            return Command::FAILURE;
        }
        if ($status === []) {
            $io->warning('No theme has a ' . Mage_Core_Model_Design_Content_Bundle::ENTRY_FILE . ' file.');
            return Command::SUCCESS;
        }

        $manifest = $bundle->getManifest();
        $rows = [];
        foreach ($status as $theme => $result) {
            $rows[] = [$theme, $result, $manifest[$theme]['file'] ?? '-'];
        }
        $io->table(['Theme', 'Result', 'File on the media mount'], $rows);

        if (in_array(Mage_Core_Model_Design_Content_Bundle::STATUS_FAILED, $status, true)) {
            $io->error('The build failed for a theme. The log has the Tailwind output.');
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
