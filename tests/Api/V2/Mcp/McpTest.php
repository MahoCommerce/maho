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

    it('advertises the filters a list resource declares', function (): void {
        $token = adminToken();
        $tools = mcpTools($token, mcpSession($token));
        $properties = array_keys($tools['catalog_products_list']['inputSchema']['properties'] ?? []);

        // Pagination is boilerplate on every list tool; the rest is derived from the
        // resource's canonical GraphQL collection query, the one machine-readable
        // declaration of what its collection filters on.
        expect($properties)->toContain('page', 'itemsPerPage', 'search', 'sku', 'categoryId');
    });

    it('advertises every input schema as a JSON object on the wire', function (): void {
        // The spec types `inputSchema.properties` as an object, and a strict client
        // rejects the whole listing when one tool gets it wrong. An empty PHP array
        // encodes to `[]`, so tools taking no arguments (/store-config, /customers/me)
        // are the ones that break. Asserting on the encoded form is the point here:
        // decoding into PHP arrays hides exactly the difference that matters.
        $token = adminToken();
        $tools = mcpTools($token, mcpSession($token));

        foreach ($tools as $name => $tool) {
            $schema = $tool['inputSchema'] ?? null;
            expect($schema)->toBeArray("tool {$name} has no input schema");
            expect($schema['type'] ?? null)->toBe('object', "tool {$name} input schema is not an object");

            $encoded = json_encode($schema, JSON_THROW_ON_ERROR);
            expect($encoded)->not->toContain('"properties":[', "tool {$name} encodes properties as an array");
            foreach (['properties', 'required'] as $key) {
                if (!array_key_exists($key, $schema)) {
                    continue;
                }
                expect($schema[$key])->not->toBe([], "tool {$name} declares an empty {$key}");
            }
        }
    });

    it('covers resources declared with the plain API Platform attribute', function (): void {
        // Product sub-resources gate on the parent's products/write rather than owning a
        // permission, so they use ApiPlatform's attribute instead of Maho's. That says
        // nothing about whether an agent should reach them.
        $token = adminToken();
        $tools = mcpTools($token, mcpSession($token));

        expect(array_keys($tools))->toContain(
            'catalog_products_tier_prices_list',
            'catalog_products_links_related_list',
            'catalog_products_media_list',
            'core_store_config_get',
        );
    });

    it('omits operations that opt out and resources that declare no tools', function (): void {
        $token = adminToken();
        $names = array_keys(mcpTools($token, mcpSession($token)));

        // AuthToken and ContactForm declare `mcp: []`; the custom-option download opts
        // out per-operation because it returns a raw file. Nothing derives a tool from
        // API Platform's injected NotExposed operation either.
        foreach ($names as $name) {
            expect($name)->not->toStartWith('api_auth_');
            expect($name)->not->toContain('contact');
            expect($name)->not->toContain('custom_option_file');
        }
        expect($names)->not->toContain('catalog_stocks_get');
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

    it('applies a declared filter to a list tool', function (): void {
        $session = mcpSession();
        $all = mcpTool('catalog_products_list', [], null, $session);
        $total = json_decode($all['json']['result']['content'][0]['text'] ?? '[]', true)['totalItems'] ?? 0;

        if ($total < 2) {
            $this->markTestSkipped('Needs at least two products to prove filtering narrows the result');
        }

        // `sku` is read from $context['args'], not $context['filters'], so this also
        // covers the tool arguments reaching both keys.
        $sku = json_decode($all['json']['result']['content'][0]['text'] ?? '[]', true)['member'][0]['sku'];
        $filtered = mcpTool('catalog_products_list', ['sku' => $sku], null, $session);
        $payload = json_decode($filtered['json']['result']['content'][0]['text'] ?? '[]', true);

        expect($payload['totalItems'] ?? null)->toBe(1);
        expect($payload['member'][0]['sku'] ?? null)->toBe($sku);
    });

    it('gives the operation its own URI so path parameters resolve', function (): void {
        // ProductLinkProvider reads which of related/cross-sell/up-sell was meant off
        // the request path, since a plain path parameter never reaches uriVariables.
        // Under MCP every request is a POST to /api/mcp, so the tool call is dispatched
        // against a request carrying the operation's URI instead.
        $token = adminToken();
        $session = mcpSession($token);
        $response = mcpTool('catalog_products_links_related_list', ['productId' => 1], $token, $session);

        expect($response['json']['error']['message'] ?? '')->not->toContain('Invalid link type');
    });

    it('keeps a read tool out of the write stage', function (): void {
        // Twenty resources declare `processor:` at resource level, so their reads
        // inherit one. Leaving API Platform's write stage enabled (what a hand-declared
        // tool needs, since its processor is the tool body) runs that processor over the
        // provider's result: CmsPage's 403s on cms-pages/write, and Order's replaces the
        // collection with a single empty Order.
        $token = adminToken();
        $session = mcpSession($token);

        foreach (['content_cms_pages_list', 'sales_orders_list'] as $name) {
            $response = mcpTool($name, [], $token, $session);
            expect($response['json']['error'] ?? null)->toBeNull("{$name} was refused");

            $payload = json_decode($response['json']['result']['content'][0]['text'] ?? '{}', true);
            expect($payload['@type'] ?? null)->toBe('Collection', "{$name} did not return a collection");
        }
    });

    it('still runs the write stage for a write tool', function (): void {
        $token = serviceToken();
        $session = mcpSession($token);

        $created = mcpTool('content_cms_blocks_create', [
            'title' => 'MCP write stage',
            'identifier' => 'mcp_write_stage_' . bin2hex(random_bytes(4)),
            'content' => 'body',
            'isActive' => true,
            'stores' => [0],
        ], $token, $session);

        $payload = json_decode($created['json']['result']['content'][0]['text'] ?? '{}', true);
        $id = $payload['id'] ?? null;
        expect($id)->toBeInt();
        trackCreated('cms_block', $id);

        $deleted = mcpTool('content_cms_blocks_delete', ['id' => $id], $token, $session);
        expect($deleted['json']['error'] ?? null)->toBeNull();
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
