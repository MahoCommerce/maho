<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 privacy requests (GDPR) and the terms and conditions of the store.
 *
 * @group write
 */

const PRIVACY_TEST_GUEST_EMAIL = 'privacy-api-guest@example.com';
const PRIVACY_TEST_OTHER_EMAIL = 'privacy-api-other@example.com';

/**
 * Save $value for $path at the default scope, and keep the earlier value for restorePrivacyConfig().
 */
function privacyConfig(string $path, string $value): void
{
    try {
        \Mage::app();
        privacyConfigBackup($path, \Mage::getStoreConfig($path));
        \Mage::getModel('core/config')->saveConfig($path, $value, 'default', 0);
        \Mage::app()->getCache()->cleanType('config');
    } catch (\Throwable) {
        // DB not available, the test fails on its first request.
    }
}

/**
 * @return array<string, string|null>
 */
function privacyConfigBackup(?string $path = null, ?string $value = null): array
{
    static $backup = [];
    if ($path !== null && !array_key_exists($path, $backup)) {
        $backup[$path] = $value;
    }
    return $backup;
}

function restorePrivacyConfig(): void
{
    try {
        \Mage::app();
        $config = \Mage::getModel('core/config');
        foreach (privacyConfigBackup() as $path => $value) {
            $config->saveConfig($path, (string) ($value ?? ''), 'default', 0);
        }
        \Mage::app()->getCache()->cleanType('config');
    } catch (\Throwable) {
        // DB not available; nothing to restore.
    }
}

function enablePrivacyCenter(): void
{
    privacyConfig('privacy/general/enabled', '1');
    privacyConfig('privacy/general/allow_guests', '1');
    privacyConfig('system/rate_limit/privacy_request', '0');
    privacyConfig('system/rate_limit/privacy_request_recipient', '0');
}

/**
 * The ID of a new privacy request of the fixture customer, saved directly for the admin tests.
 */
function createPrivacyRequest(string $type, string $status): int
{
    \Mage::app();
    $customer = \Mage::getModel('customer/customer')->load((int) fixtures('customer_id'));
    $request = \Mage::getModel('privacycenter/request')
        ->setStoreId(1)
        ->setCustomerId((int) $customer->getId())
        ->setEmail((string) $customer->getEmail())
        ->setType($type)
        ->setStatus($status)
        ->save();
    return (int) $request->getId();
}

/**
 * A second customer with a confirmed erasure, so that a test proves what the first customer cannot see or change.
 *
 * @return array{customerId: int, requestId: int}
 */
function createOtherPrivacyCustomer(): array
{
    \Mage::app();
    $customer = \Mage::getModel('customer/customer')->setWebsiteId(1)->loadByEmail(PRIVACY_TEST_OTHER_EMAIL);
    if (!$customer->getId()) {
        $customer->setStoreId(1)->setGroupId(1)->setFirstname('Other')->setLastname('Customer')
            ->setEmail(PRIVACY_TEST_OTHER_EMAIL)->save();
    }
    $request = \Mage::getModel('privacycenter/request')
        ->setStoreId(1)
        ->setCustomerId((int) $customer->getId())
        ->setEmail(PRIVACY_TEST_OTHER_EMAIL)
        ->setType('erasure')
        ->setStatus('confirmed')
        ->save();
    return ['customerId' => (int) $customer->getId(), 'requestId' => (int) $request->getId()];
}

afterAll(function (): void {
    try {
        \Mage::app();
        $adapter = \Mage::getSingleton('core/resource')->getConnection('core_write');
        $customer = \Mage::getModel('customer/customer')->load((int) fixtures('customer_id'));
        $adapter->delete('privacy_request', ['email IN (?)' => [PRIVACY_TEST_GUEST_EMAIL, PRIVACY_TEST_OTHER_EMAIL, (string) $customer->getEmail()]]);
        \Mage::register('isSecureArea', true, true);
        $other = \Mage::getModel('customer/customer')->setWebsiteId(1)->loadByEmail(PRIVACY_TEST_OTHER_EMAIL);
        if ($other->getId()) {
            $other->delete();
        }
        \Mage::unregister('isSecureArea');
    } catch (\Throwable) {
        // DB not available; nothing to clean.
    }
    restorePrivacyConfig();
});

describe('POST /api/rest/v2/privacy-requests (guest)', function (): void {
    it('answers the same for any email, with no ID', function (): void {
        enablePrivacyCenter();

        $response = apiPost('/api/rest/v2/privacy-requests', ['email' => PRIVACY_TEST_GUEST_EMAIL, 'type' => 'export']);

        expect($response['status'])->toBe(202);
        expect($response['json']['status'])->toBe('pending');
        expect($response['json']['id'] ?? null)->toBeNull();
        expect($response['json']['customerId'] ?? null)->toBeNull();
    });

    it('refuses an unknown field', function (): void {
        enablePrivacyCenter();

        $response = apiPost('/api/rest/v2/privacy-requests', ['email' => PRIVACY_TEST_GUEST_EMAIL, 'type' => 'export', 'customerId' => 1]);

        expect($response['status'])->toBe(400);
    });

    it('refuses an invalid email and an unknown type', function (): void {
        enablePrivacyCenter();

        $response = apiPost('/api/rest/v2/privacy-requests', ['email' => 'not-an-email', 'type' => 'everything']);

        expect($response['status'])->toBe(422);
    });

    it('refuses a token that no request has', function (): void {
        enablePrivacyCenter();

        $response = apiPost('/api/rest/v2/privacy-requests/confirm', ['token' => str_repeat('a', 64)]);

        expect($response['status'])->toBe(404);
    });
});

