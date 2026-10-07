<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 customer address format checks (WRITE)
 *
 * The address processor runs Address::validate(), which checks the postcode
 * format and the region before the save.
 *
 * @group write
 */

const ADDRESS_FORMAT_EMAIL_PREFIX = 'pest-address-format-';

function createAddressFormatCustomer(): int
{
    $response = apiPost('/api/rest/v2/customers', [
        'email' => ADDRESS_FORMAT_EMAIL_PREFIX . uniqid() . '@example.test',
        'password' => 'PestWrite1234!',
        'firstname' => 'Pest',
        'lastname' => 'Format',
    ], adminToken());
    expect($response['status'])->toBeSuccessful();

    return (int) $response['json']['id'];
}

afterAll(function (): void {
    try {
        \Mage::app();
        $write = \Mage::getSingleton('core/resource')->getConnection('core_write');
        // Addresses cascade via the customer_address_entity.parent_id FK
        $write->query(
            'DELETE FROM customer_entity WHERE email LIKE ?',
            [ADDRESS_FORMAT_EMAIL_PREFIX . '%'],
        );
    } catch (\Throwable) {
        // DB not available; nothing to clean
    }
});

describe('Customer address format checks', function (): void {

    $usAddress = fn(array $override = []): array => array_merge([
        'firstname' => 'Pest',
        'lastname' => 'Writer',
        'street' => ['1 Infinite Loop'],
        'city' => 'Cupertino',
        'region' => 'California',
        'postcode' => '95014',
        'countryId' => 'US',
        'telephone' => '4085550100',
    ], $override);

    it('rejects a postcode in the wrong format with the message of the address model', function () use ($usAddress): void {
        $token = customerToken(createAddressFormatCustomer());

        $created = apiPost('/api/rest/v2/customers/me/addresses', $usAddress(['postcode' => '123456789']), $token);

        expect($created['status'])->toBe(422);
        expect($created['json']['message'])->toContain('Please enter a valid postcode for United States');
    });

    it('rejects a postcode in the wrong format on update', function () use ($usAddress): void {
        $token = customerToken(createAddressFormatCustomer());

        $created = apiPost('/api/rest/v2/customers/me/addresses', $usAddress(), $token);
        expect($created['status'])->toBeSuccessful();
        $addressId = (int) $created['json']['id'];

        $updated = apiPut("/api/rest/v2/customers/me/addresses/{$addressId}", $usAddress(['postcode' => '123456789']), $token);

        expect($updated['status'])->toBe(422);
        expect($updated['json']['message'])->toContain('Please enter a valid postcode for United States');
    });

    it('rejects a region of another country', function () use ($usAddress): void {
        $token = customerToken(createAddressFormatCustomer());
        $regions = apiGet('/api/rest/v2/countries/CA')['json']['availableRegions'] ?? [];
        $ontarioId = (int) (array_column($regions, 'id', 'code')['ON'] ?? 0);
        expect($ontarioId)->toBeGreaterThan(0);

        $created = apiPost('/api/rest/v2/customers/me/addresses', $usAddress(['regionId' => $ontarioId]), $token);

        expect($created['status'])->toBe(422);
        expect($created['json']['message'])->toContain('The selected state/province is not valid for the chosen country.');
    });

});
