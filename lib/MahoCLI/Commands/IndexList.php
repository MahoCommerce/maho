<?php

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'index:list',
    description: 'List all indexes'
)]
class IndexList extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $table = new Table($output);
        $table->setHeaders(['indexer_code', 'status', 'started_at', 'ended_at', 'mode']);

        $indexCollection = Mage::getResourceModel('index/process_collection');
        foreach ($indexCollection as $index) {
            $indexData = $index->debug();
            unset($indexData['process_id']);
            $table->addRow($indexData);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
