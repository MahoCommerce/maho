<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ApiV2Helper;

/**
 * API v2 Coupon Tests (WRITE)
 *
 * Covers coupon scoping (websiteIds / customerGroupIds), per-coupon
 * expirationDate and the rule-level knobs exposed by the Coupon resource.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Coupon scoping', function (): void {

    it('creates a coupon with explicit websiteIds and customerGroupIds that round-trip', function (): void {
        $token = adminToken();
        $code = 'PestScope' . substr(uniqid(), -6);

        $create = apiPost('/api/rest/v2/coupons', [
            'code' => $code,
            'discountType' => 'percent',
            'discountAmount' => 10,
            'websiteIds' => [1],
            'customerGroupIds' => [1],
        ], $token);

        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];
        expect($id)->toBeGreaterThan(0);

        $get = apiGet("/api/rest/v2/coupons/{$id}", $token);
        expect($get['status'])->toBe(200);

        // Exactly the requested scope, not the historical all-groups/all-websites default.
        expect($get['json']['websiteIds'])->toBe([1]);
        expect($get['json']['customerGroupIds'])->toBe([1]);
        expect($get['json']['customerGroupIds'])->not->toContain(0);

        expect(apiDelete("/api/rest/v2/coupons/{$id}", $token)['status'])->toBeIn([200, 204]);
    });

    it('keeps the all-groups default when scoping fields are omitted', function (): void {
        $token = adminToken();
        $code = 'PestAll' . substr(uniqid(), -6);

        $create = apiPost('/api/rest/v2/coupons', [
            'code' => $code,
            'discountType' => 'fixed',
            'discountAmount' => 5,
        ], $token);

        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];

        $get = apiGet("/api/rest/v2/coupons/{$id}", $token);
        expect($get['status'])->toBe(200);
        // NOT LOGGED IN (0) is only present under the all-groups default.
        expect($get['json']['customerGroupIds'])->toContain(0);
        expect($get['json']['customerGroupIds'])->toContain(1);

        expect(apiDelete("/api/rest/v2/coupons/{$id}", $token)['status'])->toBeIn([200, 204]);
    });

    it('rejects unknown website and customer group ids', function (): void {
        $token = adminToken();

        $badWebsite = apiPost('/api/rest/v2/coupons', [
            'code' => 'PestBadW' . substr(uniqid(), -6),
            'discountType' => 'percent',
            'discountAmount' => 10,
            'websiteIds' => [999999],
        ], $token);
        expect($badWebsite['status'])->toBeGreaterThanOrEqual(400);
        expect($badWebsite['status'])->toBeLessThan(500);

        $badGroup = apiPost('/api/rest/v2/coupons', [
            'code' => 'PestBadG' . substr(uniqid(), -6),
            'discountType' => 'percent',
            'discountAmount' => 10,
            'customerGroupIds' => [999999],
        ], $token);
        expect($badGroup['status'])->toBeGreaterThanOrEqual(400);
        expect($badGroup['status'])->toBeLessThan(500);
    });

});

describe('Coupon expiration date', function (): void {

    it('round-trips a per-coupon expirationDate and clears it with an empty string', function (): void {
        $token = adminToken();
        $code = 'PestExp' . substr(uniqid(), -6);

        $create = apiPost('/api/rest/v2/coupons', [
            'code' => $code,
            'discountType' => 'percent',
            'discountAmount' => 15,
            'expirationDate' => '2030-12-31',
        ], $token);

        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];
        expect((string) $create['json']['expirationDate'])->toContain('2030-12-31');

        $get = apiGet("/api/rest/v2/coupons/{$id}", $token);
        expect($get['status'])->toBe(200);
        expect((string) $get['json']['expirationDate'])->toContain('2030-12-31');
        // Distinct from the rule-level toDate, which was never set.
        expect($get['json']['toDate'] ?? null)->toBeNull();

        $update = apiPut("/api/rest/v2/coupons/{$id}", [
            'expirationDate' => '',
        ], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['expirationDate'] ?? null)->toBeNull();

        expect(apiDelete("/api/rest/v2/coupons/{$id}", $token)['status'])->toBeIn([200, 204]);
    });

    it('keeps a custom expirationDate across updates that do not touch it', function (): void {
        $token = adminToken();
        $code = 'PestExpK' . substr(uniqid(), -6);

        $create = apiPost('/api/rest/v2/coupons', [
            'code' => $code,
            'discountType' => 'percent',
            'discountAmount' => 15,
            'expirationDate' => '2030-12-31',
        ], $token);
        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];

        $update = apiPut("/api/rest/v2/coupons/{$id}", [
            'discountAmount' => 20,
        ], $token);
        expect($update['status'])->toBe(200);
        expect((string) ($update['json']['expirationDate'] ?? ''))->toContain('2030-12-31');

        $get = apiGet("/api/rest/v2/coupons/{$id}", $token);
        expect((string) ($get['json']['expirationDate'] ?? ''))->toContain('2030-12-31');

        // The toDate of the rule is not copied to the coupon.
        $toDate = apiPut("/api/rest/v2/coupons/{$id}", [
            'toDate' => '2031-06-30',
        ], $token);
        expect($toDate['status'])->toBe(200);
        expect((string) ($toDate['json']['expirationDate'] ?? ''))->toContain('2030-12-31');

        expect(apiDelete("/api/rest/v2/coupons/{$id}", $token)['status'])->toBeIn([200, 204]);
    });

});

describe('Coupon validation dates', function (): void {

    it('compares the rule dates with the store date, not the UTC date', function (): void {
        ApiV2Helper::ensureMahoBootstrapped();

        // Pick a time zone where the store date is not the UTC date at this hour
        $storeIsAhead = (int) gmdate('G') >= 11;
        $timezone = $storeIsAhead ? 'Pacific/Kiritimati' : 'Etc/GMT+12';
        $config = Mage::getModel('core/config');
        $original = (string) Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, 0);
        $config->saveConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $timezone, 'default', 0);
        Mage::app()->getCache()->cleanType('config');

        try {
            $code = 'PestTz' . substr(uniqid(), -6);
            $create = apiPost('/api/rest/v2/coupons', [
                'code' => $code,
                'discountType' => 'percent',
                'discountAmount' => 10,
                $storeIsAhead ? 'toDate' : 'fromDate' => gmdate('Y-m-d'),
            ], adminToken());
            expect($create['status'])->toBeSuccessful();

            $validate = apiPost('/api/rest/v2/coupons/validate', ['code' => $code]);

            expect($validate['json']['isValid'] ?? null)->toBeFalse()
                ->and($validate['json']['validationMessage'] ?? null)
                ->toBe($storeIsAhead ? 'Coupon has expired' : 'Coupon is not yet active');
        } finally {
            $config->saveConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE, $original, 'default', 0);
            Mage::app()->getCache()->cleanType('config');
        }
    });

});

describe('Coupon rule-level knobs', function (): void {

    it('round-trips buy-X-get-Y quantities and processing flags', function (): void {
        $token = adminToken();
        $code = 'PestKnob' . substr(uniqid(), -6);

        $create = apiPost('/api/rest/v2/coupons', [
            'code' => $code,
            'discountType' => 'buy_x_get_y',
            'discountAmount' => 1,
            'discountQty' => 2,
            'discountStep' => 3,
            'sortOrder' => 5,
            'stopRulesProcessing' => true,
            'applyToShipping' => true,
            'simpleFreeShipping' => 1,
        ], $token);

        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];

        $get = apiGet("/api/rest/v2/coupons/{$id}", $token);
        expect($get['status'])->toBe(200);
        expect((float) $get['json']['discountQty'])->toBe(2.0);
        expect($get['json']['discountStep'])->toBe(3);
        expect($get['json']['sortOrder'])->toBe(5);
        expect($get['json']['stopRulesProcessing'])->toBeTrue();
        expect($get['json']['applyToShipping'])->toBeTrue();
        expect($get['json']['simpleFreeShipping'])->toBe(1);

        // Read-only coupon metadata is exposed.
        expect($get['json'])->toHaveKey('isPrimary');
        expect($get['json'])->toHaveKey('createdAt');

        expect(apiDelete("/api/rest/v2/coupons/{$id}", $token)['status'])->toBeIn([200, 204]);
    });

});
