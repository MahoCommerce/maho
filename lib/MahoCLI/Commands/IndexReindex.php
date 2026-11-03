<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Mage_Core_Exception;
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

        /** @var \Mage_Index_Service_Process $service */
        $service = Mage::getService('index/process');
        try {
            $process = $service->getByCode($indexCode);
            $output->write("Reindexing {$process->getIndexerCode()}... ");
            $startTime = microtime(true);
            $service->reindex($process);
        } catch (Mage_Core_Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }
        $output->writeln(sprintf('<info>done!</info> (%.2fs)', microtime(true) - $startTime));

        return Command::SUCCESS;
    }
}
