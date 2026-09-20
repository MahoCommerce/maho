<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'config:get',
    description: 'Get specific configuration path values',
)]
class ConfigGet extends BaseMahoCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Configuration path (e.g., web/url/base, general/store_information/name)')]
        string $path,
        #[Option(description: 'Filter by configuration scope (default, websites, stores)', name: 'scope', shortcut: 's')]
        ?string $scopeFilter = null,
        #[Option(description: 'Filter by specific scope ID (website ID or store ID)', name: 'scope-id', shortcut: 'i')]
        ?int $scopeIdFilter = null,
        #[Option(description: 'Decrypt encrypted values', shortcut: 'd')]
        bool $decrypt = false,
    ): int {
        $this->initMaho();

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
                $select->where('scope_id = ?', $scopeIdFilter);
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
            if ($defaultValue !== null && (!$scopeFilter || $scopeFilter === 'default') && (!$scopeIdFilter)) {
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
                    } catch (\Exception) {
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
        } catch (\Exception) {
            // Ignore errors when getting names
        }

        return '<comment>[Unknown]</comment>';
    }
}
