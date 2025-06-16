<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace MahoCLI\Commands;

use Mage;
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
            ->addOption('indexer', 'i', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'Specific indexer(s) to run');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $productIds = array_map('intval', explode(',', $input->getArgument('product_ids')));
        $specificIndexers = $input->getOption('indexer');

        $products = Mage::getModel('catalog/product')->getCollection()
            ->addIdFilter($productIds)
            ->getAllIds();

        if (count($products) !== count($productIds)) {
            $missingIds = array_diff($productIds, $products);
            $output->writeln('<error>Product ID(s) not found: ' . implode(', ', $missingIds) . '</error>');
            return Command::FAILURE;
        }

        $indexCollection = Mage::getModel('index/process')->getCollection();
        if ($specificIndexers) {
            $indexCollection->addFieldToFilter('indexer_code', ['in' => $specificIndexers]);
        }

        if ($indexCollection->count() === 0) {
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
}
