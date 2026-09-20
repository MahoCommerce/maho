<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'index:reindex',
    description: 'Reindex a single index',
)]
class IndexReindex extends BaseMahoCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'The code of the index, eg: catalog_product_price', name: 'index_code')]
        string $indexCode,
    ): int {
        $this->initMaho();

        $index = Mage::getModel('index/indexer')->getProcessByCode($indexCode);
        if (!$index) {
            $output->writeln('<error>Index not found</error>');
            return Command::FAILURE;
        }

        $output->write("Reindexing {$index->getIndexerCode()}... ");
        $startTime = microtime(true);
        $index->reindexEverything();
        $duration = round(microtime(true) - $startTime, 2);
        $output->writeln(sprintf('<info>done!</info> (%.2fs)', $duration));

        return Command::SUCCESS;
    }
}
