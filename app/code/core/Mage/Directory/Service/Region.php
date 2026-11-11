<?php

/**
 * Save and delete regions and their names, and import the regions of a country from the ISO 3166-2 data,
 * for the admin, the install wizard and the CLI.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

use Maho\DirectoryData\Paths;

class Mage_Directory_Service_Region
{
    /**
     * @throws Mage_Core_Exception when Mage_Directory_Model_Region::validate() refuses the region
     */
    public function save(Mage_Directory_Model_Region $region): Mage_Directory_Model_Region
    {
        $errors = $region->validate();
        if ($errors !== true) {
            throw new Mage_Core_Exception(implode(' ', $errors));
        }
        $region->save();
        return $region;
    }

    /**
     * @throws Mage_Core_Exception_Conflict when the region has a name in a locale
     */
    public function delete(Mage_Directory_Model_Region $region): void
    {
        if ($region->hasTranslation()) {
            throw new Mage_Core_Exception_Conflict(
                Mage::helper('directory')->__('Cannot delete region with existing region names. Please delete all region names first.'),
            );
        }
        $region->delete();
    }

    /**
     * Save $name as the name of $region in $locale. With $replaceExisting, a name that the locale already has is replaced.
     *
     * @throws Mage_Core_Exception when the locale or the name is empty, the name is too long, or the locale has a name
     */
    public function saveTranslation(Mage_Directory_Model_Region $region, string $locale, string $name, bool $replaceExisting = false): void
    {
        $data = ['locale' => $locale, 'name' => $name];
        $errors = $region->validateTranslation($data, $replaceExisting);
        if ($errors !== true) {
            throw new Mage_Core_Exception(implode(' ', $errors));
        }
        $region->saveTranslation($data);
    }

    /**
     * Import the regions of $countryCode, with their name in each locale of $locales. A region gets a localized
     * name only when it is not the same as the English name. Without $updateExisting, a region that exists keeps
     * its default name and each localized name that it has. With $dryRun, the import saves nothing. A region that
     * Mage_Directory_Model_Region::validate() refuses is skipped, and so is a name that validateTranslation() refuses.
     *
     * $log gets each message and its level: info, comment, error, or debug for the details of each region.
     *
     * @param list<string> $locales
     * @param (callable(string, string): void)|null $log
     * @return array{
     *     imported: int,
     *     updated: int,
     *     skipped: int,
     *     total: int,
     *     importRecords: list<array{code: string, name: string, locales: array<string, string>}>,
     *     updateRecords: list<array{code: string, name: string, existing: string, locales: array<string, string>}>,
     *     skipRecords: list<array{code: string, name: string, existing: string}>,
     * }
     * @throws Mage_Core_Exception_NoSuchEntity when no country has the code $countryCode
     * @throws JsonException when the data file of the country is not valid JSON
     */
    public function import(
        string $countryCode,
        array $locales = ['en_US'],
        bool $updateExisting = false,
        bool $dryRun = false,
        ?callable $log = null,
    ): array {
        $log ??= static function (string $message, string $level): void {};
        $countryCode = strtoupper(trim($countryCode));
        $country = Mage::getModel('directory/country')->loadByCode($countryCode);
        if ($countryCode === '' || !$country->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(
                Mage::helper('directory')->__("Country code '%s' not found in database", $countryCode),
            );
        }

        $log("Importing regions for {$country->getName()} ($countryCode)", 'info');
        $log('Locales: ' . implode(', ', $locales), 'info');
        if ($dryRun) {
            $log('DRY RUN MODE - No changes will be made', 'comment');
        }

        $result = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'total' => 0,
            'importRecords' => [],
            'updateRecords' => [],
            'skipRecords' => [],
        ];
        $regionsData = $this->loadRegionData($countryCode);
        if ($regionsData === []) {
            $log("No regions data found for $countryCode", 'comment');
            return $result;
        }
        $log('  Found ' . count($regionsData) . ' regions to process', 'debug');

        $englishNames = $this->getLocalizedNames($regionsData, 'en_US');
        $namesByLocale = [];
        foreach ($locales as $locale) {
            foreach ($this->getLocalizedNames($regionsData, $locale) as $code => $name) {
                $defaultName = $englishNames[$code] ?? null;
                if ($defaultName && $name !== $defaultName) {
                    $namesByLocale[$locale][$code] = $name;
                    $log("    Keeping $locale translation for $code: '$defaultName' → '$name'", 'debug');
                } else {
                    $log("    Skipping $locale for $code: same as English default ('$defaultName')", 'debug');
                }
            }
        }

        $log("\nProcessing " . count($regionsData) . ' regions...', 'debug');
        foreach ($regionsData as $code => $translations) {
            $code = (string) $code;
            $defaultName = $englishNames[$code] ?? $translations['en'] ?? null;
            if (!$defaultName) {
                $log("No English name found for $code, skipping", 'debug');
                $result['skipped']++;
                continue;
            }

            /** @var Mage_Directory_Model_Region $region */
            $region = Mage::getModel('directory/region')->loadByCode($code, $countryCode);
            $isNew = !$region->getId();
            $existingName = (string) $region->getDefaultName();
            $names = [];
            foreach ($namesByLocale as $locale => $localizedNames) {
                if (!isset($localizedNames[$code])) {
                    continue;
                }
                $existingLocalizedName = $isNew ? null : $region->getResource()->getTranslation($region, $locale)->getName();
                if ($existingLocalizedName && !$updateExisting) {
                    $log("Skipping $code ($locale): localized name already exists ('$existingLocalizedName')", 'debug');
                    continue;
                }
                $names[$locale] = $localizedNames[$code];
            }
            $renames = $isNew || ($updateExisting && $existingName !== $defaultName);

            if (!$renames && $names === []) {
                $result['skipRecords'][] = ['code' => $code, 'name' => $defaultName, 'existing' => $existingName];
                $result['skipped']++;
                continue;
            }

            if ($renames) {
                $region->setCountryId($countryCode)->setCode($code)->setDefaultName($defaultName);
            }
            $errors = $region->validate();
            if ($errors !== true) {
                $log("Skipping $code: " . implode(' ', $errors), 'error');
                $result['skipped']++;
                continue;
            }

            if (!$dryRun) {
                if ($renames) {
                    $this->save($region);
                }
                foreach ($names as $locale => $name) {
                    try {
                        $this->saveTranslation($region, $locale, $name, true);
                    } catch (Mage_Core_Exception $e) {
                        $log("Skipping the $locale name of $code: " . $e->getMessage(), 'error');
                        unset($names[$locale]);
                    }
                }
            }
            if ($isNew) {
                $result['importRecords'][] = ['code' => $code, 'name' => $defaultName, 'locales' => $names];
                $result['imported']++;
            } else {
                $result['updateRecords'][] = ['code' => $code, 'name' => $defaultName, 'existing' => $existingName, 'locales' => $names];
                $result['updated']++;
            }
        }

        $result['total'] = $result['imported'] + $result['updated'] + $result['skipped'];
        return $result;
    }

    /**
     * @return array<string, array<string, string>> the names of each region code, by language or locale code
     * @throws JsonException
     */
    protected function loadRegionData(string $countryCode): array
    {
        $file = Paths::regionsFile($countryCode);
        if (!is_file($file)) {
            return [];
        }
        $data = Mage::helper('core')->jsonDecode((string) file_get_contents($file));
        if (!is_array($data)) {
            throw new JsonException("Invalid JSON data in $file");
        }
        return $data;
    }

    /**
     * Return the name of each region in $locale. The name in the language of $locale comes next, then the English
     * name, then the first name of the region.
     *
     * @param array<string, array<string, string>> $regionsData
     * @return array<string, string>
     */
    protected function getLocalizedNames(array $regionsData, string $locale): array
    {
        $language = strtok($locale, '_');
        $names = [];
        foreach ($regionsData as $code => $translations) {
            $name = $translations[$locale] ?? $translations[$language] ?? $translations['en'] ?? array_first($translations);
            if ($name !== null) {
                $names[(string) $code] = $name;
            }
        }
        return $names;
    }
}
