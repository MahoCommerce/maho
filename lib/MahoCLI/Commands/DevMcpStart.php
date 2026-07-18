<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'dev:mcp:start',
    description: 'Start the MCP (Model Context Protocol) server for AI agent integration',
)]
class DevMcpStart extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!class_exists(\React\Stream\ReadableResourceStream::class)) {
            $output->writeln('<error>react/stream is not installed. Run: composer require react/stream</error>');
            return Command::FAILURE;
        }

        $this->initMaho();

        /** @var \Maho_Intelligence_Model_Mcp_Server $server */
        $server = Mage::getModel('intelligence/mcp_server');
        $server->run();

        return Command::SUCCESS;
    }
}
