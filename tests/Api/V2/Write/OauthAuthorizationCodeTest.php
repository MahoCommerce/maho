<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * The OAuth 2.1 authorization code flow, and the rules that make it safe.
 *
 * The consent screen itself lives in the admin area and needs a browser, so the
 * approval step is driven through the server model here. Everything below the
 * approval is the same code the admin controller calls.
 */

function oauthServer(): Maho_ApiPlatform_Model_Oauth_Server
{
    /** @var Maho_ApiPlatform_Model_Oauth_Server $server */
    $server = Mage::getSingleton('apiplatform/oauth_server');
    return $server;
}

function oauthAdminId(): int
{
    return (int) Mage::getModel('admin/user')->getCollection()
        ->addFieldToFilter('is_active', 1)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
}

function oauthRedirectUri(): string
{
    return 'http://127.0.0.1:41234/callback';
}

/** @return array{verifier: string, challenge: string} */
function oauthPkce(): array
{
    $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

    return [
        'verifier' => $verifier,
        'challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
    ];
}

/** @return array<string, mixed> */
function oauthRegisterClient(): array
{
    return oauthServer()->registerClient([
        'client_name' => 'Test Client',
        'redirect_uris' => [oauthRedirectUri()],
    ]);
}

/** @return array{client_id: string, verifier: string, code: string} */
function oauthIssueCode(): array
{
    $client = oauthRegisterClient();
    $pkce = oauthPkce();

    $validated = oauthServer()->validateAuthorizationRequest([
        'client_id' => $client['client_id'],
        'redirect_uri' => oauthRedirectUri(),
        'response_type' => 'code',
        'code_challenge' => $pkce['challenge'],
        'code_challenge_method' => 'S256',
        'scope' => 'mcp',
    ]);

    $code = oauthServer()->issueAuthorizationCode($validated, oauthAdminId());

    return [
        'client_id' => $client['client_id'],
        'verifier' => $pkce['verifier'],
        'code' => (string) $code->getPlainToken(),
    ];
}

/** @return array<string, mixed> */
function oauthExchange(string $clientId, string $code, string $verifier, ?string $redirectUri = null): array
{
    return oauthServer()->exchangeAuthorizationCode([
        'client_id' => $clientId,
        'code' => $code,
        'redirect_uri' => $redirectUri ?? oauthRedirectUri(),
        'code_verifier' => $verifier,
    ]);
}

describe('OAuth authorization code flow', function (): void {

    it('exchanges a code for an access token and a refresh token', function (): void {
        $flow = oauthIssueCode();

        $tokens = oauthExchange($flow['client_id'], $flow['code'], $flow['verifier']);

        expect($tokens['token_type'])->toBe('Bearer');
        expect($tokens['access_token'])->toBeString()->not->toBeEmpty();
        expect($tokens['refresh_token'])->toBeString()->not->toBeEmpty();
        expect($tokens['scope'])->toBe('mcp');
    });

    it('binds the access token to a canonical resource, not a placeholder', function (): void {
        $flow = oauthIssueCode();

        $tokens = oauthExchange($flow['client_id'], $flow['code'], $flow['verifier']);
        $payload = (new \Maho\ApiPlatform\Service\JwtService())->decodeToken($tokens['access_token']);

        $audiences = is_array($payload->aud) ? $payload->aud : [$payload->aud];
        // RFC 8707: the audience names the resource, so a token minted for one
        // endpoint cannot be replayed against another.
        expect($audiences)->not->toBeEmpty();
        foreach ($audiences as $audience) {
            expect(Mage::helper('apiplatform')->getCanonicalResources())->toContain(rtrim((string) $audience, '/'));
        }
    });

    it('refuses a code verifier that does not match the challenge', function (): void {
        $flow = oauthIssueCode();

        expect(fn() => oauthExchange($flow['client_id'], $flow['code'], 'a-verifier-that-was-never-used-for-this-code'))
            ->toThrow(Maho_ApiPlatform_Model_Oauth_Exception::class);
    });

    it('refuses a redirect_uri that differs from the authorization request', function (): void {
        $flow = oauthIssueCode();

        expect(fn() => oauthExchange($flow['client_id'], $flow['code'], $flow['verifier'], 'http://127.0.0.1:41234/other'))
            ->toThrow(Maho_ApiPlatform_Model_Oauth_Exception::class);
    });

    it('revokes the whole grant when a code is presented twice', function (): void {
        $flow = oauthIssueCode();
        $tokens = oauthExchange($flow['client_id'], $flow['code'], $flow['verifier']);

        // A replayed code means it leaked. The legitimate holder already has its
        // tokens, so the grant is cut rather than guessing which caller was real.
        expect(fn() => oauthExchange($flow['client_id'], $flow['code'], $flow['verifier']))
            ->toThrow(Maho_ApiPlatform_Model_Oauth_Exception::class);

        expect(fn() => oauthServer()->exchangeRefreshToken([
            'client_id' => $flow['client_id'],
            'refresh_token' => $tokens['refresh_token'],
        ]))->toThrow(Maho_ApiPlatform_Model_Oauth_Exception::class);
    });

    it('rotates the refresh token on every use', function (): void {
        $flow = oauthIssueCode();
        $first = oauthExchange($flow['client_id'], $flow['code'], $flow['verifier']);

        $second = oauthServer()->exchangeRefreshToken([
            'client_id' => $flow['client_id'],
            'refresh_token' => $first['refresh_token'],
        ]);

        expect($second['refresh_token'])->not->toBe($first['refresh_token']);
    });

    it('revokes the grant when a rotated refresh token is used again', function (): void {
        $flow = oauthIssueCode();
        $first = oauthExchange($flow['client_id'], $flow['code'], $flow['verifier']);
        $second = oauthServer()->exchangeRefreshToken([
            'client_id' => $flow['client_id'],
            'refresh_token' => $first['refresh_token'],
        ]);

        expect(fn() => oauthServer()->exchangeRefreshToken([
            'client_id' => $flow['client_id'],
            'refresh_token' => $first['refresh_token'],
        ]))->toThrow(Maho_ApiPlatform_Model_Oauth_Exception::class);

        // The reuse cut the grant, so the token that was current also stops.
        expect(fn() => oauthServer()->exchangeRefreshToken([
            'client_id' => $flow['client_id'],
            'refresh_token' => $second['refresh_token'],
        ]))->toThrow(Maho_ApiPlatform_Model_Oauth_Exception::class);
    });

});

