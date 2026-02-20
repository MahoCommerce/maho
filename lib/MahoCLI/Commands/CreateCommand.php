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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'create-command',
    description: 'Create a new command that will integrate into your project\'s Maho CLI set of commands',
)]
class CreateCommand extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Interactive command name input
        $commandName = $io->ask(
            'What is the name of your command? (e.g., cache:clean)',
            null,
            function ($name) {
                if (empty($name)) {
                    throw new \RuntimeException('The command name cannot be empty');
                }
                if (!preg_match('/^[a-z][a-z0-9]*(:?[a-z0-9]+)*$/', $name)) {
                    throw new \RuntimeException('The command name should be in lowercase with optional colons (e.g., cache:clean)');
                }
                return $name;
            },
        );

        // Interactive description input
        $description = $io->ask(
            'Provide a description for your command',
            'A new Maho CLI command',
            function ($desc) {
                if (empty($desc)) {
                    throw new \RuntimeException('The command description cannot be empty');
                }
                return $desc;
            },
        );

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

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: '$commandName',
    description: '$description'
)]
class $className extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface \$input, OutputInterface \$output): int
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
