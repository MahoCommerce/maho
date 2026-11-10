<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

use ApiPlatform\Metadata\Get;
use Mage\Directory\Api\CountryProvider;

uses(Tests\MahoBackendTestCase::class);

/**
 * The address forms of the storefront and the country API give a region the name of the store locale (#1348).
 */

beforeEach(function (): void {
    $this->connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->connection->beginTransaction();
    $this->region = Mage::getModel('directory/region')->loadByCode('BAY', 'DE');
    $this->connection->insert(
        Mage::getSingleton('core/resource')->getTableName('directory/country_region_name'),
        ['locale' => 'de_DE', 'region_id' => (int) $this->region->getId(), 'name' => 'Freistaat Bayern'],
    );
    $this->localeCode = Mage::app()->getLocale()->getLocaleCode();
    Mage::app()->getLocale()->setLocaleCode('de_DE');
});

afterEach(function (): void {
    Mage::app()->getLocale()->setLocaleCode($this->localeCode);
    $this->connection->rollBack();
});

it('gives a region the name of the store locale on the storefront and the API', function (): void {
    $storeId = (int) Mage::app()->getStore('default-de')->getId();
    Mage::app()->removeCache('DIRECTORY_REGIONS_JSON_STORE' . $storeId);
    $storefront = Mage::helper('core')->jsonDecode(new Mage_Directory_Helper_Data()->getRegionJsonByStore($storeId));

    $country = new CountryProvider()->provide(new Get(), ['id' => 'DE']);
    $api = array_column($country->availableRegions, 'name', 'code');

    expect($storefront['DE'][$this->region->getId()]['name'])->toBe('Freistaat Bayern')
        ->and($api['BAY'])->toBe('Freistaat Bayern');
});
