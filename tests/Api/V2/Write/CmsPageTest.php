<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 CMS Page CRUD Tests
 *
 * End-to-end tests for CMS page create, read, update, delete via REST,
 * plus GraphQL read verification. Tests permission enforcement at each step.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('CMS Page Permission Enforcement (REST)', function (): void {

    it('denies create without authentication', function (): void {
        $response = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-page-noauth',
            'title' => 'Test Page No Auth',
            'content' => '<p>Should fail</p>',
        ]);

        expect($response['status'])->toBe(401);
        expect($response['json']['error'])->toBe('unauthorized');
    });

    it('denies create with customer token (wrong role)', function (): void {
        $response = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-page-customer',
            'title' => 'Test Page Customer',
            'content' => '<p>Should fail</p>',
        ], customerToken());

        expect($response['status'])->toBeForbidden();
    });

    it('denies create without correct permission', function (): void {
        $token = serviceToken(['blog-posts/write']);
        $response = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-page-noperm',
            'title' => 'Test Page No Permission',
            'content' => '<p>Should fail</p>',
        ], $token);

        expect($response['status'])->toBeForbidden();
    });

});

describe('CMS Page CRUD Lifecycle (REST)', function (): void {

    it('creates → reads → updates → verifies → delete-denied → deletes → confirms gone', function (): void {
        $writeToken = serviceToken(['cms-pages/write']);
        $deleteToken = serviceToken(['cms-pages/delete']);

        // 1. Create
        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-pest-crud-page',
            'title' => 'Test CRUD Page',
            'content' => '<p>Created by Pest test suite</p>',
            'isActive' => true,
            'stores' => ['all'],
        ], $writeToken);

        expect($create['status'])->toBeIn([200, 201]);
        expect($create['json'])->toHaveKey('id');
        expect($create['json']['identifier'])->toBe('test-pest-crud-page');
        expect($create['json']['title'])->toBe('Test CRUD Page');

        $pageId = $create['json']['id'];
        trackCreated('cms_page', $pageId);

        // 2. Read (public, no auth)
        $read = apiGet("/api/rest/v2/cms-pages/{$pageId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['identifier'])->toBe('test-pest-crud-page');

        // 3. Update
        $update = apiPut("/api/rest/v2/cms-pages/{$pageId}", [
            'title' => 'Test CRUD Page Updated',
            'content' => '<p>Updated by Pest test suite</p>',
        ], $writeToken);
        expect($update['status'])->toBe(200);
        expect($update['json']['title'])->toBe('Test CRUD Page Updated');

        // 4. Verify update persisted
        $verify = apiGet("/api/rest/v2/cms-pages/{$pageId}");
        expect($verify['status'])->toBe(200);
        expect($verify['json']['title'])->toBe('Test CRUD Page Updated');
        expect($verify['json']['content'])->toContain('Updated by Pest');

        // 5. Deny delete with only write permission
        $denyDelete = apiDelete("/api/rest/v2/cms-pages/{$pageId}", $writeToken);
        expect($denyDelete['status'])->toBeForbidden();

        // 6. Delete with correct permission
        $delete = apiDelete("/api/rest/v2/cms-pages/{$pageId}", $deleteToken);
        expect($delete['status'])->toBeIn([200, 204]);

        // 7. Confirm gone
        $gone = apiGet("/api/rest/v2/cms-pages/{$pageId}");
        expect($gone['status'])->toBeNotFound();
    });

    it('leaves omitted fields untouched on a partial update', function (): void {
        $token = serviceToken(['cms-pages/write', 'cms-pages/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => "test-pest-partial-{$suffix}",
            'title' => 'Partial Update Page',
            'content' => '<p>Original content</p>',
            'contentHeading' => 'Original heading',
            'isActive' => true,
            'stores' => ['all'],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $pageId = $create['json']['id'];
        trackCreated('cms_page', $pageId);

        // Only sortOrder. Blanking the omitted identifier would fail the model's URL
        // key validation, so a regression here surfaces as a 422 rather than bad data.
        $update = apiPut("/api/rest/v2/cms-pages/{$pageId}", ['sortOrder' => 3], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['identifier'])->toBe("test-pest-partial-{$suffix}");
        expect($update['json']['title'])->toBe('Partial Update Page');
        expect($update['json']['sortOrder'])->toBe(3);

        $verify = apiGet("/api/rest/v2/cms-pages/{$pageId}");
        expect($verify['status'])->toBe(200);
        expect($verify['json']['identifier'])->toBe("test-pest-partial-{$suffix}");
        expect($verify['json']['title'])->toBe('Partial Update Page');
        expect($verify['json']['contentHeading'])->toBe('Original heading');
        expect($verify['json']['content'])->toContain('Original content');

        expect(apiDelete("/api/rest/v2/cms-pages/{$pageId}", $token)['status'])->toBeIn([200, 204]);
    });

});

describe('CMS Page CRUD with "all" permission', function (): void {

    it('full lifecycle with "all" permission', function (): void {
        $token = serviceToken(['all']);

        // Create
        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-pest-all-perm-page',
            'title' => 'All Permission Page',
            'content' => '<p>All permission test</p>',
            'isActive' => true,
            'stores' => ['all'],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $pageId = $create['json']['id'];
        trackCreated('cms_page', $pageId);

        // Read (public)
        $read = apiGet("/api/rest/v2/cms-pages/{$pageId}");
        expect($read['status'])->toBe(200);

        // Update
        $update = apiPut("/api/rest/v2/cms-pages/{$pageId}", [
            'title' => 'All Permission Page Updated',
        ], $token);
        expect($update['status'])->toBe(200);

        // Delete
        $delete = apiDelete("/api/rest/v2/cms-pages/{$pageId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);

        // Confirm gone
        $gone = apiGet("/api/rest/v2/cms-pages/{$pageId}");
        expect($gone['status'])->toBeNotFound();
    });

});

describe('CMS Page layout and meta fields (REST)', function (): void {

    it('round-trips design and meta fields, and clears custom theme dates with an empty string', function (): void {
        $token = serviceToken(['cms-pages/write', 'cms-pages/delete']);

        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-pest-layout-page',
            'title' => 'Layout Fields Page',
            'content' => '<p>Layout fields</p>',
            'isActive' => true,
            'stores' => ['all'],
            'sortOrder' => 7,
            'layoutUpdateXml' => '<reference name="content"/>',
            'customTheme' => 'default/custom',
            'customRootTemplate' => 'two_columns_left',
            'customLayoutUpdateXml' => '<reference name="root"/>',
            'customThemeFrom' => '2026-01-01',
            'customThemeTo' => '2026-12-31',
            'metaRobots' => 'NOINDEX,FOLLOW',
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $pageId = $create['json']['id'];
        trackCreated('cms_page', $pageId);

        // Design fields round-trip on the write response, which is permission-gated.
        expect($create['json']['layoutUpdateXml'])->toBe('<reference name="content"/>');
        expect($create['json']['customTheme'])->toBe('default/custom');
        expect($create['json']['customRootTemplate'])->toBe('two_columns_left');
        expect($create['json']['customLayoutUpdateXml'])->toBe('<reference name="root"/>');
        expect($create['json']['customThemeFrom'])->toBe('2026-01-01');
        expect($create['json']['customThemeTo'])->toBe('2026-12-31');

        $read = apiGet("/api/rest/v2/cms-pages/{$pageId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['sortOrder'])->toBe(7);
        expect($read['json']['metaRobots'])->toBe('NOINDEX,FOLLOW');
        // Design and layout internals stay out of the public read payload.
        expect($read['json']['layoutUpdateXml'] ?? null)->toBeNull();
        expect($read['json']['customLayoutUpdateXml'] ?? null)->toBeNull();
        expect($read['json']['customTheme'] ?? null)->toBeNull();
        expect($read['json']['customRootTemplate'] ?? null)->toBeNull();
        expect($read['json']['customThemeFrom'] ?? null)->toBeNull();
        expect($read['json']['customThemeTo'] ?? null)->toBeNull();

        // An authenticated back-office caller reading the same page does see them.
        foreach ([adminToken(), $token] as $privileged) {
            $privilegedRead = apiGet("/api/rest/v2/cms-pages/{$pageId}", $privileged);
            expect($privilegedRead['status'])->toBe(200);
            expect($privilegedRead['json']['layoutUpdateXml'])->toBe('<reference name="content"/>');
            expect($privilegedRead['json']['customLayoutUpdateXml'])->toBe('<reference name="root"/>');
            expect($privilegedRead['json']['customTheme'])->toBe('default/custom');
            expect($privilegedRead['json']['customRootTemplate'])->toBe('two_columns_left');
            expect($privilegedRead['json']['customThemeFrom'])->toBe('2026-01-01');
            expect($privilegedRead['json']['customThemeTo'])->toBe('2026-12-31');
        }

        // The collection path is gated the same way: a leak there is just as bad.
        $publicList = apiGet('/api/rest/v2/cms-pages?identifier=test-pest-layout-page');
        expect($publicList['status'])->toBe(200);
        $publicMember = ($publicList['json']['member'] ?? $publicList['json']['hydra:member'] ?? [])[0] ?? null;
        expect($publicMember)->not->toBeNull();
        expect($publicMember['layoutUpdateXml'] ?? null)->toBeNull();
        expect($publicMember['customLayoutUpdateXml'] ?? null)->toBeNull();
        expect($publicMember['customTheme'] ?? null)->toBeNull();

        $adminList = apiGet('/api/rest/v2/cms-pages?identifier=test-pest-layout-page', adminToken());
        expect($adminList['status'])->toBe(200);
        $adminMember = ($adminList['json']['member'] ?? $adminList['json']['hydra:member'] ?? [])[0] ?? null;
        expect($adminMember)->not->toBeNull();
        expect($adminMember['layoutUpdateXml'])->toBe('<reference name="content"/>');
        expect($adminMember['customLayoutUpdateXml'])->toBe('<reference name="root"/>');
        expect($adminMember['customTheme'])->toBe('default/custom');

        $update = apiPut("/api/rest/v2/cms-pages/{$pageId}", [
            'customThemeFrom' => '',
            'customThemeTo' => '',
        ], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['customThemeFrom'] ?? null)->toBeNull();
        expect($update['json']['customThemeTo'] ?? null)->toBeNull();
        // Untouched fields survive the partial update.
        expect($update['json']['identifier'])->toBe('test-pest-layout-page');
        expect($update['json']['title'])->toBe('Layout Fields Page');
        expect($update['json']['metaRobots'])->toBe('NOINDEX,FOLLOW');
        expect($update['json']['sortOrder'])->toBe(7);
        expect($update['json']['layoutUpdateXml'])->toBe('<reference name="content"/>');

        $verify = apiGet("/api/rest/v2/cms-pages/{$pageId}");
        expect($verify['status'])->toBe(200);
        expect($verify['json']['identifier'])->toBe('test-pest-layout-page');
        expect($verify['json']['title'])->toBe('Layout Fields Page');

        expect(apiDelete("/api/rest/v2/cms-pages/{$pageId}", $token)['status'])->toBeIn([200, 204]);
    });

    it('rejects an invalid metaRobots value', function (): void {
        $token = serviceToken(['cms-pages/write']);

        $response = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-pest-bad-robots',
            'title' => 'Bad Robots',
            'metaRobots' => 'PLEASE,CRAWL',
        ], $token);

        expect($response['status'])->toBeGreaterThanOrEqual(400);
        expect($response['status'])->toBeLessThan(500);
    });

    it('keeps updating a page whose stored metaRobots is out of the allowed set', function (): void {
        $token = serviceToken(['cms-pages/write', 'cms-pages/delete']);
        $suffix = substr(uniqid(), -8);

        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => "test-pest-legacy-robots-{$suffix}",
            'title' => 'Legacy Robots Page',
            'isActive' => true,
            'stores' => ['all'],
        ], $token);
        expect($create['status'])->toBeIn([200, 201]);
        $pageId = (int) $create['json']['id'];
        trackCreated('cms_page', $pageId);

        // Simulate an imported/legacy value the API enum does not know about.
        Tests\Helpers\ApiV2Helper::ensureMahoBootstrapped();
        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->update(
            $resource->getTableName('cms/page'),
            ['meta_robots' => 'ARCHIVE'],
            ['page_id = ?' => $pageId],
        );

        $update = apiPut("/api/rest/v2/cms-pages/{$pageId}", [
            'title' => 'Legacy Robots Page Updated',
        ], $token);
        expect($update['status'])->toBe(200);
        expect($update['json']['title'])->toBe('Legacy Robots Page Updated');

        expect(apiDelete("/api/rest/v2/cms-pages/{$pageId}", $token)['status'])->toBeIn([200, 204]);
    });

    it('rejects layout XML that injects a template path or is malformed', function (): void {
        $token = serviceToken(['cms-pages/write']);

        $injection = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-pest-layout-injection',
            'title' => 'Layout Injection',
            'layoutUpdateXml' => '<reference name="content">'
                . '<block type="core/template" template="../../../../app/etc/local.xml"/>'
                . '</reference>',
        ], $token);
        expect($injection['status'])->toBeIn([400, 422]);

        $malformed = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-pest-layout-malformed',
            'title' => 'Layout Malformed',
            'customLayoutUpdateXml' => '<reference name="content"><block type="core/template">',
        ], $token);
        expect($malformed['status'])->toBeIn([400, 422]);
    });

});

