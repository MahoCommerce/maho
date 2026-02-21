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

use Exception;
use Mage;
use Mage_Core_Model_Resource;
use Maho\Db\Adapter\AdapterInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'sys:directory:regions:import',
    description: 'Import states/provinces for a country from ISO 3166-2 standard with localization',
)]
class SysDirectoryRegionsImport extends BaseMahoCommand
{
    private const DATA_BASE_URL = 'https://raw.githubusercontent.com/MahoCommerce/directory-data/main/regions/';

    private mixed $logger = null;

    private function initLogger(?OutputInterface $output = null, ?callable $loggerCallback = null): void
    {
        if ($loggerCallback) {
            $this->logger = $loggerCallback;
        } elseif ($output) {
            $this->logger = function ($message, $level = 'info') use ($output) {
                match ($level) {
                    'error' => $output->writeln("<error>$message</error>"),
                    'comment' => $output->writeln("<comment>$message</comment>"),
                    default => $output->writeln("<info>$message</info>"),
                };
            };
        } else {
            $this->logger = function ($message, $level = 'info') {
                // Silent fallback
            };
        }
    }

    private function log(string $message, string $level = 'info'): void
    {
        if ($this->logger) {
            ($this->logger)($message, $level);
        }
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('country', 'c', InputOption::VALUE_REQUIRED, 'ISO-2 country code (e.g., US, IT, CA)')
            ->addOption('locales', 'l', InputOption::VALUE_OPTIONAL, 'Comma-separated list of Maho locales (e.g., en_US,it_IT)', 'en_US')
            ->addOption('update-existing', 'u', InputOption::VALUE_NONE, 'Update existing regions and localized names (default: only add new locales)')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Preview changes without importing');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->importRegions($input, $output);
    }

    /**
     * Environment-agnostic method for importing regions from backend
     */
    public function importRegionsData(string $countryCode, array $options = [], ?callable $logger = null): array
    {
        $this->initMaho();
        $this->initLogger(null, $logger);

        // Parse options with defaults
        $locales = array_map('trim', explode(',', $options['locales'] ?? 'en_US'));
        $dryRun = $options['dryRun'] ?? false;
        $updateExisting = $options['updateExisting'] ?? false;
        $verbose = $options['verbose'] ?? false;

        return $this->performImport($countryCode, $locales, $dryRun, $updateExisting, $verbose);
    }

    public function importRegions(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();
        $this->initLogger($output);

        $countryCode = strtoupper($input->getOption('country'));
        $locales = array_map('trim', explode(',', $input->getOption('locales')));
        $dryRun = $input->getOption('dry-run');
        $updateExisting = $input->getOption('update-existing');
        $verbose = $output->isVerbose();

        if (!$countryCode) {
            $this->log('Country code is required. Use --country=XX', 'error');
            return Command::FAILURE;
        }

        $result = $this->performImport($countryCode, $locales, $dryRun, $updateExisting, $verbose, $output);

        return $result['success'] ? Command::SUCCESS : Command::FAILURE;
    }

