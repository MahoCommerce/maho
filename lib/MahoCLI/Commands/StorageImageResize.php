<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'storage:image:resize',
    description: 'Create the missing resized files of the images, then switch the store views to the current image settings',
)]
class StorageImageResize extends BaseMahoCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Only this image entity, such as product, category or blog')]
        ?string $entity = null,
    ): int {
        $this->initMaho();

        $entities = array_keys(Mage::getSingleton('core/image_config')->getEntities());
        if ($entity !== null) {
            if (!in_array($entity, $entities, true)) {
                $io->error("Unknown image entity \"{$entity}\". Known entities: " . implode(', ', $entities));
                return Command::INVALID;
            }
            $entities = [$entity];
        }

        $resizer = Mage::getSingleton('core/image_resizer');
        foreach ($entities as $name) {
            $io->section($name);
            $progress = $io->createProgressBar();
            $progress->setFormat(' %current% images | elapsed %elapsed%');
            $progress->start();
            $count = $resizer->createAll($name, fn() => $progress->advance());
            $progress->finish();
            $io->newLine(2);
            $io->text("Created {$count} file(s).");
        }

        $io->success('The resized files exist, and every store view uses its current image settings.');
        return Command::SUCCESS;
    }
}
