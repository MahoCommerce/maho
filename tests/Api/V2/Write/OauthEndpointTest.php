<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * The machine endpoints under /api/oauth, over HTTP.
 *
 * These check the wire format RFC 6749 requires, which the model-level tests in
 * OauthAuthorizationCodeTest cannot see: form encoding, snake_case error bodies
 * and an `access_token` field.
 */

function enableOauthServer(bool $enabled): void
{
    $config = Mage::getModel('core/config');
    $config->saveConfig(Maho_ApiPlatform_Helper_Data::XML_PATH_AUTHORIZATION_ENABLED, $enabled ? '1' : '0', 'default', 0);
    $config->saveConfig(Maho_ApiPlatform_Helper_Data::XML_PATH_DYNAMIC_REGISTRATION, '1', 'default', 0);
    Mage::app()->getCache()->cleanType('config');
}

function oauthForm(string $path, array $fields): array
{
    return apiPostRaw($path, http_build_query($fields), null, ['Content-Type: application/x-www-form-urlencoded']);
}

describe('OAuth machine endpoints', function (): void {

    beforeEach(fn() => enableOauthServer(true));
    afterEach(fn() => enableOauthServer(false));

    it('registers a client and returns RFC 7591 metadata', function (): void {
        $response = apiPost('/api/oauth/register', [
            'client_name' => 'Endpoint Test',
            'redirect_uris' => ['http://127.0.0.1:41234/callback'],
        ]);

        expect($response['status'])->toBe(201);
        expect($response['json']['client_id'])->toBeString()->not->toBeEmpty();
        expect($response['json']['grant_types'])->toBe(['authorization_code', 'refresh_token']);
    });

    it('accepts a form encoded token request', function (): void {
        // RFC 6749 section 4.1.3 requires application/x-www-form-urlencoded, so a
        // JSON-only endpoint is unreachable for a conformant client.
        $response = oauthForm('/api/oauth/token', ['grant_type' => 'authorization_code', 'code' => 'not-a-real-code']);

        // The grant is rejected on its merits, not because the body was unreadable.
        expect($response['status'])->toBe(400);
        expect($response['json']['error'])->toBe('invalid_client');
    });

    it('reports an unsupported grant type in the RFC 6749 error shape', function (): void {
        $response = oauthForm('/api/oauth/token', ['grant_type' => 'password']);

        expect($response['status'])->toBe(400);
        expect($response['json']['error'])->toBe('unsupported_grant_type');
        expect($response['json'])->toHaveKey('error_description');
    });

    it('renders an error rather than redirecting when the redirect URI is unknown', function (): void {
        $client = apiPost('/api/oauth/register', [
            'client_name' => 'Redirect Test',
            'redirect_uris' => ['http://127.0.0.1:41234/callback'],
        ])['json'];

        $response = apiGet('/api/oauth/authorize?' . http_build_query([
            'client_id' => $client['client_id'],
            'redirect_uri' => 'https://attacker.example.com/cb',
            'response_type' => 'code',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]));

        // Redirecting an error to an unverified URI is an open redirect.
        expect($response['status'])->toBe(400);
        expect($response['json']['error'])->toBe('invalid_request');
    });

    it('hides every endpoint while the feature is off', function (): void {
        enableOauthServer(false);

        expect(apiPost('/api/oauth/register', ['client_name' => 'x', 'redirect_uris' => ['https://a.test/cb']])['status'])->toBe(404);
        expect(oauthForm('/api/oauth/token', ['grant_type' => 'authorization_code'])['status'])->toBe(404);
        expect(apiGet('/api/oauth/authorize')['status'])->toBe(404);
    });

});
