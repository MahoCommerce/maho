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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'serve',
    description: 'Run Maho with the built in web server',
)]
class Serve extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this->addArgument('port', InputArgument::OPTIONAL, 'Default is 8000', 8000);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = '127.0.0.1';
        $port = $input->getArgument('port');
        $docroot = MAHO_PUBLIC_DIR;

        passthru("php -S {$host}:{$port} -t {$docroot}");

        return Command::SUCCESS;
    }
}
