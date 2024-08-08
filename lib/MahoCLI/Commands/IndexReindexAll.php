<?php

namespace Maho\Commands;

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
