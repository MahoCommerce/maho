<?php

namespace Maho\Commands;

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
    protected function configure(): void
    {
        $this->addArgument('index_code', InputArgument::REQUIRED, 'The code of the index, eg: catalog_product_price');
    }

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
