<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'accessibility:install',
    description: 'Install the accessibility scanner runtime (Playwright + Chromium)',
)]
class AccessibilityInstall extends BaseMahoCommand
{
    #[\Override]
    public function isEnabled(): bool
    {
        return $this->isModuleActive('Maho_AccessibilityScan');
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Reinstall even when already installed');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $helper = Mage::helper('accessibilityscan');
        $issues = $helper->getRequirementIssues();
        if ($issues !== []) {
            foreach ($issues as $issue) {
                $output->writeln("<error>$issue</error>");
            }
            return Command::FAILURE;
        }

        $force = (bool) $input->getOption('force');
        if (!$force && $helper->isPlaywrightInstalled()) {
            $output->writeln('<info>The scanner runtime is already installed. Use --force to reinstall.</info>');
            return Command::SUCCESS;
        }

        $output->writeln('Installing Playwright and Chromium (roughly 300 MB), this can take a few minutes...');
        try {
            Mage::getModel('accessibilityscan/runner')->installPlaywright($force);
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Scanner runtime installed.</info>');
        return Command::SUCCESS;
    }
}
