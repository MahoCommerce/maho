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
    name: 'config:set',
    description: 'Set configuration values in core_config_data table',
)]
class ConfigSet extends BaseMahoCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Argument(description: 'Configuration path (e.g., web/url/base, general/store_information/name)')]
        string $path,
        #[Argument(description: 'Configuration value to set')]
        string $value,
        #[Option(description: 'Configuration scope (default, websites, stores)', shortcut: 's')]
        ?string $scope = null,
        #[Option(description: 'Scope ID (website ID or store ID)', shortcut: 'i')]
        ?int $scopeId = null,
        #[Option(description: 'Encrypt the value before storing', shortcut: 'e')]
        bool $encrypt = false,
    ): int {
        $this->initMaho();

        if (!$scope || $scopeId === null) {
            $output->writeln('<error>Both --scope and --scope-id options are required</error>');
            $output->writeln('<comment>Examples:</comment>');
            $output->writeln('  <info>./maho config:set web/url/base "http://example.com/" --scope default --scope-id 0</info>');
            $output->writeln('  <info>./maho config:set web/url/base "http://store1.com/" --scope stores --scope-id 1</info>');
            $output->writeln('  <info>./maho config:set web/url/base "http://website2.com/" --scope websites --scope-id 2</info>');
            return Command::FAILURE;
        }

        // Validate scope
        if (!in_array($scope, ['default', 'websites', 'stores'])) {
            $output->writeln('<error>Invalid scope. Must be one of: default, websites, stores</error>');
            return Command::FAILURE;
        }

        // Validate scope_id exists
        if (!$this->validateScopeId($scope, $scopeId, $output)) {
            return Command::FAILURE;
        }

        try {
            // Encrypt value if requested
            if ($encrypt) {
                $value = Mage::helper('core')->encrypt($value);
            }

            // Get the configuration model
            $config = Mage::getModel('core/config');

            // Save the configuration value
            $config->saveConfig($path, $value, $scope, $scopeId);

            // Clear configuration cache
            Mage::app()->getCache()->clean(['config']);

            // Get the config_id of the saved configuration
            $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
            $table = Mage::getSingleton('core/resource')->getTableName('core_config_data');

            $select = $connection->select()
                ->from($table, ['config_id'])
                ->where('path = ?', $path)
                ->where('scope = ?', $scope)
                ->where('scope_id = ?', $scopeId);

            $configId = $connection->fetchOne($select);

            // Get scope name
            $scopeName = $this->getScopeName($scope, $scopeId);

            // Display results in table
            $output->writeln('<info>Configuration saved successfully</info>');
            $output->writeln('');

            $tableOutput = new Table($output);
            $tableOutput->setHeaders(['Config ID', 'Path', 'Scope', 'Scope ID', 'Name', 'Value']);
            $tableOutput->addRow([
                $configId ?: 'N/A',
                $path,
                $scope,
                $scopeId,
                $scopeName,
                $encrypt ? '<comment>[ENCRYPTED]</comment>' : $value,
            ]);
            $tableOutput->render();

            if ($encrypt) {
                $output->writeln('');
                $output->writeln('<info>Value was encrypted before storing</info>');
            }

            $output->writeln('');
            $output->writeln('<info>Configuration cache has been cleared</info>');

        } catch (\Exception $e) {
            $output->writeln('<error>Error saving configuration: ' . $e->getMessage() . '</error>');
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
        } catch (\Exception) {
            // Ignore errors when getting names
        }

        return '<comment>[Unknown]</comment>';
    }

    private function validateScopeId(string $scope, int $scopeId, OutputInterface $output): bool
    {
        // Default scope with ID 0 is always valid
        if ($scope === 'default' && $scopeId === 0) {
            return true;
        }

        // Default scope must have ID 0
        if ($scope === 'default' && $scopeId !== 0) {
            $output->writeln('<error>Default scope must have scope_id 0</error>');
            return false;
        }

        // Check if website exists
        if ($scope === 'websites') {
            $website = Mage::getModel('core/website')->load($scopeId);
            if (!$website || !$website->getId()) {
                $output->writeln('<error>Website with ID ' . $scopeId . ' does not exist</error>');
                $output->writeln('<comment>Available websites:</comment>');

                $websites = Mage::getModel('core/website')->getCollection();
                foreach ($websites as $web) {
                    $output->writeln(sprintf('  ID: %d - %s (%s)', $web->getId(), $web->getName(), $web->getCode()));
                }
                return false;
            }
        }

        // Check if store exists
        if ($scope === 'stores') {
            $store = Mage::getModel('core/store')->load($scopeId);
            if (!$store || !$store->getId()) {
                $output->writeln('<error>Store with ID ' . $scopeId . ' does not exist</error>');
                $output->writeln('<comment>Available stores:</comment>');

                $stores = Mage::getModel('core/store')->getCollection();
                foreach ($stores as $st) {
                    $output->writeln(sprintf('  ID: %d - %s (%s)', $st->getId(), $st->getName(), $st->getCode()));
                }
                return false;
            }
        }

        return true;
    }
}
