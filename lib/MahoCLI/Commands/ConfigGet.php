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
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'config:get',
    description: 'Get specific configuration path values',
)]
class ConfigGet extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                'Configuration path (e.g., web/url/base, general/store_information/name)',
            )
            ->addOption(
                'scope',
                's',
                InputOption::VALUE_OPTIONAL,
                'Filter by configuration scope (default, websites, stores)',
            )
            ->addOption(
                'scope-id',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Filter by specific scope ID (website ID or store ID)',
            )
            ->addOption(
                'decrypt',
                'd',
                InputOption::VALUE_NONE,
                'Decrypt encrypted values',
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $path = $input->getArgument('path');
        $scopeFilter = $input->getOption('scope');
        $scopeIdFilter = $input->getOption('scope-id');
        $decrypt = $input->getOption('decrypt');

        try {
            $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
            $table = Mage::getSingleton('core/resource')->getTableName('core_config_data');

            // Build query to get all values for this path
            $select = $connection->select()
                ->from($table, ['config_id', 'scope', 'scope_id', 'path', 'value'])
                ->where('path = ?', $path)
                ->order(['scope', 'scope_id']);

            // Apply filters if provided
            if ($scopeFilter) {
                $select->where('scope = ?', $scopeFilter);
            }
            if ($scopeIdFilter !== null) {
                $select->where('scope_id = ?', (int) $scopeIdFilter);
            }

            $results = $connection->fetchAll($select);

            // Also get default value from XML configuration
            $defaultValue = $this->getDefaultValue($path);

            // If no results and no default value
            if (empty($results) && $defaultValue === null) {
                $output->writeln('<comment>Configuration path not found: ' . $path . '</comment>');
                return Command::SUCCESS;
            }

            // Build table data
            $tableData = [];

            // Add default value if exists and not filtered out
            if ($defaultValue !== null && (!$scopeFilter || $scopeFilter === 'default') && (!$scopeIdFilter || $scopeIdFilter == '0')) {
                $tableData[] = [
                    '-',
                    'default',
                    '0',
                    '<comment>[XML Default]</comment>',
                    $this->formatValue($defaultValue),
                ];
            }

            // Add database values
            foreach ($results as $row) {
                $value = $row['value'];

                // Decrypt if requested
                if ($decrypt && is_string($value) && $this->looksEncrypted($value)) {
                    try {
                        $value = Mage::helper('core')->decrypt($value);
                    } catch (\Exception $e) {
                        $value = '<error>[Decryption failed]</error>';
                    }
                }

                $tableData[] = [
                    $row['config_id'],
                    $row['scope'],
                    $row['scope_id'],
                    $this->getScopeName($row['scope'], (int) $row['scope_id']),
                    $this->formatValue($value),
                ];
            }

            // Display results in table
            $output->writeln('<info>Configuration values for: ' . $path . '</info>');
            $output->writeln('');

            $table = new Table($output);
            $table->setHeaders(['Config ID', 'Scope', 'Scope ID', 'Name', 'Value']);
            $table->setRows($tableData);
            $table->render();

        } catch (\Exception $e) {
            $output->writeln('<error>Error retrieving configuration: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function looksEncrypted(string $value): bool
    {
        // Check if value looks like base64 encoded encrypted data
        return preg_match('/^[A-Za-z0-9+\/]+=*$/', $value) && strlen($value) > 20;
    }

    private function formatValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return (string) $value;
    }

    private function getDefaultValue(string $path): mixed
    {
        // Don't show XML defaults since Mage::getConfig()->getNode() returns
        // already-processed values with placeholders replaced, making it
        // indistinguishable from database values
        return null;
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
