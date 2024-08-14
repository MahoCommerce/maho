<?php

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'cache:enable',
    description: 'Enable all caches'
)]
class CacheEnable extends BaseMahoCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $db->query('update core_cache_option set value=1');

        $output->writeln("Caches enabled successfully!");
        return Command::SUCCESS;
    }
}
