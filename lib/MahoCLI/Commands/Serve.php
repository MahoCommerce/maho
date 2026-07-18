<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host/interface to bind', '127.0.0.1');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = (string) $input->getOption('host');
        $port = $input->getArgument('port');
        $docroot = MAHO_PUBLIC_DIR;

        passthru('php -S ' . escapeshellarg("{$host}:{$port}") . ' -t ' . escapeshellarg($docroot));

        return Command::SUCCESS;
    }
}