    private function performImport(string $countryCode, array $locales, bool $dryRun, bool $updateExisting, bool $verbose, ?OutputInterface $output = null): array
    {

        // Validate country exists
        $country = Mage::getModel('directory/country')->loadByCode($countryCode);
        if (!$country->getId()) {
            $this->log("Country code '$countryCode' not found in database", 'error');
            return ['success' => false, 'error' => "Country code '$countryCode' not found in database"];
        }

        $this->log("Importing regions for {$country->getName()} ($countryCode)");
        $this->log('Locales: ' . implode(', ', $locales));

        if ($dryRun) {
            $this->log('DRY RUN MODE - No changes will be made', 'comment');
        }

        try {
            // Fetch region data from GitHub
            if ($verbose) {
                $this->log('Fetching region data from GitHub...');
            }
            $regionsData = $this->fetchRegionData($countryCode);

            if (empty($regionsData)) {
                $this->log("No regions data found for $countryCode", 'comment');
                return ['success' => true, 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'total' => 0, 'message' => 'No regions data found'];
            }

            if ($verbose) {
                $this->log('  Found ' . count($regionsData) . ' regions to process');
            }

            // Process regions with their localized names
            $regionsByLocale = [];
            foreach ($locales as $mahoLocale) {
                $localizedNames = $this->getLocalizedNamesFromData($regionsData, $mahoLocale, $verbose);

                // Only keep names that are different from the English default
                $englishNames = $this->getLocalizedNamesFromData($regionsData, 'en_US', $verbose);
                $differentNames = [];

                foreach ($localizedNames as $code => $localizedName) {
                    $defaultName = $englishNames[$code] ?? null;

                    if ($defaultName && $localizedName !== $defaultName) {
                        $differentNames[$code] = $localizedName;
                        if ($verbose) {
                            $this->log("    Keeping $mahoLocale translation for $code: '$defaultName' → '$localizedName'", 'comment');
                        }
                    } elseif ($verbose) {
                        $this->log("    Skipping $mahoLocale for $code: same as English default ('$defaultName')", 'comment');
                    }
                }

                if (!empty($differentNames)) {
                    $regionsByLocale[$mahoLocale] = $differentNames;
                }
            }

        } catch (Exception $e) {
            $this->log("Failed to load region data: {$e->getMessage()}", 'error');
            return ['success' => false, 'error' => $e->getMessage()];
        }

        // Process imports
        if ($verbose) {
            $this->log("\nProcessing " . count($regionsData) . ' regions...');
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;

        $importRecords = [];
        $updateRecords = [];
        $skipRecords = [];

        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');

        // Get English names to use as default
        $englishNames = $this->getLocalizedNamesFromData($regionsData, 'en_US', $verbose);

        foreach ($regionsData as $code => $translations) {
            $defaultName = $englishNames[$code] ?? $translations['en'] ?? null;

            if (!$defaultName) {
                if ($verbose) {
                    $this->log("No English name found for $code, skipping", 'comment');
                }
                $skipped++;
                continue;
            }

            // Check if region already exists
            $existingRegion = Mage::getModel('directory/region')
                ->loadByCode($code, $countryCode);

            if ($existingRegion->getId()) {
                // Process localized names, checking each locale individually
                $localesToProcess = [];
                $hasUpdates = false;

                foreach ($regionsByLocale as $locale => $localizedNames) {
                    if (isset($localizedNames[$code])) {
                        // Check if this localized name already exists
                        $existingLocalizedName = $this->getExistingRegionName((int) $existingRegion->getId(), $locale, $connection);
                        if (!$existingLocalizedName || $updateExisting) {
                            $localesToProcess[$locale] = $localizedNames[$code];
                            $hasUpdates = true;
                        } elseif ($verbose) {
                            $this->log("Skipping $code ($locale): localized name already exists ('$existingLocalizedName')", 'comment');
                        }
                    }
                }

                // Update region name if needed (only when $updateExisting is true)
                $shouldUpdateRegionName = $updateExisting && $existingRegion->getDefaultName() !== $defaultName;

                if ($hasUpdates || $shouldUpdateRegionName) {
                    if (!$dryRun) {
                        if ($shouldUpdateRegionName) {
                            $existingRegion->setDefaultName($defaultName);
                            $existingRegion->save();
                        }

                        // Update localized names
                        foreach ($localesToProcess as $locale => $localizedName) {
                            $this->updateRegionName(
                                (int) $existingRegion->getId(),
                                $locale,
                                $localizedName,
                                $connection,
                            );
                        }
                    } else {
                        $updateRecords[] = ['code' => $code, 'name' => $defaultName, 'existing' => $existingRegion->getDefaultName(), 'locales' => $localesToProcess];
                    }
                    $updated++;
                } else {
                    $skipRecords[] = ['code' => $code, 'name' => $defaultName, 'existing' => $existingRegion->getDefaultName()];
                    $skipped++;
                }
            } else {
                // Insert new region
                if (!$dryRun) {
                    $region = Mage::getModel('directory/region');
                    $region->setCountryId($countryCode);
                    $region->setCode($code);
                    $region->setDefaultName($defaultName);
                    $region->save();

                    // Insert localized names (only for names that differ from English)
                    foreach ($regionsByLocale as $locale => $localizedNames) {
                        if (isset($localizedNames[$code])) {
                            $this->insertRegionName(
                                (int) $region->getId(),
                                $locale,
                                $localizedNames[$code],
                                $connection,
                            );
                        }
                    }
                } else {
                    $localizedInfo = [];
                    foreach ($regionsByLocale as $locale => $localizedNames) {
                        if (isset($localizedNames[$code])) {
                            $localizedInfo[$locale] = $localizedNames[$code];
                        }
                    }
                    $importRecords[] = ['code' => $code, 'name' => $defaultName, 'locales' => $localizedInfo];
                }
                $imported++;
            }
        }

        // Summary
        $this->log("\nImport Summary:");
        if ($output) {
            $table = new Table($output);
            $table->setHeaders(['Action', 'Count']);
            $table->addRow(['Imported', $imported]);
            $table->addRow(['Updated', $updated]);
            $table->addRow(['Skipped', $skipped]);
            $table->addRow(['<info>Total</info>', '<info>' . ($imported + $updated + $skipped) . '</info>']);
            $table->render();

            if ($dryRun) {
                $this->showDryRunDetails($output, $importRecords, $updateRecords, $skipRecords);
            }
        }

        return [
            'success' => true,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => $imported + $updated + $skipped,
            'dryRun' => $dryRun,
            'updateRecords' => $updateRecords,
            'importRecords' => $importRecords,
        ];
    }

    private function fetchRegionData(string $countryCode): array
    {
        try {
            $url = self::DATA_BASE_URL . $countryCode . '.json';
            $client = \Maho\Http\Client::create(['timeout' => 30]);
            $response = $client->request('GET', $url);

            // Check if file exists (404 means no regions for this country)
            if ($response->getStatusCode() === 404) {
                return [];
            }

            $data = $response->getContent();
            $jsonData = Mage::helper('core')->jsonDecode($data);

            if (!is_array($jsonData)) {
                throw new Exception('Invalid JSON data received');
            }

            return $jsonData;
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), '404')) {
                // No regions file for this country - this is normal
                return [];
            }
            $this->log("Failed to fetch region data: {$e->getMessage()}", 'error');
            return [];
        }
    }

    private function getLocalizedNamesFromData(array $regionsData, string $mahoLocale, bool $verbose = false): array
    {
        $localizedNames = [];

        if ($verbose) {
            $this->log("  Getting localized region names for locale: $mahoLocale", 'comment');
        }

        foreach ($regionsData as $code => $translations) {
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

            if ($verbose && isset($localizedNames[$code])) {
                $englishName = $translations['en'] ?? 'N/A';
                if ($englishName !== $localizedNames[$code]) {
                    $this->log("    $code: '$englishName' → '{$localizedNames[$code]}' *TRANSLATED*", 'comment');
                }
            }
        }

        return $localizedNames;
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

    private function getExistingRegionName(int $regionId, string $locale, \Maho\Db\Adapter\AdapterInterface $connection): ?string
    {
        $tableName = $connection->getTableName('directory_country_region_name');
        $select = $connection->select()
            ->from($tableName, ['name'])
            ->where('region_id = ?', $regionId)
            ->where('locale = ?', $locale);

        $result = $connection->fetchOne($select);
        return $result ?: null;
    }

    private function insertRegionName(int $regionId, string $locale, string $name, \Maho\Db\Adapter\AdapterInterface $connection): void
    {
        $connection->insertOnDuplicate(
            $connection->getTableName('directory_country_region_name'),
            [
                'locale' => $locale,
                'region_id' => $regionId,
                'name' => $name,
            ],
            ['name'],
        );
    }

    private function updateRegionName(int $regionId, string $locale, string $name, \Maho\Db\Adapter\AdapterInterface $connection): void
    {
        $this->insertRegionName($regionId, $locale, $name, $connection);
    }
}
