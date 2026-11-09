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
    use ConfigCommandTrait;

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
        #[Option(description: 'Save a path that no field of System > Configuration declares, with no check of its value')]
        bool $undeclared = false,
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

        if ($encrypt) {
            $value = Mage::helper('core')->encrypt($value);
        }

        try {
            /** @var \Mage_Core_Service_Config $service */
            $service = Mage::getService('core/config');
            $service->save($path, $value, $scope, $scopeId, $undeclared);
        } catch (\Mage_Core_Exception_NoSuchEntity $e) {
            $output->writeln('<error>Error saving configuration: ' . $e->getMessage() . '</error>');
            $output->writeln('<comment>No field of System > Configuration declares this path. Use --undeclared to save it anyway.</comment>');
            return Command::FAILURE;
        } catch (\Mage_Core_Exception $e) {
            $output->writeln('<error>Error saving configuration: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $configId = Mage::getResourceModel('core/config_data_collection')
            ->addFieldToFilter('path', $path)
            ->addFieldToFilter('scope', $scope)
            ->addFieldToFilter('scope_id', $scopeId)
            ->getFirstItem()
            ->getId();

        $output->writeln('<info>Configuration saved successfully</info>');
        $output->writeln('');

        $tableOutput = new Table($output);
        $tableOutput->setHeaders(['Config ID', 'Path', 'Scope', 'Scope ID', 'Name', 'Value']);
        $tableOutput->addRow([
            $configId ?: 'N/A',
            $path,
            $scope,
            $scopeId,
            $this->getScopeName($scope, $scopeId),
            $encrypt ? '<comment>[ENCRYPTED]</comment>' : $value,
        ]);
        $tableOutput->render();

        if ($encrypt) {
            $output->writeln('');
            $output->writeln('<info>Value was encrypted before storing</info>');
        }

        return Command::SUCCESS;
    }
}
