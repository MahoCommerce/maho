<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Catalog_Model_Product_Image_Resizer;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'catalog:image:resize',
    description: 'Resize the product images to every size that the templates render, before a visitor asks for them',
)]
class CatalogImageResize extends BaseMahoCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Product ID(s) to resize (comma-separated). Default: every product', name: 'product_ids')]
        ?string $productIds = null,
        #[Option(description: 'Products per batch')]
        int $batchSize = Mage_Catalog_Model_Product_Image_Resizer::BATCH_SIZE,
    ): int {
        $this->initMaho();

        if ($productIds !== null) {
            $ids = array_map(intval(...), explode(',', $productIds));
        } else {
            $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
            $ids = array_map(intval(...), $adapter->fetchCol(
                $adapter->select()
                    ->from(Mage::getSingleton('core/resource')->getTableName('catalog/product'), 'entity_id')
                    ->order('entity_id'),
            ));
        }

        $resizer = Mage::getSingleton('catalog/product_image_resizer');
        $resized = 0;
        $io->progressStart(count($ids));
        foreach (array_chunk($ids, max(1, $batchSize)) as $batch) {
            $resized += $resizer->resizeProducts($batch);
            $io->progressAdvance(count($batch));
        }
        $io->progressFinish();

        $io->success("Resized {$resized} image(s) for " . count($ids) . ' product(s).');
        return Command::SUCCESS;
    }
}
