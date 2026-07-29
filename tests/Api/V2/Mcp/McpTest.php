<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ApiV2Helper;

/**
 * MCP endpoint tests.
 *
 * The tool catalogue is derived from the same ApiResource metadata that drives
 * REST, so these tests focus on what only MCP can get wrong: the handshake, the
 * derived tool names, and the fact that dispatching a tool goes through the same
 * authentication, admin ACL and per-operation permission gates a REST request
 * would hit. Everything below that is the REST pipeline, covered elsewhere.
 *
 * @group write
 */

afterAll(function (): void {
    cleanupTestData();
});

describe('MCP handshake', function (): void {

    it('answers initialize with server info and instructions', function (): void {
        $response = mcpCall([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => ApiV2Helper::MCP_PROTOCOL_VERSION,
                'capabilities' => [],
                'clientInfo' => ['name' => 'maho-pest', 'version' => '1.0'],
            ],
        ]);

        expect($response['status'])->toBe(200);

        $result = $response['json']['result'] ?? [];
        expect($result['serverInfo']['name'] ?? null)->toBe('maho');
        expect($result['serverInfo']['version'] ?? null)->toBe(Mage::getVersion());
        expect($result['capabilities']['tools'] ?? null)->toBeArray();
        expect($result['instructions'] ?? '')->toContain('Maho Commerce');
    });

    it('issues a session id later messages can reuse', function (): void {
        expect(mcpSession())->toBeString();
    });
});

describe('MCP tool catalogue', function (): void {

    it('derives stable tool names from the resource surface', function (): void {
        $token = adminToken();
        $tools = mcpTools($token, mcpSession($token));

        expect(array_keys($tools))->toContain(
            'catalog_products_list',
            'catalog_products_get',
            'catalog_categories_list',
            'sales_orders_list',
            'customers_customers_list',
        );
    });

    it('marks read tools read-only and deletes destructive', function (): void {
        $token = adminToken();
        $tools = mcpTools($token, mcpSession($token));

        expect($tools['catalog_products_list']['annotations']['readOnlyHint'] ?? null)->toBeTrue();
        expect($tools['catalog_products_delete']['annotations']['destructiveHint'] ?? null)->toBeTrue();
    });

    it('advertises only the identifier as input for an item read', function (): void {
        $token = adminToken();
        $tools = mcpTools($token, mcpSession($token));
        $schema = $tools['catalog_products_get']['inputSchema'] ?? [];

        expect($schema['type'] ?? null)->toBe('object');
        expect(array_keys($schema['properties'] ?? []))->toBe(['id']);
        expect($schema['required'] ?? [])->toBe(['id']);
    });

    it('hides tools the caller cannot call', function (): void {
        $anonymous = mcpTools(null, mcpSession());

        // /products is public, so an unauthenticated caller keeps the read tools
        // and loses everything that needs a grant.
        expect(array_keys($anonymous))->toContain('catalog_products_list');
        expect(array_keys($anonymous))->not->toContain('catalog_products_delete');
        expect(array_keys($anonymous))->not->toContain('sales_orders_list');
    });
});

describe('MCP tool dispatch', function (): void {

    it('returns store data from a read tool', function (): void {
        $session = mcpSession();
        $response = mcpTool('catalog_products_list', [], null, $session);

        expect($response['status'])->toBe(200);

        $result = $response['json']['result'] ?? [];
        expect($result['isError'] ?? false)->toBeFalse();

        $payload = json_decode($result['content'][0]['text'] ?? '[]', true);
        expect($payload['totalItems'] ?? null)->toBeInt();
        expect($payload['member'] ?? null)->toBeArray();
    });

    it('passes list arguments through as pagination', function (): void {
        $session = mcpSession();
        $response = mcpTool('catalog_products_list', ['itemsPerPage' => 2], null, $session);

        $payload = json_decode($response['json']['result']['content'][0]['text'] ?? '[]', true);
        expect($payload['member'] ?? [])->toHaveCount(2);
    });

    it('refuses an unauthenticated call to a non-public tool', function (): void {
        $session = mcpSession();
        $response = mcpTool('catalog_products_delete', ['id' => 1], null, $session);

        expect($response['json']['error']['message'] ?? '')->toContain('Authentication required');
    });

    it('refuses a write tool the token has no permission for', function (): void {
        // Granted cms-pages only; the catalog write must be denied by the same
        // ApiUserVoter expression that gates POST /api/rest/v2/products.
        $token = serviceToken(['cms_pages/all']);
        $session = mcpSession($token);
        $response = mcpTool('catalog_products_create', ['sku' => 'mcp-denied', 'name' => 'Denied'], $token, $session);

        expect($response['json']['result'] ?? null)->toBeNull();
        expect($response['json']['error'] ?? null)->not->toBeNull();
    });

    it('applies admin ACL to admin tokens', function (): void {
        // A role granting catalog only must not reach a sales tool: the request
        // attributes AdminAclListener keys off are absent under MCP, so this
        // proves McpDispatchProvider is doing the gating instead.
        $token = adminTokenWithAcl(['catalog/products'], 'pest_mcp_acl_deny');
        $session = mcpSession($token);
        $response = mcpTool('sales_orders_list', [], $token, $session);

        expect($response['json']['error']['message'] ?? '')->toContain('does not grant access');
    });
});

describe('MCP protocol toggle', function (): void {

    it('404s the endpoint when the protocol is disabled', function (): void {
        $config = Mage::getModel('core/config');
        $config->saveConfig('apiplatform/protocols/mcp', '0', 'default', 0);
        Mage::app()->getCache()->cleanType('config');

        try {
            $response = mcpCall(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []]);
            expect($response['status'])->toBe(404);
        } finally {
            $config->saveConfig('apiplatform/protocols/mcp', '1', 'default', 0);
            Mage::app()->getCache()->cleanType('config');
        }
    });
});