describe('OAuth authorization request validation', function (): void {

    it('refuses a redirect_uri the client did not register', function (): void {
        $client = oauthRegisterClient();
        $pkce = oauthPkce();

        expect(fn() => oauthServer()->validateAuthorizationRequest([
            'client_id' => $client['client_id'],
            'redirect_uri' => 'https://attacker.example.com/callback',
            'response_type' => 'code',
            'code_challenge' => $pkce['challenge'],
            'code_challenge_method' => 'S256',
        ]))->toThrow(Maho_ApiPlatform_Model_Oauth_Exception::class);
    });

    it('refuses the plain code challenge method', function (): void {
        $client = oauthRegisterClient();
        $pkce = oauthPkce();

        expect(fn() => oauthServer()->validateAuthorizationRequest([
            'client_id' => $client['client_id'],
            'redirect_uri' => oauthRedirectUri(),
            'response_type' => 'code',
            'code_challenge' => $pkce['challenge'],
            'code_challenge_method' => 'plain',
        ]))->toThrow(Maho_ApiPlatform_Model_Oauth_Exception::class);
    });

    it('refuses a resource this install does not serve', function (): void {
        $client = oauthRegisterClient();
        $pkce = oauthPkce();

        expect(fn() => oauthServer()->validateAuthorizationRequest([
            'client_id' => $client['client_id'],
            'redirect_uri' => oauthRedirectUri(),
            'response_type' => 'code',
            'code_challenge' => $pkce['challenge'],
            'code_challenge_method' => 'S256',
            'resource' => 'https://someone-elses-store.example.com',
        ]))->toThrow(Maho_ApiPlatform_Model_Oauth_Exception::class);
    });

    it('reports an unknown client without redirecting anywhere', function (): void {
        $pkce = oauthPkce();

        try {
            oauthServer()->validateAuthorizationRequest([
                'client_id' => 'no-such-client',
                'redirect_uri' => oauthRedirectUri(),
                'response_type' => 'code',
                'code_challenge' => $pkce['challenge'],
                'code_challenge_method' => 'S256',
            ]);
            $this->fail('An unknown client must be refused.');
        } catch (Maho_ApiPlatform_Model_Oauth_Exception $e) {
            // Redirecting to a URI belonging to a client that could not be
            // established is an open redirect.
            expect($e->isRedirectable())->toBeFalse();
        }
    });

});

describe('OAuth client registration', function (): void {

    it('refuses a redirect URI that is neither HTTPS nor loopback', function (): void {
        expect(fn() => oauthServer()->registerClient([
            'client_name' => 'Insecure',
            'redirect_uris' => ['http://example.com/callback'],
        ]))->toThrow(Maho_ApiPlatform_Model_Oauth_Exception::class);
    });

    it('refuses a redirect URI carrying a fragment', function (): void {
        expect(fn() => oauthServer()->registerClient([
            'client_name' => 'Fragment',
            'redirect_uris' => ['https://example.com/callback#token'],
        ]))->toThrow(Maho_ApiPlatform_Model_Oauth_Exception::class);
    });

    it('accepts HTTPS and loopback redirect URIs', function (): void {
        $client = oauthServer()->registerClient([
            'client_name' => 'Valid',
            'redirect_uris' => ['https://example.com/callback', 'http://127.0.0.1:9999/cb'],
        ]);

        expect($client['client_id'])->toBeString()->not->toBeEmpty();
        expect($client['registration_access_token'])->toBeString()->not->toBeEmpty();
    });

    it('marks a self-registered client as unverified', function (): void {
        $client = oauthRegisterClient();

        /** @var Maho_ApiPlatform_Model_Oauth_Client $model */
        $model = Mage::getModel('apiplatform/oauth_client');
        $model->loadByClientId($client['client_id']);

        // The consent screen warns about this, so nobody approves a client they
        // did not start a connection with.
        expect($model->isTrusted())->toBeFalse();
    });

});
