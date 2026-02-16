<?php

declare(strict_types=1);

/**
 * API v2 CMS Page CRUD Tests
 *
 * End-to-end tests for CMS page create, read, update, delete via REST,
 * plus GraphQL read verification. Tests permission enforcement at each step.
 *
 * @group write
 */

afterAll(function () {
    cleanupTestData();
});

describe('CMS Page Permission Enforcement (REST)', function () {

    it('denies create without authentication', function () {
        $response = apiPost('/api/cms-pages', [
            'identifier' => 'test-page-noauth',
            'title' => 'Test Page No Auth',
            'content' => '<p>Should fail</p>',
        ]);

        expect($response['status'])->toBe(401);
        expect($response['json']['error'])->toBe('unauthorized');
    });

    it('denies create with customer token (wrong role)', function () {
        $response = apiPost('/api/cms-pages', [
            'identifier' => 'test-page-customer',
            'title' => 'Test Page Customer',
            'content' => '<p>Should fail</p>',
        ], customerToken());

        expect($response['status'])->toBeForbidden();
    });

    it('denies create without correct permission', function () {
        $token = serviceToken(['blog-posts/write']);
        $response = apiPost('/api/cms-pages', [
            'identifier' => 'test-page-noperm',
            'title' => 'Test Page No Permission',
            'content' => '<p>Should fail</p>',
        ], $token);

        expect($response['status'])->toBeForbidden();
    });

});

describe('CMS Page CRUD Lifecycle (REST)', function () {

    it('creates → reads → updates → verifies → delete-denied → deletes → confirms gone', function () {
        $writeToken = serviceToken(['cms-pages/write']);
        $deleteToken = serviceToken(['cms-pages/delete']);

        // 1. Create
        $create = apiPost('/api/cms-pages', [
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
        $read = apiGet("/api/cms-pages/{$pageId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['identifier'])->toBe('test-pest-crud-page');

        // 3. Update
        $update = apiPut("/api/cms-pages/{$pageId}", [
            'title' => 'Test CRUD Page Updated',
            'content' => '<p>Updated by Pest test suite</p>',
        ], $writeToken);
        expect($update['status'])->toBe(200);
        expect($update['json']['title'])->toBe('Test CRUD Page Updated');

        // 4. Verify update persisted
        $verify = apiGet("/api/cms-pages/{$pageId}");
        expect($verify['status'])->toBe(200);
        expect($verify['json']['title'])->toBe('Test CRUD Page Updated');
        expect($verify['json']['content'])->toContain('Updated by Pest');

        // 5. Deny delete with only write permission
        $denyDelete = apiDelete("/api/cms-pages/{$pageId}", $writeToken);
        expect($denyDelete['status'])->toBeForbidden();

        // 6. Delete with correct permission
        $delete = apiDelete("/api/cms-pages/{$pageId}", $deleteToken);
        expect($delete['status'])->toBeIn([200, 204]);

        // 7. Confirm gone
        $gone = apiGet("/api/cms-pages/{$pageId}");
        expect($gone['status'])->toBeNotFound();
    });

});

describe('CMS Page CRUD with "all" permission', function () {

    it('full lifecycle with "all" permission', function () {
        $token = serviceToken(['all']);

        // Create
        $create = apiPost('/api/cms-pages', [
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
        $read = apiGet("/api/cms-pages/{$pageId}");
        expect($read['status'])->toBe(200);

        // Update
        $update = apiPut("/api/cms-pages/{$pageId}", [
            'title' => 'All Permission Page Updated',
        ], $token);
        expect($update['status'])->toBe(200);

        // Delete
        $delete = apiDelete("/api/cms-pages/{$pageId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);

        // Confirm gone
        $gone = apiGet("/api/cms-pages/{$pageId}");
        expect($gone['status'])->toBeNotFound();
    });

});

describe('CMS Page via GraphQL (read)', function () {

    it('reads pages collection via GraphQL', function () {
        $query = <<<'GRAPHQL'
        {
            cmsPagesCmsPages {
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
        expect($response['json']['data'])->toHaveKey('cmsPagesCmsPages');

        $edges = $response['json']['data']['cmsPagesCmsPages']['edges'] ?? [];
        expect($edges)->not->toBeEmpty();
    });

    it('creates page via REST then reads via GraphQL', function () {
        $token = serviceToken(['cms-pages/write', 'cms-pages/delete']);

        // Create via REST
        $create = apiPost('/api/cms-pages', [
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
        $iri = "/api/cms-pages/{$pageId}";
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
        $delete = apiDelete("/api/cms-pages/{$pageId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);
    });

});
