<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Get;
use Mage\Directory\Api\CountryProvider;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * The address forms of the storefront and the country API give a region the name that System > Regions saved
 * for the store locale (#1348).
 */

const REGION_NAME_PARITY_NAME = 'Freistaat Bayern (Pest)';

/**
 * Save $name as the name of the region $regionId in $locale, with the Region Names form of System > Regions.
 *
 * @return array<string, mixed> the JSON answer of the action
 */
function regionNameParityAdminSave(int $regionId, string $locale, string $name): array
{
    Mage::unregister('current_region');
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create(
        'http://localhost/admin/directory_region/translationSave',
        'POST',
        ['id' => $regionId, 'locale' => $locale, 'name' => $name, 'isAjax' => 1],
    ));
    $request->setRouteName('adminhtml')->setControllerName('directory_region')->setActionName('translationSave')->setDispatched(true);
    Mage::app()->setRequest($request);
    $response = new Mage_Core_Controller_Response_Http();
    new Mage_Directory_Adminhtml_Directory_RegionController($request, $response)->translationSaveAction();
    return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
}

beforeEach(function (): void {
    $this->connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->connection->beginTransaction();
    $this->region = Mage::getModel('directory/region')->loadByCode('BAY', 'DE');
    $this->region->deleteTranslation('de_DE');
    $this->localeCode = Mage::app()->getLocale()->getLocaleCode();
});

afterEach(function (): void {
    Mage::app()->getLocale()->setLocaleCode($this->localeCode);
    Mage::unregister('current_region');
    $this->connection->rollBack();
});

it('gives a region the name that the admin saved for the store locale, on the storefront and the API', function (): void {
    $admin = regionNameParityAdminSave((int) $this->region->getId(), 'de_DE', REGION_NAME_PARITY_NAME);

    Mage::app()->getLocale()->setLocaleCode('de_DE');
    $storeId = (int) Mage::app()->getStore('default-de')->getId();
    Mage::app()->removeCache('DIRECTORY_REGIONS_JSON_STORE' . $storeId);
    $storefront = Mage::helper('core')->jsonDecode(new Mage_Directory_Helper_Data()->getRegionJsonByStore($storeId));

    $country = new CountryProvider()->provide(new Get(), ['id' => 'DE']);
    $api = array_column($country->availableRegions, 'name', 'code');

    expect($admin)->toBe(['success' => true])
        ->and($api['BAY'])->toBe($storefront['DE'][$this->region->getId()]['name'])
        ->and($api['BAY'])->toBe(REGION_NAME_PARITY_NAME);
});
