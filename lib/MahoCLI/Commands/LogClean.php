<?php

namespace Maho\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'log:clean',
    description: 'Clean log tables in the database'
)]
class LogClean extends BaseMahoCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $output->write('Cleaning log tables in the database... ');
        Mage::getModel('log/log')->clean();
        $output->writeln('<info>done!</info>');

        return Command::SUCCESS;
    }
}
