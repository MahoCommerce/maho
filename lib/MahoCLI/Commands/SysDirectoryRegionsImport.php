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
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'sys:directory:regions:import',
    description: 'Import states/provinces for a country from ISO 3166-2 standard with localization',
)]
class SysDirectoryRegionsImport extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('country', 'c', InputOption::VALUE_REQUIRED, 'ISO-2 country code (e.g., US, IT, CA)')
            ->addOption('locales', 'l', InputOption::VALUE_OPTIONAL, 'Comma-separated list of Maho locales (e.g., en_US,it_IT)', 'en_US')
            ->addOption('update-existing', 'u', InputOption::VALUE_NONE, 'Update existing regions (default: skip)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation for package installation')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Preview changes without importing');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $countryCode = strtoupper($input->getOption('country'));
        $locales = array_map('trim', explode(',', $input->getOption('locales')));
        $dryRun = $input->getOption('dry-run');
        $updateExisting = $input->getOption('update-existing');
        $force = $input->getOption('force');

        if (!$countryCode) {
            $output->writeln('<error>Country code is required. Use --country=XX</error>');
            return Command::FAILURE;
        }

        // Check if this is a re-execution after package installation
        $isReExecution = getenv('MAHO_ISO_REEXEC') === 'true';
        $packagesInstalledByUs = getenv('MAHO_ISO_INSTALLED') === 'true';

        // Check if required packages are available
        $packagesWereAlreadyPresent = class_exists('Sokil\IsoCodes\IsoCodesFactory')
            && class_exists('Sokil\IsoCodes\TranslationDriver\SymfonyTranslationDriver')
            && class_exists('Symfony\Component\Translation\Translator');

        if (!$packagesWereAlreadyPresent) {
            $output->writeln('<comment>Required packages are not installed:</comment>');
            $output->writeln('  - sokil/php-isocodes');
            $output->writeln('  - sokil/php-isocodes-db-i18n');
            $output->writeln('  - symfony/translation');
            $output->writeln('');
            $output->writeln('These packages will be temporarily installed for this operation');
            $output->writeln('and automatically removed when the command completes.');

            if (!$force) {
                /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion('<question>Do you want to proceed with the installation? (yes/no) [yes]:</question> ', true);

                if (!$helper->ask($input, $output, $question)) {
                    $output->writeln('<comment>Installation cancelled.</comment>');
                    return Command::SUCCESS;
                }
            }

            $output->writeln('<info>Installing required ISO codes packages...</info>');

            // Install packages
            if (!$this->installIsoPackages($output)) {
                return Command::FAILURE;
            }

            // Re-execute the command with packages installed
            $output->writeln('<info>Re-executing command with packages installed...</info>');
            $exitCode = $this->reExecuteCommand($input, $output, true);

            // Clean up packages after re-execution completes
            $output->writeln('<info>Removing temporary ISO codes packages...</info>');
            $this->removeIsoPackages($output);

            return $exitCode;
        }

        // Map country codes to ISO 3166-2 codes if different
        $isoCountryCode = $this->mapToIsoCode($countryCode);

        // Validate country exists
        $country = Mage::getModel('directory/country')->loadByCode($countryCode);
        if (!$country->getId()) {
            $output->writeln("<error>Country code '$countryCode' not found in database</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Importing regions for {$country->getName()} ($countryCode)</info>");
        if ($isoCountryCode !== $countryCode) {
            $output->writeln("<info>Using ISO code: $isoCountryCode</info>");
        }
        $output->writeln('<info>Locales: ' . implode(', ', $locales) . '</info>');

        if ($dryRun) {
            $output->writeln('<comment>DRY RUN MODE - No changes will be made</comment>');
        }

        try {
            // Get subdivisions from ISO codes
            $isoCodes = new \Sokil\IsoCodes\IsoCodesFactory(); // @phpstan-ignore class.notFound
            $subDivisions = $isoCodes->getSubdivisions(); // @phpstan-ignore class.notFound

            $subdivisions = $this->collectSubdivisions($subDivisions, $isoCountryCode, $output);

            if (empty($subdivisions)) {
                $output->writeln("<comment>No subdivisions found for $countryCode</comment>");
                // Clean up packages if we installed them
                if ($packagesInstalledByUs && !$isReExecution) {
                    $this->removeIsoPackages($output);
                }
                return Command::SUCCESS;
            }

            $output->writeln('  Found ' . count($subdivisions) . ' subdivisions to import');

            // Get English names first to use as default
            $englishNames = $this->getLocalizedNames($subdivisions, 'en_US', $output);

            // Update subdivision data to use English names as default where available
            foreach ($subdivisions as &$subdivision) {
                $code = $subdivision['code'];
                if (isset($englishNames[$code])) {
                    if ($output->isVerbose() && $subdivision['name'] !== $englishNames[$code]) {
                        $output->writeln("<comment>  Using English as default for $code: '{$subdivision['name']}' → '{$englishNames[$code]}'</comment>");
                    }
                    $subdivision['name'] = $englishNames[$code];
                }
            }
            unset($subdivision); // Break the reference

            // Get localized names for each locale (only different from English default)
            $subdivisionsByLocale = [];
            foreach ($locales as $mahoLocale) {
                $localizedNames = $this->getLocalizedNames($subdivisions, $mahoLocale, $output);

                // Only keep names that are different from the English default
                $differentNames = [];
                foreach ($localizedNames as $code => $localizedName) {
                    $englishName = $englishNames[$code] ?? null;

                    // Find the subdivision data to get the (now English) default name
                    $defaultName = null;
                    foreach ($subdivisions as $subdivision) {
                        if ($subdivision['code'] === $code) {
                            $defaultName = $subdivision['name'];
                            break;
                        }
                    }

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
                    $subdivisionsByLocale[$mahoLocale] = $differentNames;
                }
            }

        } catch (Exception $e) {
            $output->writeln("<error>Failed to load ISO subdivision data: {$e->getMessage()}</error>");
            // Clean up packages if we installed them
            if ($packagesInstalledByUs && !$isReExecution) {
                $this->removeIsoPackages($output);
            }
            return Command::FAILURE;
        }

        // Process imports
        $output->writeln("\n<info>Processing " . count($subdivisions) . ' regions...</info>');

        $imported = 0;
        $updated = 0;
        $skipped = 0;

        $importRecords = [];
        $updateRecords = [];
        $skipRecords = [];

        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');

        foreach ($subdivisions as $subdivision) {

            $code = $subdivision['code'];
            $defaultName = $subdivision['name'];

            // Check if region already exists
            $existingRegion = Mage::getModel('directory/region')
                ->loadByCode($code, $countryCode);

            if ($existingRegion->getId()) {
                if (!$updateExisting) {
                    $skipRecords[] = ['code' => $code, 'name' => $defaultName, 'existing' => $existingRegion->getDefaultName()];
                    $skipped++;
                    continue;
                }

                // Update existing region
                if (!$dryRun) {
                    $existingRegion->setDefaultName($defaultName);
                    $existingRegion->save();

                    // Update localized names (only for names that differ from English)
                    foreach ($subdivisionsByLocale as $locale => $localizedNames) {
                        if (isset($localizedNames[$code])) {
                            $this->updateRegionName(
                                $existingRegion->getId(),
                                $locale,
                                $localizedNames[$code],
                                $connection,
                            );
                        }
                    }
                } else {
                    $localizedInfo = [];
                    foreach ($subdivisionsByLocale as $locale => $localizedNames) {
                        if (isset($localizedNames[$code])) {
                            $localizedInfo[$locale] = $localizedNames[$code];
                        }
                    }
                    $updateRecords[] = ['code' => $code, 'name' => $defaultName, 'existing' => $existingRegion->getDefaultName(), 'locales' => $localizedInfo];
                }
                $updated++;
            } else {
                // Insert new region
                if (!$dryRun) {
                    $region = Mage::getModel('directory/region');
                    $region->setCountryId($countryCode);
                    $region->setCode($code);
                    $region->setDefaultName($defaultName);
                    $region->save();

                    // Insert localized names (only for names that differ from English)
                    foreach ($subdivisionsByLocale as $locale => $localizedNames) {
                        if (isset($localizedNames[$code])) {
                            $this->insertRegionName(
                                $region->getId(),
                                $locale,
                                $localizedNames[$code],
                                $connection,
                            );
                        }
                    }
                } else {
                    $localizedInfo = [];
                    foreach ($subdivisionsByLocale as $locale => $localizedNames) {
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
        $output->writeln("\n<info>Import Summary:</info>");
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

        // Clean up packages if we installed them (only if not a re-execution)
        if ($packagesInstalledByUs && !$isReExecution) {
            $output->writeln('<info>Removing temporary ISO codes packages...</info>');
            $this->removeIsoPackages($output);
        }

        return Command::SUCCESS;
    }

    private function collectSubdivisions(\Sokil\IsoCodes\Database\SubdivisionsInterface $subDivisions, string $countryCode, OutputInterface $output): array // @phpstan-ignore class.notFound
    {
        // First pass: collect all subdivisions and analyze the hierarchy
        $allSubdivisions = $this->getAllSubdivisions($subDivisions, $countryCode);

        if (empty($allSubdivisions)) {
            return [];
        }

        // Second pass: determine what to import based on hierarchy analysis
        return $this->filterSubdivisionsByHierarchy($allSubdivisions, $output);
    }

    private function getAllSubdivisions(\Sokil\IsoCodes\Database\SubdivisionsInterface $subDivisions, string $countryCode): array // @phpstan-ignore class.notFound
    {
        $allSubdivisions = [];
        $seen = [];

        // Check numeric codes (1-999)
        for ($i = 1; $i <= 999; $i++) {
            $testCodes = [
                sprintf('%s-%02d', $countryCode, $i),  // IT-01, IT-02, etc.
                sprintf('%s-%03d', $countryCode, $i),  // US-001, etc.
            ];

            foreach ($testCodes as $testCode) {
                $this->tryAddSubdivision($subDivisions, $testCode, $allSubdivisions, $seen);
            }
        }

        // Check letter-based codes
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        // Single letters (rare but possible)
        for ($i = 0; $i < 26; $i++) {
            $testCode = $countryCode . '-' . $letters[$i];
            $this->tryAddSubdivision($subDivisions, $testCode, $allSubdivisions, $seen);
        }

        // Double letters (US-CA, US-NY, IT-TO, IT-MI, etc.)
        for ($i = 0; $i < 26; $i++) {
            for ($j = 0; $j < 26; $j++) {
                $testCode = $countryCode . '-' . $letters[$i] . $letters[$j];
                $this->tryAddSubdivision($subDivisions, $testCode, $allSubdivisions, $seen);
            }
        }

        // Triple letters (GB-ENG, GB-SCT, etc.)
        for ($i = 0; $i < 26; $i++) {
            for ($j = 0; $j < 26; $j++) {
                for ($k = 0; $k < 26; $k++) {
                    $testCode = $countryCode . '-' . $letters[$i] . $letters[$j] . $letters[$k];
                    $this->tryAddSubdivision($subDivisions, $testCode, $allSubdivisions, $seen);
                }
            }
        }

        return $allSubdivisions;
    }

    private function tryAddSubdivision(\Sokil\IsoCodes\Database\SubdivisionsInterface $subDivisions, string $testCode, array &$allSubdivisions, array &$seen): void // @phpstan-ignore class.notFound
    {
        try {
            $subdivision = $subDivisions->getByCode($testCode); // @phpstan-ignore class.notFound
            if ($subdivision) {
                $code = substr($testCode, 3); // Remove country prefix

                // Skip duplicates
                if (isset($seen[$code])) {
                    return;
                }
                $seen[$code] = true;

                $allSubdivisions[] = [
                    'code' => $code,
                    'name' => $subdivision->getName(),
                    'type' => $subdivision->getType(),
                    'isoCode' => $testCode,
                ];
            }
        } catch (Exception $e) {
            // Subdivision doesn't exist, continue
        }
    }

    private function filterSubdivisionsByHierarchy(array $allSubdivisions, OutputInterface $output): array
    {

        // Analyze subdivision types and their frequency
        $typeGroups = [];
        foreach ($allSubdivisions as $subdivision) {
            $type = $subdivision['type'];
            if (!isset($typeGroups[$type])) {
                $typeGroups[$type] = [];
            }
            $typeGroups[$type][] = $subdivision;
        }

        if ($output->isVerbose()) {
            $output->writeln('  Debug: Subdivision type analysis:');
            foreach ($typeGroups as $type => $subdivisions) {
                $output->writeln("    $type: " . count($subdivisions) . ' subdivisions');
            }
        }

        // Strategy: If we have multiple types, prefer the more specific/granular ones
        // This is based on common administrative patterns
        $result = $this->selectPreferredSubdivisions($typeGroups, $output);

        return $result;
    }

    private function selectPreferredSubdivisions(array $typeGroups, OutputInterface $output): array
    {
        // If only one type, return all subdivisions
        if (count($typeGroups) === 1) {
            return reset($typeGroups);
        }

        // Define hierarchy of administrative divisions from most general to most specific
        $hierarchyOrder = [
            // Most general (parent regions)
            'Country', 'Region', 'Autonomous region', 'Land', 'Canton',
            'Metropolitan region', 'Overseas region', 'Autonomous community',

            // Mid-level administrative divisions (primary subdivisions)
            'State', 'Territory', 'Province', 'Prefecture', 'Governorate', 'Emirate',
            'Federal district', // Equal to State level for countries like Brazil
            'Oblast', 'Krai', 'Republic', 'Federal city', 'Department',

            // More specific subdivisions (preferred for import)
            'Metropolitan city', 'Free municipal consortium', 'Autonomous province',
            'Decentralized regional entity', 'District', 'County', 'Municipality',
            'Commune', 'Outlying area', 'Special administrative region',
        ];

        // Create a scoring system: lower score = more general, higher score = more specific
        $typeScores = array_flip($hierarchyOrder);

        // Score each type group
        $scoredTypes = [];
        foreach ($typeGroups as $type => $subdivisions) {
            $score = $typeScores[$type] ?? 50; // Default middle score for unknown types
            $count = count($subdivisions);

            $scoredTypes[] = [
                'type' => $type,
                'score' => $score,
                'count' => $count,
                'subdivisions' => $subdivisions,
            ];
        }

        // Sort by specificity score (descending) - prefer more specific types
        usort($scoredTypes, fn($a, $b) => $b['score'] <=> $a['score']);

        if ($output->isVerbose()) {
            $output->writeln('  Debug: Type preference analysis:');
            foreach ($scoredTypes as $typeInfo) {
                $output->writeln("    {$typeInfo['type']}: score={$typeInfo['score']}, count={$typeInfo['count']}");
            }
        }

        // Strategy: Choose the most specific type(s) that have reasonable coverage
        // But avoid outliers (types with very few subdivisions if others have many)
        $totalSubdivisions = array_sum(array_column($scoredTypes, 'count'));
        $result = [];

        foreach ($scoredTypes as $typeInfo) {
            $coverage = $typeInfo['count'] / $totalSubdivisions;

            // Include types that either:
            // 1. Have majority coverage (>40%)
            // 2. Are among the most specific types and have minimal coverage (>1%)
            // 3. Are administrative equivalents at the primary subdivision level
            $isPrimarySubdivision = in_array($typeInfo['type'], ['State', 'Territory', 'Province', 'Federal district', 'Prefecture', 'Governorate', 'Emirate']);
            $isTopTier = $typeInfo['score'] >= ($scoredTypes[0]['score'] - 3); // Within 3 points of top score
            if ($coverage > 0.4 || ($isTopTier && $coverage > 0.01) || ($isPrimarySubdivision && $coverage > 0.01)) {
                $result = array_merge($result, $typeInfo['subdivisions']);

                if ($output->isVerbose()) {
                    $output->writeln("    -> Including {$typeInfo['type']} (coverage: " . round($coverage * 100) . '%)');
                }
            } else {
                if ($output->isVerbose()) {
                    $output->writeln("    -> Skipping {$typeInfo['type']} (coverage: " . round($coverage * 100) . '%)');
                }
            }
        }

        return $result;
    }

    private function mapToIsoCode(string $countryCode): string
    {
        // Map common country code discrepancies between Magento/Maho and ISO 3166-2
        return match ($countryCode) {
            'UK' => 'GB',  // United Kingdom alternative
            'EL' => 'GR',  // Greece (EL is sometimes used for Greek language)
            default => $countryCode,
        };
    }

    private function getLocalizedNames(array $subdivisions, string $mahoLocale, OutputInterface $output): array
    {
        $localizedNames = [];

        try {
            // Convert Maho locale format (en_US) to Symfony/ISO format (en)
            // Take only the language part, not the country part
            $symfonyLocale = strtok($mahoLocale, '_');

            if ($output->isVerbose()) {
                $output->writeln("<comment>  Getting localized subdivision names for locale: $symfonyLocale</comment>");
            }

            // Create Symfony translation driver and set locale
            $translationDriver = new \Sokil\IsoCodes\TranslationDriver\SymfonyTranslationDriver(); // @phpstan-ignore class.notFound
            $translationDriver->setLocale($symfonyLocale); // @phpstan-ignore class.notFound

            // Create IsoCodes factory with the translation driver
            $isoCodes = new \Sokil\IsoCodes\IsoCodesFactory(null, $translationDriver); // @phpstan-ignore class.notFound
            $subDivisions = $isoCodes->getSubdivisions(); // @phpstan-ignore class.notFound

            foreach ($subdivisions as $subdivision) {
                try {
                    $localizedSubdivision = $subDivisions->getByCode($subdivision['isoCode']);
                    if ($localizedSubdivision) {
                        $localName = $localizedSubdivision->getLocalName();

                        if ($output->isVerbose()) {
                            $originalName = $localizedSubdivision->getName();
                            $isDifferent = $originalName !== $localName ? ' *TRANSLATED*' : '';
                            $output->writeln("<comment>    {$subdivision['code']}: '$originalName' → '$localName'$isDifferent</comment>");
                        }

                        // Use the localized name
                        $localizedNames[$subdivision['code']] = $localName;
                    } else {
                        if ($output->isVerbose()) {
                            $output->writeln("<comment>    {$subdivision['code']}: subdivision not found, using original name</comment>");
                        }
                        $localizedNames[$subdivision['code']] = $subdivision['name'];
                    }
                } catch (\Exception $e) {
                    if ($output->isVerbose()) {
                        $output->writeln("<comment>    {$subdivision['code']}: error - {$e->getMessage()}, using original name</comment>");
                    }
                    $localizedNames[$subdivision['code']] = $subdivision['name'];
                }
            }
        } catch (\Exception $e) {
            if ($output->isVerbose()) {
                $output->writeln("<comment>  Warning: Could not initialize translations for $mahoLocale: {$e->getMessage()}</comment>");
            }
            // Fall back to default names
            foreach ($subdivisions as $subdivision) {
                $localizedNames[$subdivision['code']] = $subdivision['name'];
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
            foreach ($allLocales as $locale) {
                $headers[] = $locale . ' (if different)';
            }
            $table->setHeaders($headers);

            foreach ($importRecords as $record) {
                $row = [$record['code'], $record['name']];
                foreach ($allLocales as $locale) {
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
            $headers = ['Code', 'Current Name', 'New Name (English)'];
            $allLocales = [];
            foreach ($updateRecords as $record) {
                if (isset($record['locales'])) {
                    $allLocales = array_merge($allLocales, array_keys($record['locales']));
                }
            }
            $allLocales = array_unique($allLocales);
            sort($allLocales);
            foreach ($allLocales as $locale) {
                $headers[] = $locale . ' (if different)';
            }
            $table->setHeaders($headers);

            foreach ($updateRecords as $record) {
                $row = [$record['code'], $record['existing'], $record['name']];
                foreach ($allLocales as $locale) {
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

    private function insertRegionName(int $regionId, string $locale, string $name, Varien_Db_Adapter_Interface $connection): void
    {
        $connection->insertOnDuplicate(
            $connection->getTableName('directory/country_region_name'),
            [
                'locale' => $locale,
                'region_id' => $regionId,
                'name' => $name,
            ],
            ['name'],
        );
    }

    private function updateRegionName(int $regionId, string $locale, string $name, Varien_Db_Adapter_Interface $connection): void
    {
        $this->insertRegionName($regionId, $locale, $name, $connection);
    }

    private function installIsoPackages(OutputInterface $output): bool
    {
        $output->writeln('<info>Installing sokil/php-isocodes, sokil/php-isocodes-db-i18n and symfony/translation...</info>');

        $process = new Process([
            'composer', 'require',
            'sokil/php-isocodes',
            'sokil/php-isocodes-db-i18n',
            'symfony/translation',
            '--no-interaction',
        ], MAHO_ROOT_DIR);

        $process->setTimeout(300); // 5 minutes timeout
        $process->run();

        if (!$process->isSuccessful()) {
            $output->writeln('<error>Failed to install ISO packages:</error>');
            $output->writeln($process->getErrorOutput());
            return false;
        }

        $output->writeln('<info>Packages installed successfully.</info>');
        return true;
    }


    private function removeIsoPackages(OutputInterface $output): void
    {
        $process = new Process([
            'composer', 'remove',
            'sokil/php-isocodes',
            'sokil/php-isocodes-db-i18n',
            'symfony/translation',
            '--no-interaction',
        ], MAHO_ROOT_DIR);

        $process->setTimeout(120); // 2 minutes timeout
        $process->run();

        if (!$process->isSuccessful()) {
            $output->writeln('<comment>Warning: Could not remove ISO packages automatically.</comment>');
            $output->writeln('<comment>You may want to run: composer remove sokil/php-isocodes sokil/php-isocodes-db-i18n symfony/translation</comment>');
        } else {
            $output->writeln('<info>Temporary packages removed successfully.</info>');
        }
    }

    private function reExecuteCommand(InputInterface $input, OutputInterface $output, bool $packagesInstalledByUs = false): int
    {
        // Build command arguments
        $args = [PHP_BINARY, './maho', 'sys:directory:regions:import'];

        // Add all original options
        if ($input->getOption('country')) {
            $args[] = '--country=' . $input->getOption('country');
        }
        if ($input->getOption('locales')) {
            $args[] = '--locales=' . $input->getOption('locales');
        }
        if ($input->getOption('dry-run')) {
            $args[] = '--dry-run';
        }
        if ($input->getOption('update-existing')) {
            $args[] = '--update-existing';
        }
        if ($input->getOption('force')) {
            $args[] = '--force';
        }

        // Set environment variable to indicate this is a re-execution
        $env = $_ENV;
        $env['MAHO_ISO_REEXEC'] = 'true';
        if ($packagesInstalledByUs) {
            $env['MAHO_ISO_INSTALLED'] = 'true';
        }

        $process = new Process($args, MAHO_ROOT_DIR, $env);
        $process->setTimeout(null); // No timeout for the actual command
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        return $process->getExitCode();
    }
}
