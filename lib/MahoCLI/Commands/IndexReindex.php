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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'index:reindex',
    description: 'Reindex a single index'
)]
class IndexReindex extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('index_code', InputArgument::REQUIRED, 'The code of the index, eg: catalog_product_price');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $indexCode = $input->getArgument('index_code');
        $index = Mage::getModel('index/indexer')->getProcessByCode($indexCode);
        if (!$index) {
            $output->writeln('<error>Index not found</error>');
            return Command::FAILURE;
        }

        $output->write("Reindexing {$index->getIndexerCode()}... ");
        $index->reindexEverything();
        $output->writeln('<info>done!</info>');

        return Command::SUCCESS;
    }
}
