<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'storage:image:clean',
    description: 'Delete the resized files of image settings that no store view uses, and the stored sizes of images that no record uses',
)]
class StorageImageClean extends BaseMahoCommand
{
    public function __invoke(SymfonyStyle $io): int
    {
        $this->initMaho();

        $resizer = Mage::getSingleton('core/image_resizer');
        $count = 0;
        foreach (array_keys(Mage::getSingleton('core/image_config')->getEntities()) as $entity) {
            $count += $resizer->clean($entity);
        }

        $sizes = Mage::getSingleton('core/image_size')->deleteOrphans();

        $io->success("Deleted {$count} folder(s) of unused image settings and {$sizes} stored size(s) of unused images.");
        return Command::SUCCESS;
    }
}
