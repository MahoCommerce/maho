<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 system configuration tests: permission gates, read, write, secret masking and inheritance.
 *
 * @group write
 */

const CONFIG_SETTING_TEST_PATH = 'general/store_information/hours';
const CONFIG_SETTING_SECRET_PATH = 'system/smtp/password';

function configSettingTestStoreCode(): string
{
    return Mage::app()->getDefaultStoreView()->getCode();
}

beforeAll(function (): void {
    Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
});

afterAll(function (): void {
    $storeId = (int) Mage::app()->getDefaultStoreView()->getId();
    Mage::getConfig()->deleteConfig(CONFIG_SETTING_TEST_PATH, 'default', 0);
    Mage::getConfig()->deleteConfig(CONFIG_SETTING_TEST_PATH, 'stores', $storeId);
    Mage::getConfig()->reinit();
    Mage::app()->reinitStores();
    cleanupTestData();
});

describe('Config setting permission enforcement (REST)', function (): void {

    it('denies the list without authentication', function (): void {
        $response = apiGet('/api/rest/v2/config-settings');
        expect($response['status'])->toBe(401);
    });

    it('denies the list with a customer token', function (): void {
        $response = apiGet('/api/rest/v2/config-settings', customerToken());
        expect($response['status'])->toBeForbidden();
    });

    it('denies the list with a service token that lacks the permission', function (): void {
        $response = apiGet('/api/rest/v2/config-settings', serviceToken(['cms-blocks/write']));
        expect($response['status'])->toBeForbidden();
    });

    it('denies an admin whose role does not include system/config', function (): void {
        $token = adminTokenWithAcl(['catalog/products'], 'pest_config_setting_deny');
        $response = apiGet('/api/rest/v2/config-settings', $token);
        expect($response['status'])->toBe(403);
    });

    it('denies a write with a read-only service token', function (): void {
        $response = apiPut('/api/rest/v2/config-settings/' . CONFIG_SETTING_TEST_PATH, [
            'value' => 'denied',
        ], serviceToken(['config-settings/read']));
        expect($response['status'])->toBeForbidden();
    });

});

describe('Config setting read (REST)', function (): void {

    it('lists the settings of a path prefix with their labels', function (): void {
        $response = apiGet('/api/rest/v2/config-settings?pathPrefix=general/store_information', adminToken());
        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();

        $paths = array_column($items, 'path');
        expect($paths)->toContain(CONFIG_SETTING_TEST_PATH);

        $item = $items[array_search(CONFIG_SETTING_TEST_PATH, $paths, true)];
        expect($item['scope'])->toBe('default');
        expect($item['inherited'])->toBeFalse();
        expect($item['isSensitive'])->toBeFalse();
        expect($item['label'])->toBe('Store Hours of Operation');
        expect($item['sectionLabel'])->toBe('General');
        expect($item['groupLabel'])->toBe('Store Information');

        foreach ($items as $listed) {
            expect($listed['path'])->toStartWith('general/store_information');
        }
    });

    it('finds a setting by a partial match on the label', function (): void {
        $response = apiGet('/api/rest/v2/config-settings?search=Hours+of+Operation', serviceToken(['config-settings/read']));
        expect($response['status'])->toBe(200);
        expect(array_column(getItems($response), 'path'))->toContain(CONFIG_SETTING_TEST_PATH);
    });

    it('finds a setting by a word of its help text and returns the help text', function (): void {
        $response = apiGet('/api/rest/v2/config-settings?search=Counts+only+a+lookup+that+finds+nothing', serviceToken(['config-settings/read']));
        expect($response['status'])->toBe(200);
        $items = getItems($response);
        expect($items)->not->toBe([]);
        expect($items[0]['comment'])->toContain('Counts only a lookup that finds nothing');
    });

    it('gets one setting by its path', function (): void {
        $response = apiGet('/api/rest/v2/config-settings/' . CONFIG_SETTING_TEST_PATH, adminToken());
        expect($response['status'])->toBe(200);
        expect($response['json']['path'])->toBe(CONFIG_SETTING_TEST_PATH);
        expect($response['json']['frontendType'])->toBe('text');
    });

    it('returns 404 for a path that is not a system.xml field', function (): void {
        $response = apiGet('/api/rest/v2/config-settings/general/no_such_group/no_such_field', adminToken());
        expect($response['status'])->toBeNotFound();
    });

    it('rejects an unknown scope code', function (): void {
        $response = apiGet('/api/rest/v2/config-settings/' . CONFIG_SETTING_TEST_PATH . '?scope=stores&scopeCode=no_such_store', adminToken());
        expect($response['status'])->toBe(422);
    });

    it('masks the value of a sensitive field', function (): void {
        $response = apiGet('/api/rest/v2/config-settings/' . CONFIG_SETTING_SECRET_PATH, adminToken());
        expect($response['status'])->toBe(200);
        expect($response['json']['isSensitive'])->toBeTrue();
        expect($response['json']['value'] ?? null)->toBeNull();
        expect($response['json']['frontendType'])->toBe('obscure');

        $list = apiGet('/api/rest/v2/config-settings?pathPrefix=system/smtp', adminToken());
        foreach (getItems($list) as $item) {
            if ($item['path'] === CONFIG_SETTING_SECRET_PATH) {
                expect($item['value'] ?? null)->toBeNull();
                expect($item['isSensitive'])->toBeTrue();
            }
        }
    });

});

