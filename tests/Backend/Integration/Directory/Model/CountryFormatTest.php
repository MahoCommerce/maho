<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('returns no format for a country without an id', function (): void {
    $country = Mage::getModel('directory/country');

    expect($country->getFormats())->toBeNull();
    expect($country->getFormat('html'))->toBeNull();
});

it('returns the format collection of a loaded country', function (): void {
    $country = Mage::getModel('directory/country')->loadByCode('US');

    expect($country->getFormats())->toBeInstanceOf(Mage_Directory_Model_Resource_Country_Format_Collection::class);
});
