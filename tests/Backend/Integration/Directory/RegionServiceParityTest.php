<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Helpers\AdminApiParity;

uses(Tests\MahoBackendTestCase::class);

/**
 * System > Regions and the region import save and delete a region and its names through
 * Mage_Directory_Service_Region (#1348).
 */

const REGION_SERVICE_PARITY_COUNTRY = 'AE';
const REGION_SERVICE_PARITY_CODE = 'PEST';

function regionServiceParity(): Mage_Directory_Service_Region
{
    /** @var Mage_Directory_Service_Region */
    return Mage::getService('directory/region');
}

function regionServiceParityRegion(): Mage_Directory_Model_Region
{
    return Mage::getModel('directory/region')->loadByCode(REGION_SERVICE_PARITY_CODE, REGION_SERVICE_PARITY_COUNTRY);
}

function regionServiceParityCleanup(): void
{
    $region = regionServiceParityRegion();
    if ($region->getId()) {
        $region->deleteTranslation('de_DE');
        $region->delete();
    }
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
});

it('refuses a region that the model does not accept', function (): void {
    $region = Mage::getModel('directory/region')->setCountryId(REGION_SERVICE_PARITY_COUNTRY)->setCode(REGION_SERVICE_PARITY_CODE);

    expect(fn() => regionServiceParity()->save($region))->toThrow(Mage_Core_Exception::class)
        ->and($region->getId())->toBeNull();
});

it('refuses to delete a region with a name in the service, the delete action and the mass delete action', function (): void {
    regionServiceParity()->saveTranslation($this->region, 'de_DE', 'Pest Gebiet');
    $message = 'Cannot delete region with existing region names. Please delete all region names first.';

    expect(fn() => regionServiceParity()->delete($this->region))->toThrow(Mage_Core_Exception_Conflict::class, $message);

    $admin = AdminApiParity::admin(Mage_Directory_Adminhtml_Directory_RegionController::class, 'delete', ['id' => $this->region->getId()]);
    $mass = AdminApiParity::admin(Mage_Directory_Adminhtml_Directory_RegionController::class, 'massDelete', ['regions' => [$this->region->getId()]]);

    expect($admin['errors'])->toBe([$message])
        ->and($mass['success'])->toBe([])
        ->and(regionServiceParityRegion()->getId())->not->toBeNull();
});

it('deletes a region without a name', function (): void {
    regionServiceParity()->delete($this->region);

    expect(regionServiceParityRegion()->getId())->toBeNull();
});

it('checks a region name, and replaces the name of a locale only on request', function (): void {
    $service = regionServiceParity();
    $service->saveTranslation($this->region, 'de_DE', 'Pest Gebiet');

    expect(fn() => $service->saveTranslation($this->region, 'de_DE', 'Zweiter Name'))->toThrow(Mage_Core_Exception::class)
        ->and(fn() => $service->saveTranslation($this->region, 'fr_FR', ''))->toThrow(Mage_Core_Exception::class)
        ->and(fn() => $service->saveTranslation($this->region, 'fr_FR', str_repeat('a', 256)))->toThrow(Mage_Core_Exception::class)
        ->and(fn() => $service->saveTranslation($this->region, 'de_DE', str_repeat('a', 256), true))->toThrow(Mage_Core_Exception::class);

    $service->saveTranslation($this->region, 'de_DE', 'Zweiter Name', true);

    expect($this->region->getResource()->getTranslation($this->region, 'de_DE')->getName())->toBe('Zweiter Name')
        ->and($this->region->getResource()->getTranslation($this->region, 'fr_FR')->getName())->toBeNull();
});
