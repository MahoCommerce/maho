<?php
/**
 * Maho
 *
 * @category   Maho
 * @package    MahoCLI
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'index:reindex:all',
    description: 'Reindex all indexes'
)]
class IndexReindexAll extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $indexCollection = Mage::getResourceModel('index/process_collection');
        foreach ($indexCollection as $index) {
            $output->write("Reindexing {$index->getIndexerCode()}... ");
            $index->reindexEverything();
            $output->writeln('<info>done!</info>');
        }

        return Command::SUCCESS;
    }
}
