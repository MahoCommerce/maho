<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 URL Rewrite Tests (READ + WRITE)
 *
 * Covers REST CRUD on /url-rewrites for custom rewrites, the list filters,
 * and the refusal to change a system rewrite the indexer owns.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

function deleteUrlRewrite(int $id): void
{
    try {
        $rewrite = Mage::getModel('core/url_rewrite')->load($id);
        if ($rewrite->getId()) {
            $rewrite->delete();
        }
    } catch (\Throwable) {
        // The test already deleted it through the API.
    }
}

function systemUrlRewriteId(): ?int
{
    $rewrite = Mage::getModel('core/url_rewrite')->getCollection()
        ->addFieldToFilter('is_system', 1)
        ->setPageSize(1)
        ->getFirstItem();

    return $rewrite->getId() ? (int) $rewrite->getId() : null;
}

describe('URL rewrite permission enforcement', function (): void {

    it('rejects anonymous reads and writes', function (): void {
        expect(apiGet('/api/rest/v2/url-rewrites')['status'])->toBeUnauthorized();
        expect(apiPost('/api/rest/v2/url-rewrites', ['storeId' => 1, 'requestPath' => 'x'])['status'])->toBeUnauthorized();
    });

    it('rejects a customer token', function (): void {
        expect(apiGet('/api/rest/v2/url-rewrites', customerToken())['status'])->toBeForbidden();
    });

    it('rejects a service token without the write permission', function (): void {
        $response = apiPost('/api/rest/v2/url-rewrites', [
            'storeId' => 1,
            'requestPath' => 'pest-noperm.html',
            'idPath' => 'pest/noperm',
            'targetPath' => 'cms/index/index',
        ], serviceToken(['url-rewrites/read']));

        expect($response['status'])->toBeForbidden();
    });

    it('denies an admin whose role lacks catalog/urlrewrite', function (): void {
        $token = adminTokenWithAcl(['catalog/products'], 'pest_urlrw_acl_' . substr(uniqid(), -6));

        expect(apiGet('/api/rest/v2/url-rewrites', $token)['status'])->toBeForbidden();
    });

});

