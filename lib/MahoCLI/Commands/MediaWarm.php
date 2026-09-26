<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Catalog_Model_Product_Image_Warmer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'media:warm',
    description: 'Resize the product images to every size that the templates render, before a visitor asks for them',
)]
class MediaWarm extends BaseMahoCommand
{
    /**
     * @param list<string> $productIds
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Only this product id. Repeat it for more products', name: 'product')]
        array $productIds = [],
        #[Option(description: 'First forget the sizes that no template rendered in this many days', name: 'prune')]
        ?int $pruneDays = null,
        #[Option(description: 'Products per batch', name: 'batch-size')]
        int $batchSize = Mage_Catalog_Model_Product_Image_Warmer::BATCH_SIZE,
    ): int {
        $this->initMaho();

        if ($pruneDays !== null) {
            if ($pruneDays < 1) {
                $io->error('--prune takes a number of days of 1 or more.');
                return Command::INVALID;
            }
            $pruned = Mage::getSingleton('catalog/product_image_variant')->prune($pruneDays);
            $io->text("Forgot {$pruned} size(s) that no template rendered in {$pruneDays} day(s).");
        }

        $ids = array_map(intval(...), $productIds);
        if ($ids === []) {
            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $ids = array_map(intval(...), $adapter->fetchCol(
                $adapter->select()
                    ->from(Mage::getSingleton('core/resource')->getTableName('catalog/product'), 'entity_id')
                    ->order('entity_id'),
            ));
        }

        $warmer = Mage::getSingleton('catalog/product_image_warmer');
        $resized = 0;
        $io->progressStart(count($ids));
        foreach (array_chunk($ids, max(1, $batchSize)) as $batch) {
            $resized += $warmer->warmProducts($batch);
            $io->progressAdvance(count($batch));
        }
        $io->progressFinish();

        $io->success("Resized {$resized} image(s) for " . count($ids) . ' product(s).');
        return Command::SUCCESS;
    }
}
