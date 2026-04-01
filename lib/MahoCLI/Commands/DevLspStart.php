<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'dev:lsp:start',
    description: 'Start the LSP (Language Server Protocol) server for editor integration',
)]
class DevLspStart extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!class_exists(\React\Stream\ReadableResourceStream::class)) {
            $output->writeln('<error>react/stream is not installed. Run: composer require react/stream</error>');
            return Command::FAILURE;
        }

        $this->initMaho();

        /** @var \Maho_Intelligence_Model_Lsp_Server $server */
        $server = Mage::getModel('intelligence/lsp_server');
        $server->run();

        return Command::SUCCESS;
    }
}
