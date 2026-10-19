<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * Only the redirect host receives the authorization code. A loopback redirect
 * keeps the code on the computer of the admin (RFC 8252, section 7.3), so a
 * link from another person cannot get it, and the consent screen gives no warning.
 */

it('warns about an unverified client only when the code leaves the computer of the admin', function (string $redirectUri, bool $expected): void {
    /** @var Maho_ApiPlatform_Model_Oauth_Client $client */
    $client = Mage::getModel('apiplatform/oauth_client');
    $client->setData('is_trusted', 0);

    $block = new Maho_ApiPlatform_Block_Adminhtml_Apiplatform_Oauth_Consent();
    $block->setAuthorizationRequest([
        'client' => $client,
        'redirect_uri' => $redirectUri,
        'scope' => 'mcp',
        'resource' => '',
        'code_challenge' => '',
        'state' => '',
    ]);

    expect($block->isUnverified())->toBe($expected);
})->with([
    'an IPv4 loopback redirect' => ['http://127.0.0.1:41234/callback', false],
    'an IPv6 loopback redirect' => ['http://[::1]:41234/callback', false],
    'a localhost redirect' => ['http://LocalHost:41234/callback', false],
    'an https redirect' => ['https://claude.ai/api/mcp/auth_callback', true],
    'an https redirect to a host that starts with localhost' => ['https://localhost.attacker.example/callback', true],
    'a loopback host behind a user part' => ['http://evil.example@localhost/cb', true],
    'a loopback host behind a user part and a backslash' => ['http://evil.example\@localhost/cb', true],
]);
