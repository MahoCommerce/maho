<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * The 401 that starts the OAuth flow.
 *
 * The MCP server answers from its own loop at HTTP 200, so a refusal reads as an
 * ordinary tool error and a client has nothing to act on. The authorization
 * specification is explicit that 401 plus WWW-Authenticate is what tells a client
 * it may authenticate, and it is the only signal that starts discovery,
 * registration and the browser consent flow.
 */

beforeEach(function (): void {
    if (!mcpPackagesInstalled()) {
        $this->markTestSkipped('MCP packages are not installed.');
    }
});

function setMcpAuth(bool $serverEnabled, bool $requireAuth): void
{
    $config = Mage::getModel('core/config');
    $config->saveConfig(Maho_ApiPlatform_Helper_Data::XML_PATH_AUTHORIZATION_ENABLED, $serverEnabled ? '1' : '0', 'default', 0);
    $config->saveConfig(Maho_ApiPlatform_Helper_Data::XML_PATH_MCP_REQUIRE_AUTH, $requireAuth ? '1' : '0', 'default', 0);
    Mage::app()->getCache()->cleanType('config');
}

function mcpInitialize(?string $token = null): array
{
    return mcpCall([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'maho-pest', 'version' => '1.0'],
        ],
    ], $token);
}

describe('MCP authentication challenge', function (): void {

    afterEach(fn() => setMcpAuth(false, false));

    it('serves the public catalog tools without a token', function (): void {
        setMcpAuth(true, false);

        // Anonymous catalog browsing is a real use for MCP, and it must keep
        // working once the authorization server is switched on.
        expect(mcpSession())->not->toBeNull();
    });

    it('challenges a tool call that needs a token', function (): void {
        setMcpAuth(true, false);
        $session = mcpSession();

        $response = mcpTool('customers_customers_list', [], null, $session);

        expect($response['status'])->toBe(401);
        expect(apiHeader($response, 'WWW-Authenticate'))->toContain('resource_metadata=');
    });

    it('points the challenge at the protected resource document', function (): void {
        setMcpAuth(true, false);
        $session = mcpSession();

        $challenge = apiHeader(mcpTool('customers_customers_list', [], null, $session), 'WWW-Authenticate');

        // The client follows this to the metadata, then to the authorization
        // server, then to registration. A wrong URL breaks the whole chain.
        // /api/mcp is a resource of its own, so the challenge names its document: the one for
        // the host root would send the client after a token whose audience is the wrong one.
        expect($challenge)->toContain('/.well-known/oauth-protected-resource/api/mcp');
    });

    it('leaves a public tool call alone', function (): void {
        setMcpAuth(true, false);
        $session = mcpSession();

        $response = mcpTool('catalog_categories_list', [], null, $session);

        expect($response['status'])->toBe(200);
        expect(apiHeader($response, 'WWW-Authenticate'))->toBeNull();
    });

    it('challenges the connection itself when the store asks for it', function (): void {
        // Some clients only attempt OAuth while connecting and ignore a 401 that
        // arrives later, so a store can have the endpoint challenge up front.
        setMcpAuth(true, true);

        $response = mcpInitialize();

        expect($response['status'])->toBe(401);
        expect(apiHeader($response, 'WWW-Authenticate'))->toContain('resource_metadata=');
    });

    it('lets an authenticated caller connect while that setting is on', function (): void {
        setMcpAuth(true, true);

        $response = mcpInitialize(adminToken());

        expect($response['status'])->toBe(200);
    });

});
