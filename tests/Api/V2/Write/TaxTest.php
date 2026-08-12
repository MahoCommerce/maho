<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Tax Tests (READ + WRITE)
 *
 * Covers REST CRUD for /tax-classes, /tax-rates and /tax-rules. All tax data is
 * admin-only.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Tax classes', function (): void {

    it('rejects anonymous access', function (): void {
        expect(apiGet('/api/rest/v2/tax-classes')['status'])->toBeUnauthorized();
    });

    it('lists classes for an admin', function (): void {
        $response = apiGet('/api/rest/v2/tax-classes', adminToken());

        expect($response['status'])->toBe(200);
        expect(getItems($response))->toBeArray();
    });

    it('creates, reads and deletes a product tax class', function (): void {
        $name = 'API Class ' . substr(uniqid(), -6);
        $create = apiPost('/api/rest/v2/tax-classes', [
            'className' => $name,
            'classType' => 'PRODUCT',
        ], adminToken());

        expect($create['status'])->toBeSuccessful();
        expect($create['json']['className'])->toBe($name);
        expect($create['json']['classType'])->toBe('PRODUCT');
        $id = (int) $create['json']['id'];
        trackCreated('tax_class', $id);

        $get = apiGet("/api/rest/v2/tax-classes/{$id}", adminToken());
        expect($get['status'])->toBe(200);

        expect(apiDelete("/api/rest/v2/tax-classes/{$id}", adminToken())['status'])->toBeSuccessful();
    });

    it('rejects an invalid class type', function (): void {
        $response = apiPost('/api/rest/v2/tax-classes', [
            'className' => 'Bad ' . uniqid(),
            'classType' => 'NONSENSE',
        ], adminToken());

        expect($response['status'])->toBeGreaterThanOrEqual(400);
        expect($response['status'])->toBeLessThan(500);
    });

});

describe('Tax rates', function (): void {

    it('rejects anonymous access', function (): void {
        expect(apiGet('/api/rest/v2/tax-rates')['status'])->toBeUnauthorized();
    });

    it('creates, reads and deletes a rate', function (): void {
        $code = 'APIRate' . substr(uniqid(), -6);
        $create = apiPost('/api/rest/v2/tax-rates', [
            'code' => $code,
            'taxCountryId' => 'US',
            'taxPostcode' => '*',
            'rate' => 8.25,
        ], adminToken());

        expect($create['status'])->toBeSuccessful();
        expect($create['json']['code'])->toBe($code);
        expect((float) $create['json']['rate'])->toBe(8.25);
        $id = (int) $create['json']['id'];
        trackCreated('tax_rate', $id);

        expect(apiGet("/api/rest/v2/tax-rates/{$id}", adminToken())['status'])->toBe(200);
        expect(apiDelete("/api/rest/v2/tax-rates/{$id}", adminToken())['status'])->toBeSuccessful();
    });

    it('round-trips per-store titles and preserves them on a title-less update', function (): void {
        $token = adminToken();

        $stores = getItems(apiGet('/api/rest/v2/stores'));
        $storeId = (int) ($stores[0]['id'] ?? 0);
        if (!$storeId) {
            $this->markTestSkipped('No store views available for tax rate titles');
        }

        $code = 'APIRateTitle' . substr(uniqid(), -6);
        $create = apiPost('/api/rest/v2/tax-rates', [
            'code' => $code,
            'taxCountryId' => 'US',
            'taxPostcode' => '*',
            'rate' => 7.5,
            'titles' => [
                ['storeId' => $storeId, 'title' => 'Pest VAT'],
            ],
        ], $token);

        expect($create['status'])->toBeSuccessful();
        $id = (int) $create['json']['id'];
        trackCreated('tax_rate', $id);

        $get = apiGet("/api/rest/v2/tax-rates/{$id}", $token);
        expect($get['status'])->toBe(200);
        expect($get['json']['titles'])->toContain(['storeId' => $storeId, 'title' => 'Pest VAT']);

        // An update that omits titles must not wipe them.
        $update = apiPut("/api/rest/v2/tax-rates/{$id}", ['rate' => 8.0], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['titles'])->toContain(['storeId' => $storeId, 'title' => 'Pest VAT']);

        // An update that provides titles replaces them.
        $rename = apiPut("/api/rest/v2/tax-rates/{$id}", [
            'titles' => [
                ['storeId' => $storeId, 'title' => 'Pest VAT Renamed'],
            ],
        ], $token);
        expect($rename['status'])->toBe(200);
        expect($rename['json']['titles'])->toContain(['storeId' => $storeId, 'title' => 'Pest VAT Renamed']);

        expect(apiDelete("/api/rest/v2/tax-rates/{$id}", $token)['status'])->toBeSuccessful();
    });

    it('rejects a title for an unknown store', function (): void {
        $response = apiPost('/api/rest/v2/tax-rates', [
            'code' => 'BadStore' . uniqid(),
            'taxCountryId' => 'US',
            'taxPostcode' => '*',
            'rate' => 5,
            'titles' => [
                ['storeId' => 999999, 'title' => 'Nope'],
            ],
        ], adminToken());

        expect($response['status'])->toBeGreaterThanOrEqual(400);
        expect($response['status'])->toBeLessThan(500);
    });

    it('rejects a rate with no country', function (): void {
        $response = apiPost('/api/rest/v2/tax-rates', [
            'code' => 'NoCountry' . uniqid(),
            'rate' => 5,
        ], adminToken());

        expect($response['status'])->toBeGreaterThanOrEqual(400);
        expect($response['status'])->toBeLessThan(500);
    });

});

