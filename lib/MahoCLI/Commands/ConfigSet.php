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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'config:set',
    description: 'Set configuration values in core_config_data table',
)]
class ConfigSet extends BaseMahoCommand
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
            ->addArgument(
                'value',
                InputArgument::REQUIRED,
                'Configuration value to set',
            )
            ->addOption(
                'scope',
                's',
                InputOption::VALUE_REQUIRED,
                'Configuration scope (default, websites, stores)',
            )
            ->addOption(
                'scope-id',
                'i',
                InputOption::VALUE_REQUIRED,
                'Scope ID (website ID or store ID)',
            )
            ->addOption(
                'encrypt',
                'e',
                InputOption::VALUE_NONE,
                'Encrypt the value before storing',
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $path = $input->getArgument('path');
        $value = $input->getArgument('value');
        $scope = $input->getOption('scope');
        $scopeId = $input->getOption('scope-id');
        $encrypt = $input->getOption('encrypt');

        // Check if required options are provided
        if (!$scope || $scopeId === null) {
            $output->writeln('<error>Both --scope and --scope-id options are required</error>');
            $output->writeln('<comment>Examples:</comment>');
            $output->writeln('  <info>./maho config:set web/url/base "http://example.com/" --scope default --scope-id 0</info>');
            $output->writeln('  <info>./maho config:set web/url/base "http://store1.com/" --scope stores --scope-id 1</info>');
            $output->writeln('  <info>./maho config:set web/url/base "http://website2.com/" --scope websites --scope-id 2</info>');
            return Command::FAILURE;
        }

        $scopeId = (int) $scopeId;

        // Validate scope
        if (!in_array($scope, ['default', 'websites', 'stores'])) {
            $output->writeln('<error>Invalid scope. Must be one of: default, websites, stores</error>');
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

            $output->writeln('<info>Configuration saved successfully</info>');
            $output->writeln('<comment>Path:</comment> ' . $path);
            $output->writeln('<comment>Scope:</comment> ' . $scope . ' (ID: ' . $scopeId . ')');
            $output->writeln('<comment>Value:</comment> ' . ($encrypt ? '[ENCRYPTED]' : $value));

            if ($encrypt) {
                $output->writeln('<info>Value was encrypted before storing</info>');
            }

            $output->writeln('<info>Configuration cache has been cleared</info>');

        } catch (\Exception $e) {
            $output->writeln('<error>Error saving configuration: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
