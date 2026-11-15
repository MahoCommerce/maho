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
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'config:delete',
    description: 'Delete configuration values from core_config_data table',
)]
class ConfigDelete extends BaseMahoCommand
{
    use ConfigCommandTrait;

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(description: 'Configuration ID from core_config_data table (use config:get to find the ID)', name: 'config_id')]
        int $configId,
        #[Option(description: 'Skip confirmation prompt', shortcut: 'f')]
        bool $force = false,
    ): int {
        $this->initMaho();

        /** @var \Mage_Core_Service_Config $service */
        $service = Mage::getService('core/config');
        try {
            $row = $service->getById($configId);
        } catch (\Mage_Core_Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Configuration to delete:</info>');
        $output->writeln('');

        $tableOutput = new Table($output);
        $tableOutput->setHeaders(['Config ID', 'Path', 'Scope', 'Scope ID', 'Name', 'Value']);
        $tableOutput->addRow([
            $row->getId(),
            $row->getPath(),
            $row->getScope(),
            $row->getScopeId(),
            $this->getScopeName((string) $row->getScope(), (int) $row->getScopeId()),
            $row->getValue(),
        ]);
        $tableOutput->render();

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

        try {
            $service->delete((string) $row->getPath(), (string) $row->getScope(), (int) $row->getScopeId());
        } catch (\Mage_Core_Exception $e) {
            $output->writeln('<error>Error deleting configuration: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>Configuration deleted successfully</info>');

        return Command::SUCCESS;
    }
}
