<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Customer Write Tests
 *
 * Covers the extended customer/address DTO fields (name parts, gender, dob,
 * taxvat, groupId, isActive, websiteId, vatId) and the admin-only gating:
 * admin/service tokens may write the restricted fields, a customer's own
 * token via /customers/me may not.
 *
 * @group write
 */

const CUSTOMER_WRITE_EMAIL_PREFIX = 'pest-customer-write-';

function createTestCustomer(array $extra = []): array
{
    $email = CUSTOMER_WRITE_EMAIL_PREFIX . uniqid() . '@example.test';
    $response = apiPost('/api/rest/v2/customers', array_merge([
        'email' => $email,
        'password' => 'PestWrite1234!',
        'firstname' => 'Pest',
        'lastname' => 'Writer',
    ], $extra), adminToken());

    return [$response, $email];
}

afterAll(function (): void {
    try {
        \Mage::app();
        $write = \Mage::getSingleton('core/resource')->getConnection('core_write');
        // Addresses cascade via the customer_address_entity.parent_id FK
        $write->query(
            'DELETE FROM customer_entity WHERE email LIKE ?',
            [CUSTOMER_WRITE_EMAIL_PREFIX . '%'],
        );
    } catch (\Throwable) {
        // DB not available; nothing to clean
    }
});

describe('POST /api/rest/v2/customers (extended fields)', function (): void {

    it('lets an admin create a customer with dob, taxvat, gender and prefix, and read them back', function (): void {
        [$create, $email] = createTestCustomer([
            'prefix' => 'Dr.',
            'middlename' => 'Q.',
            'suffix' => 'Jr.',
            'gender' => 1,
            'dob' => '1990-04-15',
            'taxvat' => 'IT12345678901',
            'groupId' => 2,
        ]);

        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];
        expect($id)->toBeGreaterThan(0);

        $read = apiGet("/api/rest/v2/customers/{$id}", adminToken());
        expect($read['status'])->toBe(200);
        $customer = $read['json'];
        expect($customer['email'])->toBe($email);
        expect($customer['prefix'])->toBe('Dr.');
        expect($customer['middlename'])->toBe('Q.');
        expect($customer['suffix'])->toBe('Jr.');
        expect($customer['gender'])->toBe(1);
        expect($customer['dob'])->toBe('1990-04-15');
        expect($customer['taxvat'])->toBe('IT12345678901');
        expect($customer['groupId'])->toBe(2);
        expect($customer['isActive'])->toBeTrue();
        expect($customer['websiteId'])->toBeInt();
        expect($customer['storeId'])->toBeInt();
        expect($customer['isConfirmed'])->toBeTrue();
        expect($customer)->not->toHaveKey('confirmation');
    });

    it('ignores admin-only fields on anonymous registration', function (): void {
        $email = CUSTOMER_WRITE_EMAIL_PREFIX . uniqid() . '@example.test';
        $response = apiPost('/api/rest/v2/customers', [
            'email' => $email,
            'password' => 'PestWrite1234!',
            'firstname' => 'Anon',
            'lastname' => 'User',
            'groupId' => 2,
            'taxvat' => 'IT12345678901',
        ]);

        // The admin-only properties carry a securityPostDenormalize gate, so an
        // unprivileged caller's values are reset before the processor sees them:
        // registration succeeds, on the default group, with no tax id.
        expect($response['status'])->toBeSuccessful();

        $read = apiGet('/api/rest/v2/customers/' . (int) $response['json']['id'], adminToken());
        expect($read['json']['groupId'])->toBe(1)
            ->and($read['json']['taxvat'])->toBeNull();
    });

    it('rejects a non-existent groupId', function (): void {
        [$response] = createTestCustomer(['groupId' => 999999]);

        expect($response['status'])->toBe(400);
    });

    it('rejects a non-existent websiteId', function (): void {
        [$response] = createTestCustomer(['websiteId' => 999999]);

        expect($response['status'])->toBe(400);
    });

    it('rejects an invalid dob', function (): void {
        [$response] = createTestCustomer(['dob' => 'not-a-date']);

        expect($response['status'])->toBe(400);
    });

    it('rejects a dob that is not a Y-m-d calendar date', function (): void {
        // '1990' would be read as a unix timestamp, 'tomorrow' as a relative date
        foreach (['1990', '0', 'tomorrow', '15/04/1990', '1990-13-45'] as $dob) {
            [$response] = createTestCustomer(['dob' => $dob]);
            expect($response['status'])->toBe(400, "dob '{$dob}' should be rejected");
        }
    });

    it('rejects a dob in the future', function (): void {
        [$response] = createTestCustomer(['dob' => date('Y-m-d', strtotime('+1 day'))]);

        expect($response['status'])->toBe(400);
    });

    it('rejects a gender that is not an option of the gender attribute', function (): void {
        [$response] = createTestCustomer(['gender' => 999]);

        expect($response['status'])->toBe(400);
    });

});

