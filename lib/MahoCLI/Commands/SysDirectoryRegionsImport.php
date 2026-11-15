<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace MahoCLI\Commands;

use Mage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'sys:directory:regions:import',
    description: 'Import states/provinces for a country from ISO 3166-2 standard with localization',
)]
class SysDirectoryRegionsImport extends BaseMahoCommand
{
    public function __invoke(
        OutputInterface $output,
        #[Option(description: 'ISO-2 country code (e.g., US, IT, CA)', shortcut: 'c')]
        ?string $country = null,
        #[Option(description: 'Comma-separated list of Maho locales (e.g., en_US,it_IT)', shortcut: 'l')]
        string $locales = 'en_US',
        #[Option(description: 'Update existing regions and localized names (default: only add new locales)', shortcut: 'u')]
        bool $updateExisting = false,
        #[Option(description: 'Preview changes without importing', shortcut: 'd')]
        bool $dryRun = false,
    ): int {
        $this->initMaho();
        if (trim((string) $country) === '') {
            $output->writeln('<error>Country code is required. Use --country=XX</error>');
            return Command::FAILURE;
        }

        $log = function (string $message, string $level) use ($output): void {
            match ($level) {
                'error' => $output->writeln("<error>$message</error>"),
                'comment' => $output->writeln("<comment>$message</comment>"),
                'debug' => $output->isVerbose() ? $output->writeln("<comment>$message</comment>") : null,
                default => $output->writeln("<info>$message</info>"),
            };
        };
        try {
            /** @var \Mage_Directory_Service_Region $service */
            $service = Mage::getService('directory/region');
            $result = $service->import((string) $country, array_map(trim(...), explode(',', $locales)), $updateExisting, $dryRun, $log);
        } catch (\Mage_Core_Exception|\RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $output->writeln("\n<info>Import Summary:</info>");
        $table = new Table($output);
        $table->setHeaders(['Action', 'Count']);
        $table->addRow(['Imported', $result['imported']]);
        $table->addRow(['Updated', $result['updated']]);
        $table->addRow(['Skipped', $result['skipped']]);
        $table->addRow(['<info>Total</info>', "<info>{$result['total']}</info>"]);
        $table->render();

        if ($dryRun) {
            $this->showDryRunDetails($output, $result['importRecords'], $result['updateRecords'], $result['skipRecords']);
        }
        return Command::SUCCESS;
    }

    private function showDryRunDetails(OutputInterface $output, array $importRecords, array $updateRecords, array $skipRecords): void
    {
        if (!empty($importRecords)) {
            $output->writeln("\n<info>Regions to be imported:</info>");
            $table = new Table($output);

            // Build headers dynamically based on locales that have translations
            $headers = ['Code', 'Name (English)'];
            $allLocales = [];
            foreach ($importRecords as $record) {
                $allLocales = array_merge($allLocales, array_keys($record['locales']));
            }
            $allLocales = array_unique($allLocales);
            sort($allLocales);

            // Only show non-English locales in separate columns
            $nonEnglishLocales = array_filter($allLocales, fn($locale) => $locale !== 'en_US');

            foreach ($nonEnglishLocales as $locale) {
                $headers[] = $locale . ' (translation)';
            }
            $table->setHeaders($headers);

            foreach ($importRecords as $record) {
                $row = [$record['code'], $record['name']];
                foreach ($nonEnglishLocales as $locale) {
                    $translation = $record['locales'][$locale] ?? null;
                    $row[] = $translation ?: '-';
                }
                $table->addRow($row);
            }
            $table->render();
        }

        if (!empty($updateRecords)) {
            $output->writeln("\n<info>Regions to be updated:</info>");
            $table = new Table($output);

            // Build headers dynamically based on locales that have translations
            $headers = ['Code', 'Current Name'];
            $allLocales = [];
            $hasMainNameChanges = false;

            foreach ($updateRecords as $record) {
                // Check if the main name (English) is changing
                if ($record['existing'] !== $record['name']) {
                    $hasMainNameChanges = true;
                }

                if (isset($record['locales'])) {
                    $allLocales = array_merge($allLocales, array_keys($record['locales']));
                }
            }

            // Only add "New Name (English)" column if there are actual main name changes
            if ($hasMainNameChanges) {
                $headers[] = 'New Name (English)';
            }

            $allLocales = array_unique($allLocales);
            sort($allLocales);

            // Only show non-English locales in separate columns
            $nonEnglishLocales = array_filter($allLocales, fn($locale) => $locale !== 'en_US');

            foreach ($nonEnglishLocales as $locale) {
                $headers[] = $locale . ' (translation)';
            }
            $table->setHeaders($headers);

            foreach ($updateRecords as $record) {
                $row = [$record['code'], $record['existing']];

                // Only show new English name if it's actually different
                if ($hasMainNameChanges) {
                    $newName = ($record['existing'] !== $record['name'])
                        ? $record['name']
                        : '-';
                    $row[] = $newName;
                }

                // Show translations for non-English locales
                foreach ($nonEnglishLocales as $locale) {
                    $translation = $record['locales'][$locale] ?? null;
                    $row[] = $translation ?: '-';
                }
                $table->addRow($row);
            }
            $table->render();
        }

        if (!empty($skipRecords) && $output->isVerbose()) {
            $output->writeln("\n<info>Regions to be skipped (already exist):</info>");
            $table = new Table($output);
            $table->setHeaders(['Code', 'Name']);
            foreach ($skipRecords as $record) {
                $table->addRow([$record['code'], $record['existing']]);
            }
            $table->render();
        }

        $output->writeln("\n<comment>This was a dry run. Use without --dry-run to apply changes.</comment>");
    }
}