describe('URL rewrite CRUD lifecycle', function (): void {

    it('creates, reads, lists, updates and deletes a custom rewrite', function (): void {
        $writeToken = serviceToken(['url-rewrites/read', 'url-rewrites/write']);
        $deleteToken = serviceToken(['url-rewrites/delete']);
        $suffix = substr(uniqid(), -6);
        $requestPath = "pest-rewrite-{$suffix}.html";

        $create = apiPost('/api/rest/v2/url-rewrites', [
            'storeId' => 1,
            'requestPath' => strtoupper($requestPath),
            'idPath' => "pest/{$suffix}",
            'targetPath' => 'cms/index/index',
            'options' => 'RP',
            'description' => 'Pest custom rewrite',
        ], $writeToken);

        expect($create['status'])->toBeIn([200, 201]);
        $id = (int) $create['json']['id'];
        expect($id)->toBeGreaterThan(0);
        expect($create['json']['requestPath'])->toBe($requestPath);
        expect($create['json']['targetPath'])->toBe('cms/index/index');
        expect($create['json']['options'])->toBe('RP');
        expect($create['json']['isSystem'])->toBeFalse();
        expect($create['json']['storeId'])->toBe(1);

        try {
            $read = apiGet("/api/rest/v2/url-rewrites/{$id}", $writeToken);
            expect($read['status'])->toBe(200);
            expect($read['json']['description'])->toBe('Pest custom rewrite');

            $byPath = apiGet('/api/rest/v2/url-rewrites?requestPath=' . rawurlencode($requestPath), adminToken());
            expect($byPath['status'])->toBe(200);
            expect(array_column(getItems($byPath), 'id'))->toBe([$id]);

            $search = apiGet('/api/rest/v2/url-rewrites?search=' . rawurlencode("pest-rewrite-{$suffix}") . '&isSystem=false&storeId=1', adminToken());
            expect($search['status'])->toBe(200);
            expect(array_column(getItems($search), 'id'))->toContain($id);

            $duplicate = apiPost('/api/rest/v2/url-rewrites', [
                'storeId' => 1,
                'requestPath' => $requestPath,
                'idPath' => "pest/{$suffix}-dup",
                'targetPath' => 'cms/index/index',
            ], $writeToken);
            expect($duplicate['status'])->toBe(400);
            expect($duplicate['json']['message'] ?? '')->toContain('already exists');

            $update = apiPut("/api/rest/v2/url-rewrites/{$id}", [
                'targetPath' => 'contacts',
                'options' => 'R',
            ], $writeToken);
            expect($update['status'])->toBe(200);
            expect($update['json']['targetPath'])->toBe('contacts');
            expect($update['json']['options'])->toBe('R');
            expect($update['json']['requestPath'])->toBe($requestPath);

            expect(apiDelete("/api/rest/v2/url-rewrites/{$id}", $writeToken)['status'])->toBeForbidden();

            $delete = apiDelete("/api/rest/v2/url-rewrites/{$id}", $deleteToken);
            expect($delete['status'])->toBeIn([200, 204]);
            expect(apiGet("/api/rest/v2/url-rewrites/{$id}", adminToken())['status'])->toBeNotFound();
        } finally {
            deleteUrlRewrite($id);
        }
    });

    it('generates the id path and target path for a product rewrite', function (): void {
        $token = adminToken();
        $product = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToFilter('type_id', 'simple')
            ->setPageSize(1)
            ->getFirstItem();
        if (!$product->getId()) {
            $this->markTestSkipped('No simple product in the test fixture');
        }
        $suffix = substr(uniqid(), -6);

        $create = apiPost('/api/rest/v2/url-rewrites', [
            'storeId' => 1,
            'requestPath' => "pest-product-{$suffix}.html",
            'productId' => (int) $product->getId(),
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $id = (int) $create['json']['id'];

        try {
            expect($create['json']['idPath'])->toBe('product/' . $product->getId());
            expect($create['json']['targetPath'])->toBe('catalog/product/view/id/' . $product->getId());
            expect($create['json']['productId'])->toBe((int) $product->getId());
        } finally {
            deleteUrlRewrite($id);
        }
    });

    it('rejects invalid input', function (): void {
        $token = adminToken();

        $noStore = apiPost('/api/rest/v2/url-rewrites', [
            'requestPath' => 'pest-nostore.html',
            'idPath' => 'pest/nostore',
            'targetPath' => 'cms/index/index',
        ], $token);
        expect($noStore['status'])->toBe(400);

        $noTarget = apiPost('/api/rest/v2/url-rewrites', [
            'storeId' => 1,
            'requestPath' => 'pest-notarget.html',
        ], $token);
        expect($noTarget['status'])->toBe(400);

        $badOptions = apiPost('/api/rest/v2/url-rewrites', [
            'storeId' => 1,
            'requestPath' => 'pest-badoptions.html',
            'idPath' => 'pest/badoptions',
            'targetPath' => 'cms/index/index',
            'options' => 'X',
        ], $token);
        expect($badOptions['status'])->toBe(400);

        $badPath = apiPost('/api/rest/v2/url-rewrites', [
            'storeId' => 1,
            'requestPath' => 'pest//double-slash.html',
            'idPath' => 'pest/badpath',
            'targetPath' => 'cms/index/index',
        ], $token);
        expect($badPath['status'])->toBe(400);

        $badProduct = apiPost('/api/rest/v2/url-rewrites', [
            'storeId' => 1,
            'requestPath' => 'pest-badproduct.html',
            'productId' => 999999999,
        ], $token);
        expect($badProduct['status'])->toBe(400);
    });

});

describe('System URL rewrites', function (): void {

    it('refuses to update or delete a system rewrite', function (): void {
        $systemId = systemUrlRewriteId();
        if ($systemId === null) {
            $this->markTestSkipped('No system URL rewrite in the test fixture');
        }
        $token = serviceToken(['url-rewrites/read', 'url-rewrites/write', 'url-rewrites/delete']);

        $read = apiGet("/api/rest/v2/url-rewrites/{$systemId}", $token);
        expect($read['status'])->toBe(200);
        expect($read['json']['isSystem'])->toBeTrue();

        $update = apiPut("/api/rest/v2/url-rewrites/{$systemId}", ['description' => 'nope'], $token);
        expect($update['status'])->toBe(400);
        expect($update['json']['message'] ?? '')->toContain('system URL rewrite');

        $delete = apiDelete("/api/rest/v2/url-rewrites/{$systemId}", $token);
        expect($delete['status'])->toBe(400);

        expect(apiGet("/api/rest/v2/url-rewrites/{$systemId}", $token)['status'])->toBe(200);
    });

    it('lists only system rewrites with isSystem=true', function (): void {
        $response = apiGet('/api/rest/v2/url-rewrites?isSystem=true&itemsPerPage=5', adminToken());

        expect($response['status'])->toBe(200);
        foreach (getItems($response) as $item) {
            expect($item['isSystem'])->toBeTrue();
        }
    });

});