describe('Config setting write and inheritance (REST)', function (): void {

    it('writes a value at the default scope and reads it back', function (): void {
        $writeToken = serviceToken(['config-settings/write']);

        $update = apiPut('/api/rest/v2/config-settings/' . CONFIG_SETTING_TEST_PATH, [
            'value' => 'Pest 9-17',
        ], $writeToken);
        expect($update['status'])->toBe(200);
        expect($update['json']['path'])->toBe(CONFIG_SETTING_TEST_PATH);
        expect($update['json']['value'])->toBe('Pest 9-17');
        expect($update['json']['scope'])->toBe('default');

        $read = apiGet('/api/rest/v2/config-settings/' . CONFIG_SETTING_TEST_PATH, serviceToken(['config-settings/read']));
        expect($read['status'])->toBe(200);
        expect($read['json']['value'])->toBe('Pest 9-17');
    });

    it('requires the value in the body', function (): void {
        $response = apiPut('/api/rest/v2/config-settings/' . CONFIG_SETTING_TEST_PATH, [
            'scope' => 'default',
        ], serviceToken(['config-settings/write']));
        expect($response['status'])->toBe(422);
    });

    it('rejects a write to a path that is not a system.xml field', function (): void {
        $response = apiPut('/api/rest/v2/config-settings/general/no_such_group/no_such_field', [
            'value' => 'x',
        ], serviceToken(['config-settings/write']));
        expect($response['status'])->toBe(404);
    });

    it('requires a scope code for the websites scope', function (): void {
        $response = apiPut('/api/rest/v2/config-settings/' . CONFIG_SETTING_TEST_PATH, [
            'value' => 'x',
            'scope' => 'websites',
        ], serviceToken(['config-settings/write']));
        expect($response['status'])->toBe(422);
    });

    it('overrides a value at a store view and restores inheritance with delete', function (): void {
        $storeCode = configSettingTestStoreCode();
        $writeToken = serviceToken(['config-settings/write']);
        $readToken = serviceToken(['config-settings/read']);
        $deleteToken = serviceToken(['config-settings/delete']);
        $query = '?scope=stores&scopeCode=' . $storeCode;

        $before = apiGet('/api/rest/v2/config-settings/' . CONFIG_SETTING_TEST_PATH . $query, $readToken);
        expect($before['status'])->toBe(200);
        expect($before['json']['inherited'])->toBeTrue();
        expect($before['json']['scope'])->toBe('stores');
        expect($before['json']['scopeCode'])->toBe($storeCode);

        $update = apiPut('/api/rest/v2/config-settings/' . CONFIG_SETTING_TEST_PATH, [
            'value' => 'Pest store hours',
            'scope' => 'stores',
            'scopeCode' => $storeCode,
        ], $writeToken);
        expect($update['status'])->toBe(200);
        expect($update['json']['value'])->toBe('Pest store hours');
        expect($update['json']['inherited'])->toBeFalse();

        $overridden = apiGet('/api/rest/v2/config-settings/' . CONFIG_SETTING_TEST_PATH . $query, $readToken);
        expect($overridden['json']['value'])->toBe('Pest store hours');
        expect($overridden['json']['inherited'])->toBeFalse();

        $denied = apiDelete('/api/rest/v2/config-settings/' . CONFIG_SETTING_TEST_PATH . $query, $writeToken);
        expect($denied['status'])->toBeForbidden();

        $delete = apiDelete('/api/rest/v2/config-settings/' . CONFIG_SETTING_TEST_PATH . $query, $deleteToken);
        expect($delete['status'])->toBeIn([200, 204]);

        $after = apiGet('/api/rest/v2/config-settings/' . CONFIG_SETTING_TEST_PATH . $query, $readToken);
        expect($after['json']['inherited'])->toBeTrue();
        expect($after['json']['value'])->not->toBe('Pest store hours');
    });

    it('refuses to delete at the default scope', function (): void {
        $response = apiDelete('/api/rest/v2/config-settings/' . CONFIG_SETTING_TEST_PATH, serviceToken(['config-settings/delete']));
        expect($response['status'])->toBe(422);
    });

});
