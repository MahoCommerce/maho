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

use Exception;
use Mage;
use Mage_Core_Model_Resource;
use Varien_Db_Adapter_Interface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'sys:directory:countries:import',
    description: 'Import country names with localization from ISO 3166-1 standard',
)]
class SysDirectoryCountriesImport extends BaseMahoCommand
{
    private const DATA_URL = 'https://raw.githubusercontent.com/MahoCommerce/directory-data/main/countries.json';

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('locales', 'l', InputOption::VALUE_OPTIONAL, 'Comma-separated list of Maho locales (e.g., en_US,it_IT)', 'en_US')
            ->addOption('update-existing', 'u', InputOption::VALUE_NONE, 'Update existing localized names (default: only add new locales)')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Preview changes without importing');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->importCountries($input, $output);
    }

    public function importCountries(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $locales = array_map('trim', explode(',', $input->getOption('locales')));
        $dryRun = $input->getOption('dry-run');
        $updateExisting = $input->getOption('update-existing');

        $output->writeln('<info>Importing country names with localization</info>');
        $output->writeln('<info>Locales: ' . implode(', ', $locales) . '</info>');

        if ($dryRun) {
            $output->writeln('<comment>DRY RUN MODE - No changes will be made</comment>');
        }

        try {
            // Fetch country data from GitHub
            $output->writeln('<info>Fetching country data from GitHub...</info>');
            $countriesData = $this->fetchCountryData($output);

            if (empty($countriesData)) {
                $output->writeln('<comment>No countries data found</comment>');
                return Command::FAILURE;
            }

            // Get all existing countries from Maho database to process
            $mahoCountries = Mage::getResourceModel('directory/country_collection');
            $countriesToProcess = [];

            foreach ($mahoCountries as $mahoCountry) {
                $countryCode = $mahoCountry->getCountryId();
                if (isset($countriesData[$countryCode])) {
                    $countriesToProcess[$countryCode] = $countriesData[$countryCode];
                } elseif ($output->isVerbose()) {
                    $output->writeln("<comment>Country $countryCode not found in data source</comment>");
                }
            }

            if (empty($countriesToProcess)) {
                $output->writeln('<comment>No countries to process</comment>');
                return Command::SUCCESS;
            }

            $output->writeln('  Found ' . count($countriesToProcess) . ' countries to process');

            // Process countries with their localized names
            $countriesByLocale = [];
            foreach ($locales as $mahoLocale) {
                $localizedNames = $this->getLocalizedNamesFromData($countriesToProcess, $mahoLocale, $output);

                if ($mahoLocale === 'en_US') {
                    // For English locale, include all names (for directory_country_name table)
                    $countriesByLocale[$mahoLocale] = $localizedNames;
                } else {
                    // For non-English locales, only keep names that are different from the English default
                    $englishNames = $this->getLocalizedNamesFromData($countriesToProcess, 'en_US', $output);
                    $differentNames = [];

                    foreach ($localizedNames as $code => $localizedName) {
                        $defaultName = $englishNames[$code] ?? null;

                        if ($defaultName && $localizedName !== $defaultName) {
                            $differentNames[$code] = $localizedName;
                            if ($output->isVerbose()) {
                                $output->writeln("<comment>    Keeping $mahoLocale translation for $code: '$defaultName' → '$localizedName'</comment>");
                            }
                        } elseif ($output->isVerbose()) {
                            $output->writeln("<comment>    Skipping $mahoLocale for $code: same as English default ('$defaultName')</comment>");
                        }
                    }

                    if (!empty($differentNames)) {
                        $countriesByLocale[$mahoLocale] = $differentNames;
                    }
                }
            }

        } catch (Exception $e) {
            $output->writeln("<error>Failed to load country data: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        // Process imports
        $output->writeln("\n<info>Processing " . count($countriesToProcess) . ' countries...</info>');

        $updated = 0;
        $skipped = 0;

        $updateRecords = [];

        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');

        // Get English names to use as default
        $englishNames = $this->getLocalizedNamesFromData($countriesToProcess, 'en_US', $output);

        foreach ($countriesToProcess as $code => $countryData) {
            $defaultName = $englishNames[$code] ?? $countryData['en'] ?? null;

            if (!$defaultName) {
                if ($output->isVerbose()) {
                    $output->writeln("<comment>No English name found for $code, skipping</comment>");
                }
                $skipped++;
                continue;
            }

            // Check if country already exists in Maho
            $existingCountry = Mage::getModel('directory/country')->loadByCode($code);

            if ($existingCountry->getId()) {
                $hasUpdates = false;

                // Update main country name if using English locale and name differs
                $shouldUpdateMainName = false;
                if (in_array('en_US', $locales) && $existingCountry->getName() !== $defaultName) {
                    $shouldUpdateMainName = true;
                    $hasUpdates = true;
                }

                // Process localized names, but respect existing ones unless --update-existing is used
                $localesToProcess = [];
                foreach ($countriesByLocale as $locale => $localizedNames) {
                    if (isset($localizedNames[$code])) {
                        // Check if this localized name already exists
                        $existingLocalizedName = $this->getExistingCountryName($code, $locale, $connection);
                        if (!$existingLocalizedName || $updateExisting) {
                            $localesToProcess[$locale] = $localizedNames[$code];
                            $hasUpdates = true;
                        } elseif ($output->isVerbose()) {
                            $output->writeln("<comment>Skipping $code ($locale): localized name already exists ('$existingLocalizedName')</comment>");
                        }
                    }
                }

                if ($hasUpdates) {
                    if (!$dryRun) {
                        // Update main country name if needed
                        if ($shouldUpdateMainName) {
                            $existingCountry->setName($defaultName);
                            $existingCountry->save();
                        }

                        // Update localized names
                        foreach ($localesToProcess as $locale => $localizedName) {
                            $this->updateCountryName($code, $locale, $localizedName, $connection);
                        }
                    } else {
                        $updateRecords[] = [
                            'code' => $code,
                            'name' => $defaultName,
                            'existing' => $existingCountry->getName(),
                            'locales' => $localesToProcess,
                            'updateMainName' => $shouldUpdateMainName,
                        ];
                    }
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                // Country doesn't exist in Maho - this is unusual, we'll skip it
                $output->writeln("<comment>Country $code not found in Maho database, skipping</comment>");
                $skipped++;
            }
        }

        // Summary
        $output->writeln("\n<info>Import Summary:</info>");
        $table = new Table($output);
        $table->setHeaders(['Action', 'Count']);
        $table->addRow(['Updated', $updated]);
        $table->addRow(['Skipped', $skipped]);
        $table->addRow(['<info>Total</info>', '<info>' . ($updated + $skipped) . '</info>']);
        $table->render();

        if ($dryRun) {
            $this->showDryRunDetails($output, [], $updateRecords, []);
        }

        return Command::SUCCESS;
    }

    private function fetchCountryData(OutputInterface $output): array
    {
        try {
            $client = \Symfony\Component\HttpClient\HttpClient::create(['timeout' => 30]);
            $response = $client->request('GET', self::DATA_URL);
            $data = $response->getContent();

            $jsonData = Mage::helper('core')->jsonDecode($data);

            if (!is_array($jsonData)) {
                throw new Exception('Invalid JSON data received');
            }

            return $jsonData;
        } catch (Exception $e) {
            $output->writeln("<error>Failed to fetch country data: {$e->getMessage()}</error>");
            return [];
        }
    }

    private function getLocalizedNamesFromData(array $countriesData, string $mahoLocale, OutputInterface $output): array
    {
        $localizedNames = [];

        if ($output->isVerbose()) {
            $output->writeln("<comment>  Getting localized country names for locale: $mahoLocale</comment>");
        }

        foreach ($countriesData as $code => $translations) {
            // Try exact locale match first (e.g., en_US)
            if (isset($translations[$mahoLocale])) {
                $localizedNames[$code] = $translations[$mahoLocale];
            }
            // Fall back to language code only (e.g., en from en_US)
            else {
                $languageCode = strtok($mahoLocale, '_');
                if (isset($translations[$languageCode])) {
                    $localizedNames[$code] = $translations[$languageCode];
                }
                // Fall back to English if available
                elseif (isset($translations['en'])) {
                    $localizedNames[$code] = $translations['en'];
                }
                // Use first available translation as last resort
                elseif (!empty($translations)) {
                    $localizedNames[$code] = reset($translations);
                }
            }

            if ($output->isVerbose() && isset($localizedNames[$code])) {
                $englishName = $translations['en'] ?? 'N/A';
                if ($englishName !== $localizedNames[$code]) {
                    $output->writeln("<comment>    $code: '$englishName' → '{$localizedNames[$code]}' *TRANSLATED*</comment>");
                }
            }
        }

        return $localizedNames;
    }

    private function showDryRunDetails(OutputInterface $output, array $importRecords, array $updateRecords, array $skipRecords): void
    {
        if (!empty($updateRecords)) {
            $output->writeln("\n<info>Country names to be updated:</info>");
            $table = new Table($output);

            // Build headers dynamically based on locales that have translations
            $headers = ['Code', 'Current Name'];
            $allLocales = [];
            $hasMainNameChanges = false;

            foreach ($updateRecords as $record) {
                // Check if the main name (English) is changing
                if ($record['updateMainName'] && $record['existing'] !== $record['name']) {
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
                    $newName = ($record['updateMainName'] && $record['existing'] !== $record['name'])
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
            $output->writeln("\n<info>Countries to be skipped (not updating):</info>");
            $table = new Table($output);
            $table->setHeaders(['Code', 'Name']);
            foreach ($skipRecords as $record) {
                $table->addRow([$record['code'], $record['existing']]);
            }
            $table->render();
        }

        $output->writeln("\n<comment>This was a dry run. Use without --dry-run to apply changes.</comment>");
    }

    private function getExistingCountryName(string $countryCode, string $locale, Varien_Db_Adapter_Interface $connection): ?string
    {
        $tableName = $connection->getTableName('directory_country_name');
        $select = $connection->select()
            ->from($tableName, ['name'])
            ->where('country_id = ?', $countryCode)
            ->where('locale = ?', $locale);

        $result = $connection->fetchOne($select);
        return $result ?: null;
    }

    private function updateCountryName(string $countryCode, string $locale, string $name, Varien_Db_Adapter_Interface $connection): void
    {
        $tableName = $connection->getTableName('directory_country_name');
        $connection->insertOnDuplicate(
            $tableName,
            [
                'locale' => $locale,
                'country_id' => $countryCode,
                'name' => $name,
            ],
            ['name'],
        );
    }

}
