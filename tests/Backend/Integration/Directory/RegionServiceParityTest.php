<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Get;
use Mage\Directory\Api\CountryProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * System > Regions and the region import save and delete a region and its names through
 * Mage_Directory_Service_Region (#1348). The country API reads what the admin saved.
 */

const REGION_SERVICE_PARITY_COUNTRY = 'AE';
const REGION_SERVICE_PARITY_CODE = 'PEST';

function regionServiceParity(): Mage_Directory_Service_Region
{
    /** @var Mage_Directory_Service_Region */
    return Mage::getService('directory/region');
}

function regionServiceParityRegion(string $code = REGION_SERVICE_PARITY_CODE): Mage_Directory_Model_Region
{
    return Mage::getModel('directory/region')->loadByCode($code, REGION_SERVICE_PARITY_COUNTRY);
}

function regionServiceParityCleanup(): void
{
    foreach ([REGION_SERVICE_PARITY_CODE, REGION_SERVICE_PARITY_CODE . '2'] as $code) {
        $region = regionServiceParityRegion($code);
        if ($region->getId()) {
            $region->deleteTranslation('de_DE');
            $region->deleteTranslation('en_US');
            $region->delete();
        }
    }
}

/**
 * Post $fields to the action $action of System > Regions.
 *
 * @param array<string, mixed> $fields
 * @return array{success: list<string>, errors: list<string>}
 */
function regionServiceParityAdmin(string $action, array $fields): array
{
    Mage::unregister('current_region');
    return AdminApiParity::admin(Mage_Directory_Adminhtml_Directory_RegionController::class, $action, $fields);
}

/**
 * Post $fields to the AJAX action $action of System > Regions, and return its JSON answer.
 *
 * @param array<string, mixed> $fields
 * @return array<string, mixed>
 */
function regionServiceParityAdminJson(string $action, array $fields): array
{
    Mage::unregister('current_region');
    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create("http://localhost/admin/directory_region/{$action}", 'POST', $fields + ['isAjax' => 1]),
    );
    $request->setRouteName('adminhtml')->setControllerName('directory_region')->setActionName($action)->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    new Mage_Directory_Adminhtml_Directory_RegionController($request, $response)->{$action . 'Action'}();
    return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @return array<string, string> the name of each region of the test country in the country API, by region code
 */
function regionServiceParityApiNames(): array
{
    $country = new CountryProvider()->provide(new Get(), ['id' => REGION_SERVICE_PARITY_COUNTRY]);
    return array_column($country->availableRegions, 'name', 'code');
}

beforeEach(function (): void {
    regionServiceParityCleanup();
    $this->region = regionServiceParity()->save(
        Mage::getModel('directory/region')
            ->setCountryId(REGION_SERVICE_PARITY_COUNTRY)
            ->setCode(REGION_SERVICE_PARITY_CODE)
            ->setDefaultName('Pest Region'),
    );
});

afterEach(function (): void {
    regionServiceParityCleanup();
    Mage::unregister('current_region');
});

it('saves a region on the admin page, and the country API gives it', function (): void {
    $admin = regionServiceParityAdmin('save', [
        'country_id' => REGION_SERVICE_PARITY_COUNTRY, 'code' => REGION_SERVICE_PARITY_CODE . '2', 'default_name' => 'Pest Second Region',
    ]);

    $saved = regionServiceParityRegion(REGION_SERVICE_PARITY_CODE . '2');
    expect($admin['errors'])->toBe([])
        ->and($admin['success'])->toBe(['The region has been saved.'])
        ->and($saved->getDefaultName())->toBe('Pest Second Region')
        ->and(regionServiceParityApiNames()[REGION_SERVICE_PARITY_CODE . '2'] ?? null)->toBe('Pest Second Region');
});

it('refuses a region that the model does not accept on the admin page, with the errors of the service', function (): void {
    $region = Mage::getModel('directory/region')->setCountryId(REGION_SERVICE_PARITY_COUNTRY)->setCode(REGION_SERVICE_PARITY_CODE);
    $errors = [];
    try {
        regionServiceParity()->save($region);
    } catch (Mage_Core_Exception_Input $e) {
        $errors = $e->getErrors();
    }

    $admin = regionServiceParityAdmin('save', ['country_id' => REGION_SERVICE_PARITY_COUNTRY, 'code' => REGION_SERVICE_PARITY_CODE]);

    expect(array_column($errors, 'message'))->toBe([
        'Default name is required.',
        sprintf('A region with code "%s" already exists in this country.', REGION_SERVICE_PARITY_CODE),
    ])
        ->and(array_unique(array_column($errors, 'field')))->toBe(['region'])
        ->and($admin['errors'])->toBe(array_column($errors, 'message'))
        ->and($admin['success'])->toBe([])
        ->and($region->getId())->toBeNull();
});

it('refuses to delete a region with a name in the service, the delete action and the mass delete action', function (): void {
    regionServiceParity()->saveTranslation($this->region, 'de_DE', 'Pest Gebiet');
    $message = 'Cannot delete region with existing region names. Please delete all region names first.';

    expect(fn() => regionServiceParity()->delete($this->region))->toThrow(Mage_Core_Exception_Conflict::class, $message);

    $admin = regionServiceParityAdmin('delete', ['id' => $this->region->getId()]);
    $mass = regionServiceParityAdmin('massDelete', ['regions' => [$this->region->getId()]]);

    expect($admin['errors'])->toBe([$message])
        ->and($mass['success'])->toBe([])
        ->and(regionServiceParityRegion()->getId())->not->toBeNull();
});

it('deletes a region without a name on the admin page, and the country API no longer gives it', function (): void {
    $before = regionServiceParityApiNames();

    $admin = regionServiceParityAdmin('delete', ['id' => $this->region->getId()]);

    expect($before[REGION_SERVICE_PARITY_CODE] ?? null)->toBe('Pest Region')
        ->and($admin['errors'])->toBe([])
        ->and($admin['success'])->toBe(['The region has been deleted.'])
        ->and(regionServiceParityRegion()->getId())->toBeNull()
        ->and(regionServiceParityApiNames())->not->toHaveKey(REGION_SERVICE_PARITY_CODE);
});

it('saves a region name on the admin page, and refuses a second name of the locale as the service does', function (): void {
    $id = (int) $this->region->getId();
    $saved = regionServiceParityAdminJson('translationSave', ['id' => $id, 'locale' => 'de_DE', 'name' => 'Pest Gebiet']);
    $second = regionServiceParityAdminJson('translationSave', ['id' => $id, 'locale' => 'de_DE', 'name' => 'Zweiter Name']);
    $empty = regionServiceParityAdminJson('translationSave', ['id' => $id, 'locale' => 'fr_FR', 'name' => '']);

    $service = regionServiceParity();
    $serviceMessage = static function (Closure $call): ?string {
        try {
            $call();
        } catch (Mage_Core_Exception_Input $e) {
            return $e->getMessage();
        }
        return null;
    };

    expect($saved)->toBe(['success' => true])
        ->and($this->region->getResource()->getTranslation($this->region, 'de_DE')->getName())->toBe('Pest Gebiet')
        ->and($second)->toBe(['error' => true, 'message' => 'A region name for this locale and region combination already exists.'])
        ->and($second['message'])->toBe($serviceMessage(fn() => $service->saveTranslation($this->region, 'de_DE', 'Zweiter Name')))
        ->and($empty)->toBe(['error' => true, 'message' => 'Region name is required.'])
        ->and($empty['message'])->toBe($serviceMessage(fn() => $service->saveTranslation($this->region, 'fr_FR', '')))
        ->and($serviceMessage(fn() => $service->saveTranslation($this->region, 'fr_FR', str_repeat('a', 256))))
        ->toBe('Region name cannot be longer than 255 characters.')
        ->and($serviceMessage(fn() => $service->saveTranslation($this->region, 'de_DE', str_repeat('a', 256), true)))
        ->toBe('Region name cannot be longer than 255 characters.');

    $service->saveTranslation($this->region, 'de_DE', 'Zweiter Name', true);

    expect($this->region->getResource()->getTranslation($this->region, 'de_DE')->getName())->toBe('Zweiter Name')
        ->and($this->region->getResource()->getTranslation($this->region, 'fr_FR')->getName())->toBeNull();
});
