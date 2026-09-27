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
    name: 'catalog:image:clean',
    description: 'Forget the product image sizes that no template rendered in a number of days, and delete their resized files',
)]
class CatalogImageClean extends BaseMahoCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Forget the sizes that no template rendered in this many days')]
        int $days = 30,
    ): int {
        $this->initMaho();

        if ($days < 1) {
            $io->error('--days takes a number of 1 or more.');
            return Command::INVALID;
        }

        $count = Mage::getSingleton('catalog/product_image_variant')->prune($days);
        $io->success("Forgot {$count} size(s) that no template rendered in {$days} day(s), and deleted their resized files.");
        return Command::SUCCESS;
    }
}
