<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 index management tests: permission gates, list, mode change and reindex.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

/**
 * @return array<string, mixed>
 */
function indexProcessByCode(string $indexerCode): array
{
    $response = apiGet('/api/rest/v2/index-processes', adminToken());
    expect($response['status'])->toBe(200);

    foreach (getItems($response) as $item) {
        if ($item['indexerCode'] === $indexerCode) {
            return $item;
        }
    }

    throw new RuntimeException("Index process '$indexerCode' not listed");
}

describe('Index process permission enforcement (REST)', function (): void {

    it('denies the list without authentication', function (): void {
        $response = apiGet('/api/rest/v2/index-processes');
        expect($response['status'])->toBe(401);
    });

    it('denies the list with a customer token', function (): void {
        $response = apiGet('/api/rest/v2/index-processes', customerToken());
        expect($response['status'])->toBeForbidden();
    });

    it('denies the list with a service token that lacks the permission', function (): void {
        $response = apiGet('/api/rest/v2/index-processes', serviceToken(['cache-types/read']));
        expect($response['status'])->toBeForbidden();
    });

    it('denies an admin whose role does not include system/index', function (): void {
        $token = adminTokenWithAcl(['catalog/products'], 'pest_index_process_deny');
        $response = apiGet('/api/rest/v2/index-processes', $token);
        expect($response['status'])->toBe(403);
    });

    it('denies a reindex with a read-only service token', function (): void {
        $process = indexProcessByCode('cataloginventory_stock');
        $response = apiPost("/api/rest/v2/index-processes/{$process['id']}/reindex", [], serviceToken(['index-processes/read']));
        expect($response['status'])->toBeForbidden();
    });

});

describe('Index process read (REST)', function (): void {

    it('lists the index processes with their status and mode', function (): void {
        $response = apiGet('/api/rest/v2/index-processes', adminToken());
        expect($response['status'])->toBe(200);

        $items = getItems($response);
        expect($items)->not->toBeEmpty();
        expect(array_column($items, 'indexerCode'))->toContain('catalog_product_price', 'cataloginventory_stock');

        foreach ($items as $item) {
            expect($item)->toHaveKeys(['id', 'indexerCode', 'name', 'status', 'mode', 'updateRequired']);
            expect($item['status'])->toBeIn(['pending', 'working', 'require_reindex']);
            expect($item['mode'])->toBeIn(['real_time', 'manual']);
            expect($item['updateRequired'])->toBeBool();
            expect($item['name'])->not->toBe('');
        }
    });

    it('gets one index process by its id', function (): void {
        $listed = indexProcessByCode('cataloginventory_stock');

        $response = apiGet("/api/rest/v2/index-processes/{$listed['id']}", serviceToken(['index-processes/read']));
        expect($response['status'])->toBe(200);
        expect($response['json']['id'])->toBe($listed['id']);
        expect($response['json']['indexerCode'])->toBe('cataloginventory_stock');
    });

    it('returns 404 for an unknown index process', function (): void {
        $response = apiGet('/api/rest/v2/index-processes/999999', adminToken());
        expect($response['status'])->toBeNotFound();
    });

});

describe('Index process write (REST)', function (): void {

    it('changes the mode and reads it back', function (): void {
        $process = indexProcessByCode('cataloginventory_stock');
        $writeToken = serviceToken(['index-processes/write']);
        $originalMode = $process['mode'];
        $otherMode = $originalMode === 'manual' ? 'real_time' : 'manual';

        $update = apiPut("/api/rest/v2/index-processes/{$process['id']}", ['mode' => $otherMode], $writeToken);
        expect($update['status'])->toBe(200);
        expect($update['json']['mode'])->toBe($otherMode);

        $read = apiGet("/api/rest/v2/index-processes/{$process['id']}", adminToken());
        expect($read['json']['mode'])->toBe($otherMode);

        $restore = apiPut("/api/rest/v2/index-processes/{$process['id']}", ['mode' => $originalMode], $writeToken);
        expect($restore['status'])->toBe(200);
        expect($restore['json']['mode'])->toBe($originalMode);
    });

    it('rejects an unknown mode', function (): void {
        $process = indexProcessByCode('cataloginventory_stock');
        $response = apiPut("/api/rest/v2/index-processes/{$process['id']}", ['mode' => 'sometimes'], serviceToken(['index-processes/write']));
        expect($response['status'])->toBe(422);
    });

    it('reindexes one process and returns it as pending', function (): void {
        $process = indexProcessByCode('cataloginventory_stock');

        $response = apiPost("/api/rest/v2/index-processes/{$process['id']}/reindex", [], serviceToken(['index-processes/write']));
        expect($response['status'])->toBe(200);
        expect($response['json']['id'])->toBe($process['id']);
        expect($response['json']['status'])->toBe('pending');
        expect($response['json']['updateRequired'])->toBeFalse();
        expect($response['json']['endedAt'])->not->toBeNull();
    });

});
