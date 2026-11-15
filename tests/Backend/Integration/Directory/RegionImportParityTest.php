<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use MahoCLI\Commands\SysDirectoryRegionsImport;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * The region import of System > Regions and sys:directory:regions:import go through
 * Mage_Directory_Service_Region::import() (#1348). Each test gives the same country to both transports.
 * The install wizard calls the same method.
 */

const REGION_IMPORT_PARITY_COUNTRY = 'AE';

/**
 * @param array<string, string> $params
 * @return array{success?: bool, output?: string, error?: string}
 */
function regionImportViaAdmin(array $params): array
{
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('http://localhost/admin/directory_region/import', 'POST', $params),
    );
    $request->setRouteName('adminhtml')->setControllerName('directory_region')->setActionName('import')->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    new Mage_Directory_Adminhtml_Directory_RegionController($request, $response)->importAction();
    return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @param array<string, mixed> $options
 * @return array{code: int, output: string}
 */
function regionImportViaCli(array $options): array
{
    $command = new SysDirectoryRegionsImport();
    new Application()->addCommand($command);
    $tester = new CommandTester($command);
    $code = $tester->execute($options, ['interactive' => false]);
    return ['code' => $code, 'output' => $tester->getDisplay()];
}

/**
 * @return array<string, array{name: string, translations: array<string, string>}> the regions of the test country,
 *                                                                                 by code
 */
function regionImportSnapshot(): array
{
    $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
    $regions = [];
    foreach (Mage::getResourceModel('directory/region_collection')->addCountryFilter(REGION_IMPORT_PARITY_COUNTRY) as $region) {
        $translations = $adapter->fetchPairs($adapter->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('directory/country_region_name'), ['locale', 'name'])
            ->where('region_id = ?', $region->getId())
            ->order('locale'));
        $regions[$region->getCode()] = ['name' => $region->getDefaultName(), 'translations' => $translations];
    }
    ksort($regions);
    return $regions;
}

function regionImportCleanup(): void
{
    foreach (Mage::getResourceModel('directory/region_collection')->addCountryFilter(REGION_IMPORT_PARITY_COUNTRY) as $region) {
        foreach (['en_US', 'ru_RU'] as $locale) {
            $region->deleteTranslation($locale);
        }
        $region->delete();
    }
}

/**
 * Register a service that reads $regionsData in place of the data file of each country.
 *
 * @param array<string, array<string, string>> $regionsData
 */
function regionImportSpy(array $regionsData): void
{
    $spy = new class ($regionsData) extends Mage_Directory_Service_Region {
        public function __construct(private readonly array $regionsData) {}

        #[\Override]
        protected function loadRegionData(string $countryCode): array
        {
            return $this->regionsData;
        }
    };
    Mage::unregister('_service/directory/region');
    Mage::register('_service/directory/region', $spy);
}

beforeEach(function (): void {
    regionImportCleanup();
});

afterEach(function (): void {
    Mage::unregister('_service/directory/region');
    regionImportCleanup();
});

it('refuses a country that does not exist, on the admin and the CLI', function (): void {
    $message = Mage::helper('directory')->__("Country code '%s' not found in database", 'ZZ');

    $admin = regionImportViaAdmin(['countries' => 'ZZ']);
    $cli = regionImportViaCli(['--country' => 'ZZ']);

    expect($admin)->toBe(['error' => $message])
        ->and($cli['code'])->toBe(Command::FAILURE)
        ->and($cli['output'])->toContain($message);
});

it('previews the same import on the admin and the CLI, and saves nothing', function (): void {
    $admin = regionImportViaAdmin(['countries' => REGION_IMPORT_PARITY_COUNTRY, 'locales' => 'en_US,ru_RU', 'dryRun' => 'true']);
    $cli = regionImportViaCli(['--country' => REGION_IMPORT_PARITY_COUNTRY, '--locales' => 'en_US,ru_RU', '--dry-run' => true]);

    expect($admin['success'])->toBeTrue()
        ->and($admin['output'])->toContain('Imported: 7')
        ->and($cli['code'])->toBe(Command::SUCCESS)
        ->and($cli['output'])->toMatch('/Imported\s*\|\s*7\s/')
        ->and(regionImportSnapshot())->toBe([]);
});

it('imports the same regions and localized names from the admin and the CLI', function (): void {
    $admin = regionImportViaAdmin(['countries' => REGION_IMPORT_PARITY_COUNTRY, 'locales' => 'en_US,ru_RU']);
    $viaAdmin = regionImportSnapshot();
    regionImportCleanup();
    $cli = regionImportViaCli(['--country' => REGION_IMPORT_PARITY_COUNTRY, '--locales' => 'en_US,ru_RU']);
    $viaCli = regionImportSnapshot();

    expect($admin['success'])->toBeTrue()
        ->and($cli['code'])->toBe(Command::SUCCESS)
        ->and($viaAdmin)->toHaveCount(7)
        ->and($viaAdmin['DU'])->toBe(['name' => 'Dubai', 'translations' => ['ru_RU' => 'Дубай']])
        ->and($viaCli)->toBe($viaAdmin);
});

it('keeps the localized names that exist, unless the import updates them', function (): void {
    regionImportViaCli(['--country' => REGION_IMPORT_PARITY_COUNTRY, '--locales' => 'ru_RU']);
    $region = Mage::getModel('directory/region')->loadByCode('DU', REGION_IMPORT_PARITY_COUNTRY);
    $region->saveTranslation(['locale' => 'ru_RU', 'name' => 'Edited']);

    regionImportViaAdmin(['countries' => REGION_IMPORT_PARITY_COUNTRY, 'locales' => 'ru_RU']);
    expect(regionImportSnapshot()['DU']['translations'])->toBe(['ru_RU' => 'Edited']);

    regionImportViaAdmin(['countries' => REGION_IMPORT_PARITY_COUNTRY, 'locales' => 'ru_RU', 'updateExisting' => 'true']);
    expect(regionImportSnapshot()['DU']['translations'])->toBe(['ru_RU' => 'Дубай'])
        ->and(Mage::getResourceModel('directory/region_collection')->addCountryFilter(REGION_IMPORT_PARITY_COUNTRY)->getSize())->toBe(7);
});

it('skips a region that the region model refuses, on the admin and the CLI', function (): void {
    $code = str_repeat('X', 33);
    regionImportSpy([
        $code => ['en' => 'Too long a code'],
        'DU' => ['en' => 'Dubai'],
    ]);
    $message = Mage::helper('directory')->__('Region code cannot be longer than 32 characters.');

    $admin = regionImportViaAdmin(['countries' => REGION_IMPORT_PARITY_COUNTRY]);
    expect($admin['output'])->toContain($message)
        ->and(array_keys(regionImportSnapshot()))->toBe(['DU']);

    regionImportCleanup();
    $cli = regionImportViaCli(['--country' => REGION_IMPORT_PARITY_COUNTRY]);
    expect($cli['output'])->toContain($message)
        ->and(array_keys(regionImportSnapshot()))->toBe(['DU']);
});
