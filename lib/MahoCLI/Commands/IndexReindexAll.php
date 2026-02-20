<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'index:reindex:all',
    description: 'Reindex all indexes',
)]
class IndexReindexAll extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $indexCollection = Mage::getResourceModel('index/process_collection');
        $totalStartTime = microtime(true);

        foreach ($indexCollection as $index) {
            $output->write("Reindexing {$index->getIndexerCode()}... ");
            $startTime = microtime(true);
            $index->reindexEverything();
            $duration = round(microtime(true) - $startTime, 2);
            $output->writeln(sprintf('<info>done!</info> (%.2fs)', $duration));
        }

        $totalDuration = round(microtime(true) - $totalStartTime, 2);
        $output->writeln('');
        $output->writeln(sprintf('<info>All indexes reindexed in %.2fs</info>', $totalDuration));

        return Command::SUCCESS;
    }
}