describe('CMS Page via GraphQL (read)', function (): void {

    it('reads pages collection via GraphQL', function (): void {
        $query = <<<'GRAPHQL'
        {
            cmsPages {
                edges {
                    node {
                        id
                        _id
                        identifier
                        title
                        status
                    }
                }
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);

        expect($response['status'])->toBe(200);
        expect($response['json'])->toHaveKey('data');
        expect($response['json']['data'])->toHaveKey('cmsPages');

        $edges = $response['json']['data']['cmsPages']['edges'] ?? [];
        expect($edges)->not->toBeEmpty();
    });

    it('creates page via REST then reads via GraphQL', function (): void {
        $token = serviceToken(['cms-pages/write', 'cms-pages/delete']);

        // Create via REST
        $create = apiPost('/api/rest/v2/cms-pages', [
            'identifier' => 'test-pest-gql-verify-page',
            'title' => 'GraphQL Verify Page',
            'content' => '<p>Verify via GraphQL</p>',
            'isActive' => true,
            'stores' => ['all'],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $pageId = $create['json']['id'];
        trackCreated('cms_page', $pageId);

        // Read via GraphQL by IRI
        $iri = "/api/rest/v2/cms-pages/{$pageId}";
        $query = <<<GRAPHQL
        {
            cmsPage(id: "{$iri}") {
                _id
                identifier
                title
                content
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);

        expect($response['status'])->toBe(200);
        expect($response['json']['data']['cmsPage'])->not->toBeNull();
        expect($response['json']['data']['cmsPage']['identifier'])->toBe('test-pest-gql-verify-page');
        expect($response['json']['data']['cmsPage']['title'])->toBe('GraphQL Verify Page');

        // Cleanup via REST
        $delete = apiDelete("/api/rest/v2/cms-pages/{$pageId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);
    });

});
