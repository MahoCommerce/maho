<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Maho\Browser\Browser;
use Maho\Browser\Runtime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'sys:playwright:install',
    description: 'Install the shared Playwright runtime for the browser-based scanners',
)]
class SysPlaywrightInstall extends BaseMahoCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Option(description: 'Reinstall even when already installed')]
        bool $force = false,
        #[Option(description: 'Browser build to install: headless-shell or chromium. Default: what the registered scanners need')]
        ?string $browser = null,
    ): int {
        $this->initMaho();

        $runtime = new Runtime();
        $issues = $runtime->getRequirementIssues();
        if ($issues !== []) {
            foreach ($issues as $issue) {
                $output->writeln("<error>$issue</error>");
            }
            return Command::FAILURE;
        }

        $packages = [];
        $browsers = [];
        foreach (Runtime::scanners() as $scanner) {
            $packages = array_merge($packages, $scanner->packages());
            $browsers[$scanner->browser()->value] = $scanner->browser();
        }
        if ($browser !== null) {
            try {
                $chosen = Browser::fromOption($browser);
            } catch (\InvalidArgumentException $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                return Command::INVALID;
            }
            $browsers = [$chosen->value => $chosen];
        } elseif ($browsers === []) {
            $browsers = [Browser::HeadlessShell->value => Browser::HeadlessShell];
        }

        $output->writeln('Runtime directory: <comment>' . $runtime->getRuntimeDir() . '</comment>');
        $output->writeln('Browsers directory: <comment>' . $runtime->getBrowsersDir() . '</comment>');

        $pending = $force ? $browsers : array_filter($browsers, fn(Browser $b) => !$runtime->isInstalled($b, $packages));
        if ($pending === []) {
            $output->writeln('<info>The browser runtime is already installed. Use --force to reinstall.</info>');
            return Command::SUCCESS;
        }

        $external = $runtime->findExternalPlaywright();
        if ($external !== null) {
            $output->writeln("Linking the Playwright package found at <comment>$external</comment>");
        } else {
            $output->writeln('Installing Playwright ' . Runtime::getPlaywrightVersion());
        }

        try {
            foreach ($pending as $browser) {
                $output->writeln('Preparing ' . $browser->label() . ', this can take a few minutes...');
                $runtime->install($browser, $packages, $force);
            }
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Browser runtime installed.</info>');
        return Command::SUCCESS;
    }
}
