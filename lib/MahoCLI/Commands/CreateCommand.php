<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dev:create-command',
    description: 'Create a new command that will integrate into your project\'s Maho CLI set of commands',
)]
class CreateCommand extends BaseMahoCommand
{
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The name of the command, in lowercase with optional colons (e.g., cache:clean)', name: 'name')]
        #[Ask('What is the name of your command? (e.g., cache:clean)')]
        string $commandName,
        #[Argument(description: 'The description of the command')]
        ?string $description = null,
    ): int {
        if (!preg_match('/^[a-z][a-z0-9]*(:?[a-z0-9]+)*$/', $commandName)) {
            $io->error('The command name should be in lowercase with optional colons (e.g., cache:clean)');
            return Command::INVALID;
        }

        $description = trim((string) $description) ?: 'A new Maho CLI command';

        // Convert command name to class name (e.g., cache:clean -> CacheCleanCommand)
        $className = $this->generateClassName($commandName);

        // Generate the command file content
        $content = $this->generateCommandContent(
            $className,
            $commandName,
            $description,
        );

        // Get the commands directory path
        $commandsDir = $this->getCommandsDirectory();

        // Ensure the directory exists
        if (!is_dir($commandsDir)) {
            if (!mkdir($commandsDir, 0755, true)) {
                $io->error("Failed to create directory: $commandsDir");
                return Command::FAILURE;
            }
        }

        // Full file path
        $filePath = $commandsDir . '/' . $className . '.php';

        // Check if file already exists
        if (file_exists($filePath)) {
            $io->error("Command file already exists at: $filePath");
            return Command::FAILURE;
        }

        // Create the file
        if (file_put_contents($filePath, $content) === false) {
            $io->error("Failed to create command file at: $filePath");
            return Command::FAILURE;
        }

        $io->success([
            'Command created successfully!',
            "File location: $filePath",
            "Use it with: php bin/maho $commandName",
        ]);

        return Command::SUCCESS;
    }

    #[Interact]
    public function askDescription(InputInterface $input, SymfonyStyle $io): void
    {
        if ($input->getArgument('description') === null) {
            $input->setArgument('description', $io->ask('Provide a description for your command', 'A new Maho CLI command'));
        }
    }

    private function generateClassName(string $commandName): string
    {
        return str_replace([':','-'], '', ucwords($commandName, ':-')) . 'Command';
    }

    private function getCommandsDirectory(): string
    {
        return './lib/MahoCLI/Commands';
    }

    private function generateCommandContent(
        string $className,
        string $commandName,
        string $description,
    ): string {
        return <<<FILECONTENT
<?php

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Attribute\Interact;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: '$commandName',
    description: '$description'
)]
class $className extends BaseMahoCommand
{
    public function __invoke(OutputInterface \$output): int
    {
        \$this->initMaho();

        // Your custom logic here

        \$output->writeln('Command executed successfully!');
        return Command::SUCCESS;
    }
}

FILECONTENT;
    }
}
