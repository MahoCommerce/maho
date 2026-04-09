<?php

/**
 * Maho
 *
 * @package    MahoCLI
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
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

        // Ensure timezone is properly configured for shell
        $store = Mage::app()->getStore();
        $timezone = $store->getConfig(\Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);

        if (empty($timezone)) {
            // Directly set the config value in the store's configuration cache
            $store->setConfig(
                \Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE,
                \Mage_Core_Model_Locale::DEFAULT_TIMEZONE,
            );
        }

        // Also ensure the config tree has a fallback
        if (!Mage::getConfig()->getNode('default/general/locale/timezone')) {
            Mage::getConfig()->setNode(
                'default/general/locale/timezone',
                \Mage_Core_Model_Locale::DEFAULT_TIMEZONE,
            );
        }

        $io->success('Maho application initialized!');
        $io->text([
            'You can now interact with Maho using PHP code.',
            'Type "exit" or press Ctrl+D to quit.',
            '',
            'Example commands:',
            '  $product = Mage::getModel("catalog/product")->load(1);',
            '  var_dump($product->getId()); // Check if product loaded',
            '  $product->debug(); // Debug product data',
            '  echo "Test output\n"; // Simple output test',
            '',
        ]);

        if ($this->hasPsysh()) {
            return $this->runPsysh($io);
        }

        $io->warning('PsySH is not installed. Using basic REPL instead.');
        $io->text([
            'For a much better experience, install PsySH:',
            '  composer require --dev psy/psysh',
        ]);

        if (!function_exists('readline')) {
            $io->warning('Readline extension is also not available.');
            $io->text([
                'Without readline, you will not have:',
                '  - Command history (up/down arrows)',
                '  - Tab completion',
                '  - Line editing capabilities',
                '',
                'Consider installing php-readline package for better experience.',
            ]);
        }

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
                // Get database connection
                $conn = $resource->getConnection('core_read');
                if (!$conn) {
                    throw new \RuntimeException('Failed to get database connection');
                }
                return $conn;
            },
            // Add some helpful shortcuts
            'getModel' => function ($modelClass) {
                return Mage::getModel($modelClass);
            },
            'getCollection' => function ($modelClass) {
                return Mage::getModel($modelClass)->getCollection();
            },
            'reconnect' => function () {
                // Note: Connection management is handled automatically by Doctrine DBAL
                echo "Note: Connection lifecycle is managed automatically by Doctrine DBAL.\n";
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
        $prompt = '> ';

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
                flush(); // Ensure prompt is displayed immediately
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

            // Add semicolon if missing
            if (!str_ends_with($input, ';')) {
                $input .= ';';
            }

            try {
                ob_start();
                eval($input);
                $output = ob_get_clean();
                if ($output !== '') {
                    echo $output;
                }
            } catch (\Throwable $e) {
                ob_end_clean();
                echo 'Error: ' . $e->getMessage() . "\n";
            }
        }

        $io->newLine();
        $io->text('Goodbye!');

        return Command::SUCCESS;
    }
}
