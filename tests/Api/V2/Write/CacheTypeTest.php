<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 cache management tests: permission gates, list, enable and disable, refresh and flush.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('Cache type permission enforcement (REST)', function (): void {

    it('denies the list without authentication', function (): void {
        $response = apiGet('/api/rest/v2/cache-types');
        expect($response['status'])->toBe(401);
    });

    it('denies the list with a customer token', function (): void {
        $response = apiGet('/api/rest/v2/cache-types', customerToken());
        expect($response['status'])->toBeForbidden();
    });

    it('denies the list with a service token that lacks the permission', function (): void {
        $response = apiGet('/api/rest/v2/cache-types', serviceToken(['config-settings/read']));
        expect($response['status'])->toBeForbidden();
    });

    it('denies an admin whose role does not include system/cache', function (): void {
        $token = adminTokenWithAcl(['catalog/products'], 'pest_cache_type_deny');
        $response = apiGet('/api/rest/v2/cache-types', $token);
        expect($response['status'])->toBe(403);
    });

    it('denies a refresh with a read-only service token', function (): void {
        $response = apiPost('/api/rest/v2/cache-types/config/refresh', [], serviceToken(['cache-types/read']));
        expect($response['status'])->toBeForbidden();
    });

});

describe('Cache type read (REST)', function (): void {

    it('lists the cache types with their state', function (): void {
        $response = apiGet('/api/rest/v2/cache-types', adminToken());
        expect($response['status'])->toBe(200);

        $items = getItems($response);
        $codes = array_column($items, 'code');
        expect($codes)->toContain('config', 'layout', 'block_html');

        $config = $items[array_search('config', $codes, true)];
        expect($config)->toHaveKeys(['code', 'label', 'description', 'enabled', 'invalidated', 'tags']);
        expect($config['enabled'])->toBeBool();
        expect($config['invalidated'])->toBeBool();
        expect($config['label'])->not->toBe('');
    });

    it('gets one cache type by its code', function (): void {
        $response = apiGet('/api/rest/v2/cache-types/config', serviceToken(['cache-types/read']));
        expect($response['status'])->toBe(200);
        expect($response['json']['code'])->toBe('config');
        expect($response['json']['tags'])->toContain('CONFIG');
    });

    it('returns 404 for an unknown cache type', function (): void {
        $response = apiGet('/api/rest/v2/cache-types/no_such_type', adminToken());
        expect($response['status'])->toBeNotFound();
    });

});

describe('Cache type write (REST)', function (): void {

    it('disables and enables a cache type and reads the state back', function (): void {
        $writeToken = serviceToken(['cache-types/write']);
        $readToken = serviceToken(['cache-types/read']);

        $before = apiGet('/api/rest/v2/cache-types/block_html', $readToken);
        expect($before['status'])->toBe(200);
        $wasEnabled = $before['json']['enabled'];

        $disable = apiPut('/api/rest/v2/cache-types/block_html', ['enabled' => false], $writeToken);
        expect($disable['status'])->toBe(200);
        expect($disable['json']['code'])->toBe('block_html');
        expect($disable['json']['enabled'])->toBeFalse();

        $disabled = apiGet('/api/rest/v2/cache-types/block_html', $readToken);
        expect($disabled['json']['enabled'])->toBeFalse();

        $enable = apiPut('/api/rest/v2/cache-types/block_html', ['enabled' => true], $writeToken);
        expect($enable['status'])->toBe(200);
        expect($enable['json']['enabled'])->toBeTrue();

        $enabled = apiGet('/api/rest/v2/cache-types/block_html', $readToken);
        expect($enabled['json']['enabled'])->toBeTrue();

        $restore = apiPut('/api/rest/v2/cache-types/block_html', ['enabled' => $wasEnabled], $writeToken);
        expect($restore['status'])->toBe(200);
        expect($restore['json']['enabled'])->toBe($wasEnabled);
    });

    it('requires a boolean enabled flag', function (): void {
        $missing = apiPut('/api/rest/v2/cache-types/block_html', [], serviceToken(['cache-types/write']));
        expect($missing['status'])->toBe(422);

        $wrongType = apiPut('/api/rest/v2/cache-types/block_html', ['enabled' => 'yes'], serviceToken(['cache-types/write']));
        expect($wrongType['status'])->toBe(400);
    });

    it('refreshes one cache type and clears its invalidated state', function (): void {
        $refresh = apiPost('/api/rest/v2/cache-types/layout/refresh', [], serviceToken(['cache-types/write']));
        expect($refresh['status'])->toBe(200);
        expect($refresh['json']['code'])->toBe('layout');
        expect($refresh['json']['invalidated'])->toBeFalse();

        $after = apiGet('/api/rest/v2/cache-types/layout', adminToken());
        expect($after['json']['invalidated'])->toBeFalse();
    });

    it('returns 404 when refreshing an unknown cache type', function (): void {
        $response = apiPost('/api/rest/v2/cache-types/no_such_type/refresh', [], adminToken());
        expect($response['status'])->toBeNotFound();
    });

    it('flushes the whole cache storage', function (): void {
        $response = apiPost('/api/rest/v2/cache-types/flush-all', [], serviceToken(['cache-types/write']));
        expect($response['status'])->toBe(200);
        expect($response['json']['success'])->toBeTrue();
        expect($response['json']['flushed'])->toBeGreaterThan(0);
    });

});
