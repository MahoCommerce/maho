<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Design Change and Theme Tests (READ + WRITE)
 *
 * Covers the read-only /themes listing and REST CRUD on /design-changes,
 * including the overlap rule of the design change model.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

function deleteDesignChange(int $id): void
{
    try {
        $change = Mage::getModel('core/design')->load($id);
        if ($change->getId()) {
            $change->delete();
        }
    } catch (\Throwable) {
        // The test already deleted it through the API.
    }
}

describe('Themes', function (): void {

    it('rejects anonymous and customer reads', function (): void {
        expect(apiGet('/api/rest/v2/themes')['status'])->toBeUnauthorized();
        expect(apiGet('/api/rest/v2/themes', customerToken())['status'])->toBeForbidden();
    });

    it('rejects a service token without the read permission', function (): void {
        expect(apiGet('/api/rest/v2/themes', serviceToken(['cms-pages/read']))['status'])->toBeForbidden();
    });

    it('lists the installed themes with one default', function (): void {
        $response = apiGet('/api/rest/v2/themes', adminToken());

        expect($response['status'])->toBe(200);
        $items = getItems($response);
        $ids = array_column($items, 'id');
        expect($ids)->toContain('base/default');

        // The default-scope theme is flagged when it is installed on disk.
        $expectedDefault = (Mage::getStoreConfig('design/package/name', 0) ?: 'base')
            . '/' . (Mage::getStoreConfig('design/theme/default', 0) ?: 'default');
        $defaults = array_values(array_filter($items, fn(array $item): bool => $item['isDefault'] === true));
        expect(count($defaults))->toBeLessThanOrEqual(1);
        if (in_array($expectedDefault, $ids, true)) {
            expect($defaults)->toHaveCount(1);
            expect($defaults[0]['id'])->toBe($expectedDefault);
        }

        $base = $items[array_search('base/default', $ids, true)];
        expect($base['package'])->toBe('base');
        expect($base['theme'])->toBe('default');
        expect($base['area'])->toBe('frontend');
    });

    it('returns one theme by its package/theme id', function (): void {
        $response = apiGet('/api/rest/v2/themes/base/default', serviceToken(['themes/read']));

        expect($response['status'])->toBe(200);
        expect($response['json']['id'])->toBe('base/default');
        expect($response['json']['package'])->toBe('base');
    });

    it('returns 404 for a theme that is not installed', function (): void {
        expect(apiGet('/api/rest/v2/themes/base/no_such_theme', adminToken())['status'])->toBeNotFound();
    });

});

describe('Design change permission enforcement', function (): void {

    it('rejects anonymous reads and writes', function (): void {
        expect(apiGet('/api/rest/v2/design-changes')['status'])->toBeUnauthorized();
        expect(apiPost('/api/rest/v2/design-changes', ['storeId' => 1, 'design' => 'base/default'])['status'])->toBeUnauthorized();
    });

    it('rejects a service token without the write permission', function (): void {
        $response = apiPost('/api/rest/v2/design-changes', [
            'storeId' => 1,
            'design' => 'base/default',
        ], serviceToken(['design-changes/read']));

        expect($response['status'])->toBeForbidden();
    });

    it('denies an admin whose role lacks system/design', function (): void {
        $token = adminTokenWithAcl(['catalog/products'], 'pest_design_acl_' . substr(uniqid(), -6));

        expect(apiGet('/api/rest/v2/design-changes', $token)['status'])->toBeForbidden();
    });

});

describe('Design change CRUD lifecycle', function (): void {

    it('creates, reads, updates and deletes a scheduled change', function (): void {
        $writeToken = serviceToken(['design-changes/read', 'design-changes/write']);
        $deleteToken = serviceToken(['design-changes/delete']);

        $create = apiPost('/api/rest/v2/design-changes', [
            'storeId' => 1,
            'design' => 'base/default',
            'dateFrom' => '2090-01-01',
            'dateTo' => '2090-01-31',
        ], $writeToken);

        expect($create['status'])->toBeIn([200, 201]);
        $id = (int) $create['json']['id'];
        expect($id)->toBeGreaterThan(0);
        expect($create['json']['storeId'])->toBe(1);
        expect($create['json']['design'])->toBe('base/default');
        expect($create['json']['dateFrom'])->toBe('2090-01-01');
        expect($create['json']['dateTo'])->toBe('2090-01-31');

        try {
            $read = apiGet("/api/rest/v2/design-changes/{$id}", $writeToken);
            expect($read['status'])->toBe(200);
            expect($read['json']['design'])->toBe('base/default');

            $list = apiGet('/api/rest/v2/design-changes?storeId=1', adminToken());
            expect($list['status'])->toBe(200);
            expect(array_column(getItems($list), 'id'))->toContain($id);

            $overlap = apiPost('/api/rest/v2/design-changes', [
                'storeId' => 1,
                'design' => 'base/default',
                'dateFrom' => '2090-01-15',
                'dateTo' => '2090-02-15',
            ], $writeToken);
            expect($overlap['status'])->toBe(422);

            $update = apiPut("/api/rest/v2/design-changes/{$id}", [
                'dateTo' => '2090-02-28',
            ], $writeToken);
            expect($update['status'])->toBe(200);
            expect($update['json']['dateTo'])->toBe('2090-02-28');
            expect($update['json']['dateFrom'])->toBe('2090-01-01');

            $clear = apiPut("/api/rest/v2/design-changes/{$id}", [
                'dateTo' => null,
            ], $writeToken);
            expect($clear['status'])->toBe(200);
            expect($clear['json']['dateTo'])->toBeNull();

            expect(apiDelete("/api/rest/v2/design-changes/{$id}", $writeToken)['status'])->toBeForbidden();

            $delete = apiDelete("/api/rest/v2/design-changes/{$id}", $deleteToken);
            expect($delete['status'])->toBeIn([200, 204]);
            expect(apiGet("/api/rest/v2/design-changes/{$id}", adminToken())['status'])->toBeNotFound();
        } finally {
            deleteDesignChange($id);
        }
    });

    it('rejects an unknown theme, an unknown store and a reversed date range', function (): void {
        $token = adminToken();

        $badDesign = apiPost('/api/rest/v2/design-changes', [
            'storeId' => 1,
            'design' => 'base/no_such_theme',
        ], $token);
        expect($badDesign['status'])->toBe(400);
        expect($badDesign['json']['message'] ?? '')->toContain('design');

        $badStore = apiPost('/api/rest/v2/design-changes', [
            'storeId' => 999999,
            'design' => 'base/default',
        ], $token);
        expect($badStore['status'])->toBe(400);

        $reversed = apiPost('/api/rest/v2/design-changes', [
            'storeId' => 1,
            'design' => 'base/default',
            'dateFrom' => '2091-02-01',
            'dateTo' => '2091-01-01',
        ], $token);
        expect($reversed['status'])->toBe(400);

        $badFormat = apiPost('/api/rest/v2/design-changes', [
            'storeId' => 1,
            'design' => 'base/default',
            'dateFrom' => '01/02/2091',
        ], $token);
        expect($badFormat['status'])->toBe(400);
    });

});
