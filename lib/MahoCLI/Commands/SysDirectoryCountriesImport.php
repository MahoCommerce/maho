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
    name: 'sys:directory:countries:import',
    description: 'Import country names with localization from ISO 3166-1 standard',
)]
class SysDirectoryCountriesImport extends BaseMahoCommand
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption('locales', 'l', InputOption::VALUE_OPTIONAL, 'Comma-separated list of Maho locales (e.g., en_US,it_IT)', 'en_US')
            ->addOption('update-existing', 'u', InputOption::VALUE_NONE, 'Update existing localized names (default: only add new locales)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation for package installation')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Preview changes without importing');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->initMaho();

        $locales = array_map('trim', explode(',', $input->getOption('locales')));
        $dryRun = $input->getOption('dry-run');
        $updateExisting = $input->getOption('update-existing');
        $force = $input->getOption('force');

        // Check if this is a re-execution after package installation
        $isReExecution = getenv('MAHO_ISO_REEXEC') === 'true';
        $packagesInstalledByUs = getenv('MAHO_ISO_INSTALLED') === 'true';

        // Check if required packages are available
        $packagesWereAlreadyPresent = class_exists(\Sokil\IsoCodes\IsoCodesFactory::class)
            && class_exists(\Sokil\IsoCodes\TranslationDriver\SymfonyTranslationDriver::class)
            && class_exists(\Symfony\Component\Translation\Translator::class);

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

        $output->writeln('<info>Importing country names with localization</info>');
        $output->writeln('<info>Locales: ' . implode(', ', $locales) . '</info>');

        if ($dryRun) {
            $output->writeln('<comment>DRY RUN MODE - No changes will be made</comment>');
        }

        try {
            // Get countries from ISO codes
            $isoCodes = new \Sokil\IsoCodes\IsoCodesFactory(); // @phpstan-ignore class.notFound
            $countries = $isoCodes->getCountries(); // @phpstan-ignore class.notFound

            $countriesToProcess = $this->getCountriesToProcess($countries, $output);

            if (empty($countriesToProcess)) {
                $output->writeln('<comment>No countries to process</comment>');
                // Clean up packages if we installed them
                if ($packagesInstalledByUs && !$isReExecution) {
                    $this->removeIsoPackages($output);
                }
                return Command::SUCCESS;
            }

            $output->writeln('  Found ' . count($countriesToProcess) . ' countries to process');

            // Get English names first to use as default
            $englishNames = $this->getLocalizedNames($countriesToProcess, 'en_US', $output);

            // Update country data to use English names as default where available
            foreach ($countriesToProcess as &$country) {
                $code = $country['code'];
                if (isset($englishNames[$code])) {
                    if ($output->isVerbose() && $country['name'] !== $englishNames[$code]) {
                        $output->writeln("<comment>  Using English as default for $code: '{$country['name']}' → '{$englishNames[$code]}'</comment>");
                    }
                    $country['name'] = $englishNames[$code];
                }
            }
            unset($country); // Break the reference

            // Get localized names for each locale
            $countriesByLocale = [];
            foreach ($locales as $mahoLocale) {
                $localizedNames = $this->getLocalizedNames($countriesToProcess, $mahoLocale, $output);

                if ($mahoLocale === 'en_US') {
                    // For English locale, include all names (for directory_country_name table)
                    $countriesByLocale[$mahoLocale] = $localizedNames;
                } else {
                    // For non-English locales, only keep names that are different from the English default
                    $differentNames = [];
                    foreach ($localizedNames as $code => $localizedName) {
                        // Find the country data to get the (now English) default name
                        $defaultName = null;
                        foreach ($countriesToProcess as $country) {
                            if ($country['code'] === $code) {
                                $defaultName = $country['name'];
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
                        $countriesByLocale[$mahoLocale] = $differentNames;
                    }
                }
            }

        } catch (Exception $e) {
            $output->writeln("<error>Failed to load ISO country data: {$e->getMessage()}</error>");
            // Clean up packages if we installed them
            if ($packagesInstalledByUs && !$isReExecution) {
                $this->removeIsoPackages($output);
            }
            return Command::FAILURE;
        }

        // Process imports
        $output->writeln("\n<info>Processing " . count($countriesToProcess) . ' countries...</info>');

        $imported = 0;
        $updated = 0;
        $skipped = 0;

        $importRecords = [];
        $updateRecords = [];
        $skipRecords = [];

        /** @var Mage_Core_Model_Resource $resource */
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');

        foreach ($countriesToProcess as $country) {
            $code = $country['code'];
            $defaultName = $country['name'];

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

                // Process localized names (non-English), but respect existing ones unless --update-existing is used
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
            $this->showDryRunDetails($output, $importRecords, $updateRecords, $skipRecords);
        }

        // Clean up packages if we installed them (only if not a re-execution)
        if ($packagesInstalledByUs && !$isReExecution) {
            $output->writeln('<info>Removing temporary ISO codes packages...</info>');
            $this->removeIsoPackages($output);
        }

        return Command::SUCCESS;
    }

    private function getCountriesToProcess(\Sokil\IsoCodes\Database\Countries $countries, OutputInterface $output): array // @phpstan-ignore class.notFound
    {
        $countriesToProcess = [];

        if ($output->isVerbose()) {
            $output->writeln('<comment>Processing all countries from Maho database</comment>');
        }

        // Get all existing countries from Maho database
        $mahoCountries = Mage::getResourceModel('directory/country_collection');

        foreach ($mahoCountries as $mahoCountry) {
            $countryCode = $mahoCountry->getCountryId();
            try {
                $country = $countries->getByAlpha2($countryCode); // @phpstan-ignore class.notFound
                if ($country) {
                    $countriesToProcess[] = [
                        'code' => $countryCode,
                        'name' => $country->getName(),
                    ];
                } else {
                    if ($output->isVerbose()) {
                        $output->writeln("<comment>Country $countryCode not found in ISO database</comment>");
                    }
                }
            } catch (Exception $e) {
                // Country not found in ISO database, skip
                if ($output->isVerbose()) {
                    $output->writeln("<comment>Country $countryCode not found in ISO database: {$e->getMessage()}</comment>");
                }
            }
        }

        return $countriesToProcess;
    }

    private function getLocalizedNames(array $countries, string $mahoLocale, OutputInterface $output): array
    {
        $localizedNames = [];

        try {
            // Convert Maho locale format (en_US) to Symfony/ISO format (en)
            // Take only the language part, not the country part
            $symfonyLocale = strtok($mahoLocale, '_');

            if ($output->isVerbose()) {
                $output->writeln("<comment>  Getting localized country names for locale: $symfonyLocale</comment>");
            }

            // Create Symfony translation driver and set locale
            $translationDriver = new \Sokil\IsoCodes\TranslationDriver\SymfonyTranslationDriver(); // @phpstan-ignore class.notFound
            $translationDriver->setLocale($symfonyLocale); // @phpstan-ignore class.notFound

            // Create IsoCodes factory with the translation driver
            $isoCodes = new \Sokil\IsoCodes\IsoCodesFactory(null, $translationDriver); // @phpstan-ignore class.notFound
            $isoCountries = $isoCodes->getCountries(); // @phpstan-ignore class.notFound

            foreach ($countries as $country) {
                try {
                    $localizedCountry = $isoCountries->getByAlpha2($country['code']);
                    if ($localizedCountry) {
                        $localName = $localizedCountry->getLocalName();

                        if ($output->isVerbose()) {
                            $originalName = $localizedCountry->getName();
                            $isDifferent = $originalName !== $localName ? ' *TRANSLATED*' : '';
                            $output->writeln("<comment>    {$country['code']}: '$originalName' → '$localName'$isDifferent</comment>");
                        }

                        // Use the localized name
                        $localizedNames[$country['code']] = $localName;
                    } else {
                        if ($output->isVerbose()) {
                            $output->writeln("<comment>    {$country['code']}: country not found, using original name</comment>");
                        }
                        $localizedNames[$country['code']] = $country['name'];
                    }
                } catch (\Exception $e) {
                    if ($output->isVerbose()) {
                        $output->writeln("<comment>    {$country['code']}: error - {$e->getMessage()}, using original name</comment>");
                    }
                    $localizedNames[$country['code']] = $country['name'];
                }
            }
        } catch (\Exception $e) {
            if ($output->isVerbose()) {
                $output->writeln("<comment>  Warning: Could not initialize translations for $mahoLocale: {$e->getMessage()}</comment>");
            }
            // Fall back to default names
            foreach ($countries as $country) {
                $localizedNames[$country['code']] = $country['name'];
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
        $args = [PHP_BINARY, './maho', 'sys:directory:countries:import'];

        // Add all original options
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