describe('/api/rest/v2/customers/me/privacy-requests', function (): void {
    it('requires authentication', function (): void {
        $response = apiPost('/api/rest/v2/customers/me/privacy-requests', ['type' => 'erasure']);

        expect($response['status'])->toBeUnauthorized();
    });

    it('takes one open erasure, which the customer can cancel', function (): void {
        enablePrivacyCenter();
        $other = createOtherPrivacyCustomer();

        $created = apiPost('/api/rest/v2/customers/me/privacy-requests', ['type' => 'erasure'], customerToken());
        $second = apiPost('/api/rest/v2/customers/me/privacy-requests', ['type' => 'erasure'], customerToken());
        $list = apiGet('/api/rest/v2/customers/me/privacy-requests', customerToken());
        $canceled = apiPost("/api/rest/v2/customers/me/privacy-requests/{$created['json']['id']}/cancel", [], customerToken());

        expect($created['status'])->toBe(201);
        expect($created['json']['type'])->toBe('erasure');
        expect($created['json']['status'])->toBe('pending');
        expect($created['json']['adminNote'] ?? null)->toBeNull();
        expect($second['status'])->toBe(409);
        expect(array_column($list['json']['member'] ?? $list['json'], 'id'))->toContain($created['json']['id'])
            ->not->toContain($other['requestId']);
        expect($canceled['status'])->toBe(200);
        expect($canceled['json']['status'])->toBe('canceled');
    });

    it('cannot cancel the request of another customer', function (): void {
        enablePrivacyCenter();
        $id = createPrivacyRequest('erasure', 'confirmed');
        $other = createOtherPrivacyCustomer();

        $response = apiPost("/api/rest/v2/customers/me/privacy-requests/{$id}/cancel", [], customerToken($other['customerId']));

        expect($response['status'])->toBe(404);
    });
});

describe('/api/rest/v2/privacy-requests (staff)', function (): void {
    it('forbids a customer to list every request', function (): void {
        $response = apiGet('/api/rest/v2/privacy-requests', customerToken());

        expect($response['status'])->toBeForbidden();
    });

    it('lists the requests with their filters and the admin fields', function (): void {
        $id = createPrivacyRequest('export', 'pending');

        $response = apiGet('/api/rest/v2/privacy-requests?type=export&status=pending', adminToken());
        $ids = array_column($response['json']['member'] ?? $response['json'], 'id');

        expect($response['status'])->toBe(200);
        expect($ids)->toContain($id);
    });

    it('refuses a customer ID that is not a number', function (): void {
        $response = apiGet('/api/rest/v2/privacy-requests?customerId=abc', adminToken());

        expect($response['status'])->toBe(400);
    });

    it('approves a confirmed erasure, and refuses to approve it twice', function (): void {
        $id = createPrivacyRequest('erasure', 'confirmed');

        $approved = apiPost("/api/rest/v2/privacy-requests/{$id}/approve", [], adminToken());
        $rejected = apiPost("/api/rest/v2/privacy-requests/{$id}/reject", ['reason' => 'Identity not proven'], adminToken());
        $again = apiPost("/api/rest/v2/privacy-requests/{$id}/approve", [], adminToken());

        expect($approved['status'])->toBe(200);
        expect($approved['json']['approvedAt'])->not->toBeNull();
        expect($rejected['json']['status'])->toBe('rejected');
        expect($rejected['json']['adminNote'])->toContain('Identity not proven');
        expect($again['status'])->toBe(409);
    });
});

describe('the terms and conditions', function (): void {
    it('lists the terms of a location for anybody', function (): void {
        $checkout = apiGet('/api/rest/v2/checkout-agreements');
        $registration = apiGet('/api/rest/v2/checkout-agreements?location=registration');
        $invalid = apiGet('/api/rest/v2/checkout-agreements?location=everywhere');

        expect($checkout['status'])->toBe(200);
        expect($registration['status'])->toBe(200);
        expect($invalid['status'])->toBe(400);
    });

    it('lists the pending terms and the acceptances of the customer only with a customer token', function (): void {
        expect(apiGet('/api/rest/v2/customers/me/checkout-agreements/pending')['status'])->toBeUnauthorized();
        expect(apiGet('/api/rest/v2/customers/me/agreement-acceptances', customerToken())['status'])->toBe(200);
    });

    it('refuses to accept terms that do not wait for the customer', function (): void {
        $response = apiPost('/api/rest/v2/customers/me/agreement-acceptances', ['agreementId' => 999999999], customerToken());

        expect($response['status'])->toBe(422);
    });
});
