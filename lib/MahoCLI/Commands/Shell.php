<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
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
    name: 'shell',
    description: 'Opens an interactive PHP shell with Maho bootstrapped',
)]
class Shell extends BaseMahoCommand
{
    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Maho Shell');
        $io->text('Initializing Maho application...');

        $this->initMaho();

        $io->success('Maho application initialized!');
        $io->text([
            'You can now interact with Maho using PHP code.',
            'Type "exit" or press Ctrl+D to quit.',
            '',
            'Example commands:',
            '  $product = Mage::getModel("catalog/product")->load(1);',
            '  $customer = $getModel("customer/customer")->load(1);',
            '  $db()->fetchAll("SELECT * FROM core_store");',
            '',
        ]);

        if ($this->hasPsysh()) {
            return $this->runPsysh($io);
        }

        $io->warning('PsySH is not installed. Using basic REPL instead.');
        $io->text('For a much better experience, install PsySH:');
        $io->text('  composer require --dev psy/psysh');
        $io->newLine();

        return $this->runBasicRepl($io);
    }

    private function hasPsysh(): bool
    {
        return class_exists(\Psy\Shell::class);
    }

    private function runPsysh(SymfonyStyle $io): int
    {
        $io->text('Starting enhanced shell with PsySH...');

        $config = new \Psy\Configuration();
        $config->setUpdateCheck('never');
        $config->setColorMode(\Psy\Configuration::COLOR_MODE_FORCED);

        // Add custom commands if needed
        $config->setStartupMessage(
            "Maho Shell - PsySH Enhanced\n" .
            "===========================\n" .
            "Helper functions:\n" .
            "  \$db()         - Database connection (auto-reconnects)\n" .
            "  \$getModel('catalog/product')\n" .
            "  \$getCollection('customer/customer')\n" .
            "  \$reconnect()  - Refresh DB connections\n\n" .
            "PsySH commands:\n" .
            "  ls       - List variables/methods\n" .
            "  show     - Show source code\n" .
            "  doc      - Show documentation\n" .
            "  help     - Show PsySH help\n",
        );

        // Get database connection for convenience
        $resource = Mage::getSingleton('core/resource');

        $shell = new \Psy\Shell($config);
        $shell->setScopeVariables([
            'db' => function () use ($resource) {
                // Always get fresh connection to avoid timeout issues
                $conn = $resource->getConnection('core_read');
                // Reconnect if connection was lost
                if (!$conn->isConnected()) {
                    $conn->closeConnection();
                    $conn->getConnection();
                }
                return $conn;
            },
            // Add some helpful shortcuts
            'getModel' => function ($modelClass) {
                // Ensure DB connection is fresh
                $resource = Mage::getSingleton('core/resource');
                $conn = $resource->getConnection('core_read');
                if (!$conn->isConnected()) {
                    $conn->closeConnection();
                    $conn->getConnection();
                }
                return Mage::getModel($modelClass);
            },
            'getCollection' => function ($modelClass) {
                // Ensure DB connection is fresh
                $resource = Mage::getSingleton('core/resource');
                $conn = $resource->getConnection('core_read');
                if (!$conn->isConnected()) {
                    $conn->closeConnection();
                    $conn->getConnection();
                }
                return Mage::getModel($modelClass)->getCollection();
            },
            'reconnect' => function () {
                // Helper function to manually reconnect
                $resource = Mage::getSingleton('core/resource');
                $connections = ['core_read', 'core_write'];
                foreach ($connections as $connName) {
                    $conn = $resource->getConnection($connName);
                    $conn->closeConnection();
                    $conn->getConnection();
                }
                echo "Database connections refreshed.\n";
            },
        ]);

        $shell->run();

        return Command::SUCCESS;
    }

    private function runBasicRepl(SymfonyStyle $io): int
    {
        $io->text('Starting interactive Maho shell...');
        $io->newLine();

        $readline = function_exists('readline');
        $prompt = 'maho> ';

        while (true) {
            if ($readline) {
                $input = readline($prompt);
                if ($input === false) {
                    break; // Ctrl+D
                }
                if (trim($input) !== '') {
                    readline_add_history($input);
                }
            } else {
                echo $prompt;
                $input = fgets(STDIN);
                if ($input === false) {
                    break;
                }
            }

            $input = trim($input);

            if ($input === 'exit' || $input === 'quit') {
                break;
            }

            if ($input === '') {
                continue;
            }

            // Handle special commands
            if ($input === 'help') {
                $io->text([
                    'Examples:',
                    '  $product = Mage::getModel("catalog/product")->load(1);',
                    '  $customers = Mage::getModel("customer/customer")->getCollection();',
                    '  Mage::getConfig()->getNode("global/install/date");',
                    '',
                    'Type "exit" to quit.',
                ]);
                continue;
            }

            // Add semicolon if missing (unless it's a control structure)
            if (!str_ends_with($input, ';') && !str_ends_with($input, '}') && !str_ends_with($input, '{')) {
                $input .= ';';
            }

            // Replace dd() calls with var_dump() to prevent exit
            $safeInput = preg_replace('/\bdd\s*\(/', 'var_dump(', $input);

            try {
                ob_start();
                $result = eval("return ($safeInput);");
                $output = ob_get_clean();

                if ($output !== '') {
                    echo $output;
                }

                if ($result !== null) {
                    if (is_object($result) || is_array($result)) {
                        print_r($result);
                    } else {
                        var_export($result);
                        echo "\n";
                    }
                }
            } catch (\ParseError $e) {
                // Try without return for statements like echo, print, etc.
                try {
                    ob_start();
                    eval($safeInput);
                    $output = ob_get_clean();
                    if ($output !== '') {
                        echo $output;
                    }
                } catch (\Throwable $e) {
                    $io->error('Error: ' . $e->getMessage());
                }
            } catch (\Throwable $e) {
                $io->error('Error: ' . $e->getMessage());
            }
        }

        $io->newLine();
        $io->text('Goodbye!');

        return Command::SUCCESS;
    }
}
