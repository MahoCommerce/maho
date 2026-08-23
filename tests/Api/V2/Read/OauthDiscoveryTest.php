<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * The two documents an MCP client reads before it can authenticate.
 *
 * RFC 9728 (protected resource) and RFC 8414 (authorization server). A client
 * that cannot read both, at their exact well-known paths, cannot connect at all.
 */

function setAuthorizationServer(bool $enabled): void
{
    Mage::getModel('core/config')->saveConfig(
        Maho_ApiPlatform_Helper_Data::XML_PATH_AUTHORIZATION_ENABLED,
        $enabled ? '1' : '0',
        'default',
        0,
    );
    Mage::app()->getCache()->cleanType('config');
}

describe('OAuth discovery documents', function (): void {

    afterEach(fn() => setAuthorizationServer(false));

    it('hides the authorization server metadata while the feature is off', function (): void {
        setAuthorizationServer(false);

        expect(apiGet('/.well-known/oauth-authorization-server')['status'])->toBe(404);
    });

    it('omits authorization_servers while the feature is off', function (): void {
        setAuthorizationServer(false);

        $response = apiGet('/.well-known/oauth-protected-resource');

        expect($response['status'])->toBe(200);
        // Naming an authorization server that does not answer sends a conformant
        // client down a path it cannot finish.
        expect($response['json'])->not->toHaveKey('authorization_servers');
    });

    it('names an authorization server once the feature is on', function (): void {
        setAuthorizationServer(true);

        $response = apiGet('/.well-known/oauth-protected-resource');

        expect($response['status'])->toBe(200);
        expect($response['json']['authorization_servers'])->toBeArray()->not->toBeEmpty();
        expect($response['json']['scopes_supported'])->toContain('mcp');
    });

    it('publishes the fields RFC 8414 requires', function (): void {
        setAuthorizationServer(true);

        $response = apiGet('/.well-known/oauth-authorization-server');

        expect($response['status'])->toBe(200);
        foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'response_types_supported', 'grant_types_supported'] as $field) {
            expect($response['json'])->toHaveKey($field);
        }
        expect($response['json']['response_types_supported'])->toBe(['code']);
        expect($response['json']['grant_types_supported'])->toBe(['authorization_code', 'refresh_token']);
    });

    it('offers S256 and refuses plain', function (): void {
        setAuthorizationServer(true);

        $methods = apiGet('/.well-known/oauth-authorization-server')['json']['code_challenge_methods_supported'];

        // `plain` gives an intercepted authorization code no protection at all.
        expect($methods)->toBe(['S256']);
    });

    it('keeps the admin path out of a document anyone can read', function (): void {
        setAuthorizationServer(true);

        $json = apiGet('/.well-known/oauth-authorization-server')['json'];

        $adminPath = (string) parse_url(
            Mage::helper('adminhtml')->getUrl('adminhtml/apiplatform_oauth/authorize', ['_nosecret' => true]),
            PHP_URL_PATH,
        );

        expect($json['authorization_endpoint'])->toContain('/api/oauth/authorize');
        expect($adminPath)->not->toBe('');
        expect($json['authorization_endpoint'])->not->toContain($adminPath);
    });

    it('serves every document without redirecting', function (): void {
        setAuthorizationServer(true);

        // RFC 8615 well-known URIs are exact. A redirect to a trailing-slash
        // variant points at a path no specification defines.
        $paths = [
            '/.well-known/oauth-protected-resource',
            '/.well-known/oauth-protected-resource/api/mcp',
            '/.well-known/oauth-authorization-server',
        ];
        foreach ($paths as $path) {
            expect(apiGet($path)['status'])->toBe(200);
        }
    });

    it('describes /api/mcp at the path RFC 9728 gives it', function (): void {
        setAuthorizationServer(true);

        $response = apiGet('/.well-known/oauth-protected-resource/api/mcp');

        expect($response['status'])->toBe(200);
        // A client holding only the MCP identifier reads this document, then asks for a token
        // with exactly this audience.
        expect($response['json']['resource'])->toEndWith('/api/mcp');
        expect($response['json']['authorization_servers'])->toBeArray()->not->toBeEmpty();
    });

    it('publishes an issuer a client can compare against the iss claim', function (): void {
        setAuthorizationServer(true);

        $issuer = apiGet('/.well-known/oauth-authorization-server')['json']['issuer'];

        // The comparison is character by character, so a trailing slash on either side
        // rejects every token this server signs.
        expect($issuer)->not->toEndWith('/');
        expect($issuer)->toBe((new \Maho\ApiPlatform\Service\JwtService())->getIssuer());
    });

});
