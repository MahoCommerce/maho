<?php

declare(strict_types=1);

/**
 * API v2 CMS Block CRUD Tests
 *
 * End-to-end tests for CMS block create, read, update, delete via REST,
 * plus GraphQL read verification. Tests permission enforcement at each step.
 *
 * @group write
 */

afterAll(function () {
    cleanupTestData();
});

describe('CMS Block Permission Enforcement (REST)', function () {

    it('denies create without authentication', function () {
        $response = apiPost('/api/cms-blocks', [
            'identifier' => 'test-block-noauth',
            'title' => 'Test Block No Auth',
            'content' => '<p>Should fail</p>',
        ]);

        expect($response['status'])->toBe(401);
    });

    it('denies create with customer token (wrong role)', function () {
        $response = apiPost('/api/cms-blocks', [
            'identifier' => 'test-block-customer',
            'title' => 'Test Block Customer',
            'content' => '<p>Should fail</p>',
        ], customerToken());

        expect($response['status'])->toBeForbidden();
    });

    it('denies create without correct permission', function () {
        $token = serviceToken(['cms-pages/write']);
        $response = apiPost('/api/cms-blocks', [
            'identifier' => 'test-block-noperm',
            'title' => 'Test Block No Permission',
            'content' => '<p>Should fail</p>',
        ], $token);

        expect($response['status'])->toBeForbidden();
    });

});

describe('CMS Block CRUD Lifecycle (REST)', function () {

    it('creates → reads → updates → verifies → delete-denied → deletes → confirms gone', function () {
        $writeToken = serviceToken(['cms-blocks/write']);
        $deleteToken = serviceToken(['cms-blocks/delete']);

        // 1. Create
        $create = apiPost('/api/cms-blocks', [
            'identifier' => 'test-pest-crud-block',
            'title' => 'Test CRUD Block',
            'content' => '<p>Created by Pest test suite</p>',
            'isActive' => true,
            'stores' => ['all'],
        ], $writeToken);

        expect($create['status'])->toBeIn([200, 201]);
        expect($create['json'])->toHaveKey('id');
        expect($create['json']['identifier'])->toBe('test-pest-crud-block');
        expect($create['json']['title'])->toBe('Test CRUD Block');

        $blockId = $create['json']['id'];
        trackCreated('cms_block', $blockId);

        // 2. Read (public, no auth)
        $read = apiGet("/api/cms-blocks/{$blockId}");
        expect($read['status'])->toBe(200);
        expect($read['json']['identifier'])->toBe('test-pest-crud-block');

        // 3. Update
        $update = apiPut("/api/cms-blocks/{$blockId}", [
            'title' => 'Test CRUD Block Updated',
            'content' => '<p>Updated by Pest test suite</p>',
        ], $writeToken);
        expect($update['status'])->toBe(200);
        expect($update['json']['title'])->toBe('Test CRUD Block Updated');

        // 4. Verify update persisted
        $verify = apiGet("/api/cms-blocks/{$blockId}");
        expect($verify['status'])->toBe(200);
        expect($verify['json']['title'])->toBe('Test CRUD Block Updated');
        expect($verify['json']['content'])->toContain('Updated by Pest');

        // 5. Deny delete with only write permission
        $denyDelete = apiDelete("/api/cms-blocks/{$blockId}", $writeToken);
        expect($denyDelete['status'])->toBeForbidden();

        // 6. Delete with correct permission
        $delete = apiDelete("/api/cms-blocks/{$blockId}", $deleteToken);
        expect($delete['status'])->toBeIn([200, 204]);

        // 7. Confirm gone
        $gone = apiGet("/api/cms-blocks/{$blockId}");
        expect($gone['status'])->toBeNotFound();
    });

});

describe('CMS Block CRUD with "all" permission', function () {

    it('full lifecycle with "all" permission', function () {
        $token = serviceToken(['all']);

        // Create
        $create = apiPost('/api/cms-blocks', [
            'identifier' => 'test-pest-all-perm-block',
            'title' => 'All Permission Block',
            'content' => '<p>All permission test</p>',
            'isActive' => true,
            'stores' => ['all'],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $blockId = $create['json']['id'];
        trackCreated('cms_block', $blockId);

        // Read (public)
        $read = apiGet("/api/cms-blocks/{$blockId}");
        expect($read['status'])->toBe(200);

        // Update
        $update = apiPut("/api/cms-blocks/{$blockId}", [
            'title' => 'All Permission Block Updated',
        ], $token);
        expect($update['status'])->toBe(200);

        // Delete
        $delete = apiDelete("/api/cms-blocks/{$blockId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);

        // Confirm gone
        $gone = apiGet("/api/cms-blocks/{$blockId}");
        expect($gone['status'])->toBeNotFound();
    });

});

describe('CMS Block via GraphQL (read)', function () {

    it('reads blocks collection via GraphQL', function () {
        $query = <<<'GRAPHQL'
        {
            cmsBlocksCmsBlocks {
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
        expect($response['json']['data'])->toHaveKey('cmsBlocksCmsBlocks');
    });

    it('creates block via REST then reads by identifier via GraphQL', function () {
        $token = serviceToken(['cms-blocks/write', 'cms-blocks/delete']);

        // Create via REST
        $create = apiPost('/api/cms-blocks', [
            'identifier' => 'test-pest-gql-verify-block',
            'title' => 'GraphQL Verify Block',
            'content' => '<p>Verify via GraphQL</p>',
            'isActive' => true,
            'stores' => ['all'],
        ], $token);

        expect($create['status'])->toBeIn([200, 201]);
        $blockId = $create['json']['id'];
        trackCreated('cms_block', $blockId);

        // Read by identifier via GraphQL
        $query = <<<'GRAPHQL'
        {
            cmsBlockByIdentifierCmsBlock(identifier: "test-pest-gql-verify-block") {
                _id
                identifier
                title
                content
            }
        }
        GRAPHQL;

        $response = gqlQuery($query);

        expect($response['status'])->toBe(200);
        expect($response['json']['data']['cmsBlockByIdentifierCmsBlock'])->not->toBeNull();
        expect($response['json']['data']['cmsBlockByIdentifierCmsBlock']['identifier'])->toBe('test-pest-gql-verify-block');

        // Cleanup via REST
        $delete = apiDelete("/api/cms-blocks/{$blockId}", $token);
        expect($delete['status'])->toBeIn([200, 204]);
    });

});
