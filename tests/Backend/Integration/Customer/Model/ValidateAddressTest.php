<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function importAddressRow(string $country, ?string $region): array
{
    return array_filter([
        'billing_city' => 'Los Angeles',
        'billing_country' => $country,
        'billing_postcode' => '90001',
        'billing_telephone' => '5551234567',
        'billing_street1' => '1 Main St',
        'billing_region' => $region,
    ], fn($value) => $value !== null);
}

it('accepts a US or CA address whose region matches by name or by code', function (string $country, string $region) {
    expect(Mage::getModel('customer/customer')->validateAddress(importAddressRow($country, $region)))->toBeTrue();
})->with([
    ['US', 'California'],
    ['us', 'California'],
    ['US', 'CA'],
    ['ca', 'Ontario'],
    ['CA', 'ON'],
]);

it('rejects a US or CA address with no region or an unknown region', function (?string $region) {
    expect(Mage::getModel('customer/customer')->validateAddress(importAddressRow('US', $region)))->toBeFalse();
})->with([
    [null],
    ['Atlantis'],
]);

it('does not check the region outside US and CA', function () {
    expect(Mage::getModel('customer/customer')->validateAddress(importAddressRow('IT', null)))->toBeTrue();
});
