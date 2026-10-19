<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * When the store lists allowed redirect hosts, an application can send the
 * approval only to one of them, or to a loopback address.
 */

function allowedHostsServer(): Maho_ApiPlatform_Model_Oauth_Server
{
    /** @var Maho_ApiPlatform_Model_Oauth_Server $server */
    $server = Mage::getSingleton('apiplatform/oauth_server');
    return $server;
}

function allowedHostsSet(string $hosts): void
{
    Mage::app()->getStore()->setConfig('apiplatform/oauth2/allowed_redirect_hosts', $hosts);
}

/** @return array{client_id: string, code_challenge: string} */
function allowedHostsRegister(string $redirectUri): array
{
    $client = allowedHostsServer()->registerClient([
        'client_name' => 'Allowed Hosts Test',
        'redirect_uris' => [$redirectUri],
    ]);

    return ['client_id' => $client['client_id'], 'code_challenge' => str_repeat('a', 43)];
}

describe('The allowed redirect hosts', function (): void {
    it('decides which redirect URIs an application can register', function (string $hosts, string $redirectUri, bool $expected): void {
        allowedHostsSet($hosts);

        expect(Maho_ApiPlatform_Model_Oauth_Client::isAllowedRedirectUri($redirectUri))->toBe($expected);
    })->with([
        'any https host with an empty list' => ['', 'https://anything.example/cb', true],
        'a host on the list' => ["chatgpt.com\nclaude.ai", 'https://claude.ai/api/mcp/auth_callback', true],
        'a host on a line with spaces and a Windows line end' => ["chatgpt.com\r\n CLAUDE.ai \r\n", 'https://Claude.AI/api/mcp/auth_callback', true],
        'a host that is not on the list' => ['claude.ai', 'https://attacker.example/cb', false],
        'a subdomain of a host on the list' => ['claude.ai', 'https://attacker.claude.ai/cb', false],
        'a loopback address that is not on the list' => ['claude.ai', 'http://127.0.0.1:41234/callback', true],
    ]);

    it('refuses to register an application with a host that is not on the list', function (): void {
        allowedHostsSet('claude.ai');

        expect(fn() => allowedHostsRegister('https://attacker.example/cb'))
            ->toThrow(Maho_ApiPlatform_Model_Oauth_Exception::class);
    });

    it('refuses an application whose host the admin removed from the list, without redirecting to it', function (): void {
        $client = allowedHostsRegister('https://attacker.example/cb');
        allowedHostsSet('claude.ai');

        try {
            allowedHostsServer()->validateAuthorizationRequest([
                'client_id' => $client['client_id'],
                'redirect_uri' => 'https://attacker.example/cb',
                'response_type' => 'code',
                'code_challenge' => $client['code_challenge'],
                'code_challenge_method' => 'S256',
            ]);
            $this->fail('A redirect host that is not on the list must be refused.');
        } catch (Maho_ApiPlatform_Model_Oauth_Exception $e) {
            expect($e->isRedirectable())->toBeFalse();
        }
    });

    it('shows no warning for a host on the list', function (string $hosts, bool $expected): void {
        allowedHostsSet($hosts);

        /** @var Maho_ApiPlatform_Model_Oauth_Client $client */
        $client = Mage::getModel('apiplatform/oauth_client');
        $client->setData('is_trusted', 0);

        $block = new Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Oauth_Consent();
        $block->setAuthorizationRequest([
            'client' => $client,
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'scope' => 'mcp',
            'resource' => '',
            'code_challenge' => '',
            'state' => '',
        ]);

        expect($block->isUnverified())->toBe($expected);
    })->with([
        'the host is on the list' => ['claude.ai', false],
        'the list is empty' => ['', true],
    ]);
});
