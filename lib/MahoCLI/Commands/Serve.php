<?php

namespace Maho\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'serve',
    description: 'Run Maho with the built in web server'
)]
class Serve extends BaseMahoCommand
{
    protected function configure(): void
    {
        $this->addArgument('port', InputArgument::OPTIONAL, 'Default is 8000', 8000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = '127.0.0.1';
        $port = $input->getArgument('port');
        $docroot = MAHO_ROOT_DIR . '/pub';

        $output->writeln("Serving Maho on http://{$host}:{$port}, press CTRL+C to exit...");
        passthru("php -t {$host}:{$port} -t {$docroot}");
        $output->writeln("Process interrupted.");

        return Command::SUCCESS;
    }
}
