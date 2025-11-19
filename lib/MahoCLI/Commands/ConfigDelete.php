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
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'config:delete',
    description: 'Delete configuration values from core_config_data table',
)]
class ConfigDelete extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'config_id',
                InputArgument::REQUIRED,
                'Configuration ID from core_config_data table (use config:get to find the ID)',
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Skip confirmation prompt',
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $configId = (int) $input->getArgument('config_id');
        $force = $input->getOption('force');

        try {
            $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
            $table = Mage::getSingleton('core/resource')->getTableName('core_config_data');

            // Check if the configuration exists
            $select = $connection->select()
                ->from($table)
                ->where('config_id = ?', $configId);

            $existing = $connection->fetchRow($select);

            if (!$existing) {
                $output->writeln('<error>Configuration with ID ' . $configId . ' not found in database</error>');
                return Command::FAILURE;
            }

            // Show what will be deleted in a table
            $output->writeln('<info>Configuration to delete:</info>');
            $output->writeln('');

            $tableOutput = new Table($output);
            $tableOutput->setHeaders(['Config ID', 'Path', 'Scope', 'Scope ID', 'Name', 'Value']);
            $tableOutput->addRow([
                $existing['config_id'],
                $existing['path'],
                $existing['scope'],
                $existing['scope_id'],
                $this->getScopeName($existing['scope'], (int) $existing['scope_id']),
                $existing['value'],
            ]);
            $tableOutput->render();

            // Confirm deletion unless --force is used
            if (!$force) {
                $output->writeln('');
                /** @var QuestionHelper $helper */
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    '<question>Are you sure you want to delete this configuration? [y/N]</question> ',
                    false,
                );

                if (!$helper->ask($input, $output, $question)) {
                    $output->writeln('<comment>Deletion cancelled</comment>');
                    return Command::SUCCESS;
                }
            }


            // Delete the configuration
            $connection->delete(
                $table,
                ['config_id = ?' => $configId],
            );

            // Clear configuration cache
            Mage::app()->getCache()->clean(['config']);

            $output->writeln('');
            $output->writeln('<info>Configuration deleted successfully</info>');
            $output->writeln('<info>Configuration cache has been cleared</info>');

        } catch (\Exception $e) {
            $output->writeln('<error>Error deleting configuration: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function getScopeName(string $scope, int $scopeId): string
    {
        if ($scopeId === 0) {
            return 'Default Config';
        }

        try {
            switch ($scope) {
                case 'websites':
                    $website = Mage::getModel('core/website')->load($scopeId);
                    if ($website && $website->getId()) {
                        return $website->getName() . ' (' . $website->getCode() . ')';
                    }
                    break;

                case 'stores':
                    $store = Mage::getModel('core/store')->load($scopeId);
                    if ($store && $store->getId()) {
                        return $store->getName() . ' (' . $store->getCode() . ')';
                    }
                    break;
            }
        } catch (\Exception $e) {
            // Ignore errors when getting names
        }

        return '<comment>[Unknown]</comment>';
    }
}