describe('Tax rules', function (): void {

    it('creates a rule wiring classes and a rate, then deletes it', function (): void {
        $token = adminToken();

        // Build the three association members the rule needs.
        $custClass = apiPost('/api/rest/v2/tax-classes', [
            'className' => 'API Cust ' . substr(uniqid(), -6),
            'classType' => 'CUSTOMER',
        ], $token);
        $prodClass = apiPost('/api/rest/v2/tax-classes', [
            'className' => 'API Prod ' . substr(uniqid(), -6),
            'classType' => 'PRODUCT',
        ], $token);
        $rate = apiPost('/api/rest/v2/tax-rates', [
            'code' => 'APIRuleRate' . substr(uniqid(), -6),
            'taxCountryId' => 'US',
            'taxPostcode' => '*',
            'rate' => 10,
        ], $token);

        foreach ([$custClass, $prodClass, $rate] as $r) {
            expect($r['status'])->toBeSuccessful();
        }
        $custId = (int) $custClass['json']['id'];
        $prodId = (int) $prodClass['json']['id'];
        $rateId = (int) $rate['json']['id'];
        trackCreated('tax_class', $custId);
        trackCreated('tax_class', $prodId);
        trackCreated('tax_rate', $rateId);

        $create = apiPost('/api/rest/v2/tax-rules', [
            'code' => 'APIRule ' . substr(uniqid(), -6),
            'priority' => 0,
            'position' => 0,
            'customerTaxClassIds' => [$custId],
            'productTaxClassIds' => [$prodId],
            'taxRateIds' => [$rateId],
        ], $token);

        expect($create['status'])->toBeSuccessful();
        $ruleId = (int) $create['json']['id'];
        trackCreated('tax_rule', $ruleId);

        // Associations should round-trip on read.
        $get = apiGet("/api/rest/v2/tax-rules/{$ruleId}", $token);
        expect($get['status'])->toBe(200);
        expect($get['json']['customerTaxClassIds'])->toContain($custId);
        expect($get['json']['productTaxClassIds'])->toContain($prodId);
        expect($get['json']['taxRateIds'])->toContain($rateId);

        expect(apiDelete("/api/rest/v2/tax-rules/{$ruleId}", $token)['status'])->toBeSuccessful();
        apiDelete("/api/rest/v2/tax-rates/{$rateId}", $token);
        apiDelete("/api/rest/v2/tax-classes/{$custId}", $token);
        apiDelete("/api/rest/v2/tax-classes/{$prodId}", $token);
    });

    it('rejects a rule with no associations', function (): void {
        $response = apiPost('/api/rest/v2/tax-rules', [
            'code' => 'Empty ' . uniqid(),
        ], adminToken());

        expect($response['status'])->toBeGreaterThanOrEqual(400);
        expect($response['status'])->toBeLessThan(500);
    });

});
