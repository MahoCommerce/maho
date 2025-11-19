<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Catalog_Model_Product_Link;
use Mage_Catalog_Model_Product_Type;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'index:reindex:product',
    description: 'Reindex specific product(s) across all or specified indexers',
)]
class IndexReindexProduct extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('product_ids', InputArgument::REQUIRED, 'Product ID(s) to reindex (comma-separated)')
            ->addOption('include-children', 'ic', InputOption::VALUE_NONE, 'Include child products (for configurable, grouped, bundle)')
            ->addOption('indexer', 'i', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Specific indexer(s) to run');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $productIds = array_map('intval', explode(',', $input->getArgument('product_ids')));
        $specificIndexers = $input->getOption('indexer');
        $includeChildren = $input->getOption('include-children');

        // Validate all products exist
        $existingProductIds = Mage::getModel('catalog/product')->getCollection()
            ->addIdFilter($productIds)
            ->getAllIds();

        if ($missingIds = array_diff($productIds, $existingProductIds)) {
            $output->writeln(sprintf('<error>Product ID(s) not found: %s</error>', implode(', ', $missingIds)));
            return Command::FAILURE;
        }

        // If include-children flag is set, find all child products
        if ($includeChildren && $childProductIds = $this->getChildProductIds($productIds)) {
            $productIds = array_unique([...$productIds, ...$childProductIds]);
            $output->writeln(sprintf('<comment>Including %d child product(s) in reindex</comment>', count($childProductIds)));
        }

        $indexCollection = Mage::getModel('index/process')->getCollection();
        if ($specificIndexers) {
            $indexCollection->addFieldToFilter('indexer_code', ['in' => $specificIndexers]);
        }

        if (!$indexCollection->getSize()) {
            $output->writeln('<error>No valid indexers found</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Reindexing product(s): %s</info>', implode(', ', $productIds)));
        $output->writeln('');

        $hasErrors = false;
        foreach ($indexCollection as $process) {
            $output->write(sprintf('Reindexing %s... ', $process->getIndexerCode()));

            try {
                $startTime = microtime(true);
                $process->reindexEntity($productIds);
                $duration = round(microtime(true) - $startTime, 2);
                $output->writeln(sprintf('<info>done!</info> (%.2fs)', $duration));
            } catch (\Exception $e) {
                $output->writeln('<error>failed: ' . $e->getMessage() . '</error>');
                $hasErrors = true;
            }
        }

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Get child product IDs for configurable, grouped, and bundle products
     *
     * @param int[] $parentIds Array of parent product IDs
     * @return int[] Array of unique child product IDs
     */
    protected function getChildProductIds(array $parentIds): array
    {
        $childIds = [];

        // Load products to check their types
        $products = Mage::getModel('catalog/product')->getCollection()
            ->addIdFilter($parentIds)
            ->addAttributeToSelect('type_id');

        $productsByType = [
            Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE => [],
            Mage_Catalog_Model_Product_Type::TYPE_GROUPED => [],
            Mage_Catalog_Model_Product_Type::TYPE_BUNDLE => [],
        ];

        // Group products by type
        foreach ($products as $product) {
            $typeId = $product->getTypeId();
            if (isset($productsByType[$typeId])) {
                $productsByType[$typeId][] = $product->getId();
            }
        }

        [$configurableIds, $groupedIds, $bundleIds] = array_values($productsByType);

        // Get child products from configurable products
        if (!empty($configurableIds)) {
            $resourceModel = Mage::getResourceModel('catalog/product_type_configurable');
            foreach ($configurableIds as $configurableId) {
                $children = $resourceModel->getChildrenIds($configurableId);
                foreach ($children as $childGroup) {
                    if ($childGroup) {
                        array_push($childIds, ...array_values($childGroup));
                    }
                }
            }
        }

        // Get child products from grouped products
        if (!empty($groupedIds)) {
            $groupedLinks = Mage::getResourceModel('catalog/product_link_collection')
                ->addFieldToFilter('link_type_id', Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED)
                ->addFieldToFilter('product_id', ['in' => $groupedIds]);

            foreach ($groupedLinks as $link) {
                $childIds[] = (int) $link->getLinkedProductId();
            }
        }

        // Get child products from bundle products
        if (!empty($bundleIds)) {
            $bundleCollection = Mage::getResourceModel('bundle/selection_collection')
                ->addFieldToFilter('parent_product_id', ['in' => $bundleIds]);

            foreach ($bundleCollection as $selection) {
                $childIds[] = (int) $selection->getProductId();
            }
        }

        // Return unique child IDs
        return array_unique($childIds);
    }
}