describe('PUT /api/rest/v2/customers/{id}', function (): void {

    it('lets an admin change groupId and isActive', function (): void {
        [$create] = createTestCustomer();
        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];

        $update = apiPut("/api/rest/v2/customers/{$id}", [
            'groupId' => 3,
            'isActive' => false,
            'disableAutoGroupChange' => true,
        ], adminToken());

        expect($update['status'])->toBeSuccessful();
        expect($update['json']['groupId'])->toBe(3);
        expect($update['json']['isActive'])->toBeFalse();
        expect($update['json']['disableAutoGroupChange'])->toBeTrue();

        $read = apiGet("/api/rest/v2/customers/{$id}", adminToken());
        expect($read['json']['groupId'])->toBe(3);
        expect($read['json']['isActive'])->toBeFalse();
    });

    it('rejects changing websiteId after creation', function (): void {
        [$create] = createTestCustomer();
        $id = (int) $create['json']['id'];
        $currentWebsiteId = (int) $create['json']['websiteId'];

        $update = apiPut("/api/rest/v2/customers/{$id}", [
            'websiteId' => $currentWebsiteId + 1,
        ], adminToken());

        expect($update['status'])->toBe(400);
    });

    it('denies the admin update endpoint to a service token without customers/write', function (): void {
        [$create] = createTestCustomer();
        $id = (int) $create['json']['id'];

        $update = apiPut("/api/rest/v2/customers/{$id}", [
            'groupId' => 3,
        ], serviceToken(['customers/read']));

        expect($update['status'])->toBeForbidden();

        $read = apiGet("/api/rest/v2/customers/{$id}", adminToken());
        expect($read['json']['groupId'])->not->toBe(3);
    });

    it('denies the admin update endpoint to a customer token', function (): void {
        [$create] = createTestCustomer();
        $id = (int) $create['json']['id'];

        $update = apiPut("/api/rest/v2/customers/{$id}", [
            'groupId' => 3,
        ], customerToken($id));

        expect($update['status'])->toBe(403);
    });

});

describe('PUT /api/rest/v2/customers/me (self-service gating)', function (): void {

    it('ignores groupId, isActive and taxvat on a self-service update', function (): void {
        [$create] = createTestCustomer();
        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];
        $originalGroupId = (int) $create['json']['groupId'];

        $update = apiPut('/api/rest/v2/customers/me', [
            'firstname' => 'SelfEdited',
            'groupId' => 3,
            'isActive' => false,
            'taxvat' => 'FORGED123',
            'disableAutoGroupChange' => true,
        ], customerToken($id));

        // The self-service handler never reads admin-only fields: the write
        // succeeds but only the profile fields are applied.
        expect($update['status'])->toBeSuccessful();

        $read = apiGet("/api/rest/v2/customers/{$id}", adminToken());
        expect($read['json']['firstname'])->toBe('SelfEdited');
        expect($read['json']['groupId'])->toBe($originalGroupId);
        expect($read['json']['isActive'])->toBeTrue();
        // Null properties are omitted from the response (skip_null_values)
        expect($read['json']['taxvat'] ?? null)->toBeNull();
        expect($read['json']['disableAutoGroupChange'])->toBeFalse();
    });

    it('lets a customer update their own name parts and dob', function (): void {
        [$create] = createTestCustomer();
        $id = (int) $create['json']['id'];

        $update = apiPut('/api/rest/v2/customers/me', [
            'prefix' => 'Ms.',
            'middlename' => 'X.',
            'suffix' => 'Sr.',
            'gender' => 2,
            'dob' => '1985-12-01',
        ], customerToken($id));

        expect($update['status'])->toBeSuccessful();
        expect($update['json']['prefix'])->toBe('Ms.');
        expect($update['json']['middlename'])->toBe('X.');
        expect($update['json']['suffix'])->toBe('Sr.');
        expect($update['json']['gender'])->toBe(2);
        expect($update['json']['dob'])->toBe('1985-12-01');
    });

});

describe('Customer address extended fields', function (): void {

    it('round-trips vatId and name parts on an admin-created address', function (): void {
        [$create] = createTestCustomer();
        $customerId = (int) $create['json']['id'];

        $addressData = [
            'firstname' => 'Pest',
            'lastname' => 'Writer',
            'prefix' => 'Dr.',
            'middlename' => 'Q.',
            'suffix' => 'Jr.',
            'vatId' => 'IT98765432109',
            'street' => ['Via Roma 1'],
            'city' => 'Milano',
            'postcode' => '20121',
            'countryId' => 'IT',
            'telephone' => '+39 02 1234567',
        ];

        $created = apiPost("/api/rest/v2/customers/{$customerId}/addresses", $addressData, adminToken());
        expect($created['status'])->toBeSuccessful();
        $addressId = (int) $created['json']['id'];
        expect($addressId)->toBeGreaterThan(0);
        expect($created['json']['vatId'])->toBe('IT98765432109');

        $read = apiGet("/api/rest/v2/addresses/{$addressId}", adminToken());
        expect($read['status'])->toBe(200);
        $address = $read['json'];
        expect($address['prefix'])->toBe('Dr.');
        expect($address['middlename'])->toBe('Q.');
        expect($address['suffix'])->toBe('Jr.');
        expect($address['vatId'])->toBe('IT98765432109');
        expect($address['createdAt'])->not->toBeEmpty();
        expect($address['updatedAt'])->not->toBeEmpty();
    });

    it('lets a customer update vatId on their own address', function (): void {
        [$create] = createTestCustomer();
        $customerId = (int) $create['json']['id'];

        $created = apiPost('/api/rest/v2/customers/me/addresses', [
            'firstname' => 'Pest',
            'lastname' => 'Writer',
            'street' => ['Via Roma 2'],
            'city' => 'Torino',
            'postcode' => '10121',
            'countryId' => 'IT',
            'telephone' => '+39 011 7654321',
        ], customerToken($customerId));
        expect($created['status'])->toBeSuccessful();
        $addressId = (int) $created['json']['id'];

        $updated = apiPut("/api/rest/v2/customers/me/addresses/{$addressId}", [
            'firstname' => 'Pest',
            'lastname' => 'Writer',
            'vatId' => 'IT00000000001',
            'street' => ['Via Roma 2'],
            'city' => 'Torino',
            'postcode' => '10121',
            'countryId' => 'IT',
            'telephone' => '+39 011 7654321',
        ], customerToken($customerId));

        expect($updated['status'])->toBeSuccessful();
        expect($updated['json']['vatId'])->toBe('IT00000000001');
    });

});
