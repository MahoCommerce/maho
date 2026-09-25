<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * The consent page needs only api_connect, not the grid of every connection.
 * On My Account, an admin sees and revokes only their own connections.
 */

function connectionsTokenResource(): Maho_ApiPlatform_Model_Resource_Oauth_Token
{
    /** @var Maho_ApiPlatform_Model_Resource_Oauth_Token $resource */
    $resource = Mage::getResourceSingleton('apiplatform/oauth_token');
    return $resource;
}

function connectionsAdminId(): int
{
    return (int) Mage::getModel('admin/user')->getCollection()
        ->addFieldToFilter('is_active', 1)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
}

/**
 * Approve a new client for this admin, and return the client, the consent and the code.
 *
 * @return array{client_id: string, consent_id: int, code: Maho_ApiPlatform_Model_Oauth_Token}
 */
function connectionsApprove(int $adminId, string $clientName = 'Connections Test'): array
{
    /** @var Maho_ApiPlatform_Model_Oauth_Server $server */
    $server = Mage::getSingleton('apiplatform/oauth_server');
    $client = $server->registerClient([
        'client_name' => $clientName,
        'redirect_uris' => ['http://127.0.0.1:41234/callback'],
    ]);

    $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $validated = $server->validateAuthorizationRequest([
        'client_id' => $client['client_id'],
        'redirect_uri' => 'http://127.0.0.1:41234/callback',
        'response_type' => 'code',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
        'scope' => 'mcp',
    ]);

    $code = $server->issueAuthorizationCode($validated, $adminId);

    return ['client_id' => (string) $client['client_id'], 'consent_id' => (int) $code->getData('parent_id'), 'code' => $code];
}

function connectionsRegister(): string
{
    /** @var Maho_ApiPlatform_Model_Oauth_Server $server */
    $server = Mage::getSingleton('apiplatform/oauth_server');
    return (string) $server->registerClient([
        'client_name' => 'Never Approved',
        'redirect_uris' => ['http://127.0.0.1:41234/callback'],
    ])['client_id'];
}

function connectionsClientExists(string $clientId): bool
{
    return (bool) Mage::getModel('apiplatform/oauth_client')->load($clientId, 'client_id')->getId();
}

function connectionsClientResource(): Maho_ApiPlatform_Model_Resource_Oauth_Client
{
    /** @var Maho_ApiPlatform_Model_Resource_Oauth_Client $resource */
    $resource = Mage::getResourceSingleton('apiplatform/oauth_client');
    return $resource;
}

function connectionsIsRevoked(int $entityId): bool
{
    $token = Mage::getModel('apiplatform/oauth_token')->load($entityId);
    return (bool) $token->getData('revoked');
}

/**
 * @param array<string, mixed> $data
 */
function connectionsRenderConsent(array $data): string
{
    $design = Mage::getDesign();
    // Only the "default" package holds the admin templates.
    $previousDesign = $design->setAllGetOld([
        'area' => Mage_Core_Model_App_Area::AREA_ADMINHTML,
        'package' => Mage_Core_Model_Design_Package::DEFAULT_PACKAGE,
    ]);

    try {
        $layout = Mage::app()->getLayout();
        $layout->setArea(Mage_Core_Model_App_Area::AREA_ADMINHTML);

        return $layout->createBlock('apiplatform/adminhtml_apiplatform_oauth_consent')
            ->setData($data)
            ->toHtml();
    } finally {
        $design->setAllGetOld($previousDesign);
    }
}

describe('The connections of one admin', function (): void {
    it('lists the live consents of the admin with the client name', function (): void {
        $adminId = connectionsAdminId();
        $approved = connectionsApprove($adminId, 'Listed <b>App</b>');

        $connections = connectionsTokenResource()->getAdminConsents($adminId);
        $listed = array_values(array_filter(
            $connections,
            fn(array $connection): bool => $connection['consent_id'] === $approved['consent_id'],
        ));

        expect($listed)->toHaveCount(1)
            ->and($listed[0]['client_name'])->toBe('Listed <b>App</b>');
    });

    it('lists no consent of another admin', function (): void {
        $approved = connectionsApprove(connectionsAdminId());

        $consentIds = array_column(connectionsTokenResource()->getAdminConsents(connectionsAdminId() + 100000), 'consent_id');

        expect($consentIds)->not->toContain($approved['consent_id']);
    });

    it('revokes a consent and everything issued under it for its owner', function (): void {
        $adminId = connectionsAdminId();
        $approved = connectionsApprove($adminId);

        expect(connectionsTokenResource()->revokeAdminConsent($approved['consent_id'], $adminId))->toBeTrue()
            ->and(connectionsIsRevoked($approved['consent_id']))->toBeTrue()
            ->and(connectionsIsRevoked((int) $approved['code']->getId()))->toBeTrue()
            ->and(array_column(connectionsTokenResource()->getAdminConsents($adminId), 'consent_id'))
            ->not->toContain($approved['consent_id']);
    });

    it('refuses to revoke the consent of another admin', function (): void {
        $approved = connectionsApprove(connectionsAdminId());

        expect(connectionsTokenResource()->revokeAdminConsent($approved['consent_id'], connectionsAdminId() + 100000))->toBeFalse()
            ->and(connectionsIsRevoked($approved['consent_id']))->toBeFalse();
    });

    it('refuses to revoke a token that is not a consent', function (): void {
        $adminId = connectionsAdminId();
        $approved = connectionsApprove($adminId);

        expect(connectionsTokenResource()->revokeAdminConsent((int) $approved['code']->getId(), $adminId))->toBeFalse();
    });
});

describe('The grid of every client', function (): void {
    it('shows the admins who approved each client', function (): void {
        $adminId = connectionsAdminId();
        $approved = connectionsApprove($adminId);
        $unapproved = connectionsRegister();
        $username = (string) Mage::getModel('admin/user')->load($adminId)->getUsername();

        $collection = Mage::getResourceModel('apiplatform/oauth_client_collection')
            ->addFieldToFilter('client_id', ['in' => [$approved['client_id'], $unapproved]])
            ->addApprovingAdmins();
        $approvedBy = [];
        foreach ($collection as $client) {
            $approvedBy[$client->getData('client_id')] = $client->getData('approved_by');
        }

        expect($approvedBy[$approved['client_id']])->toBe($username)
            ->and($approvedBy[$unapproved])->toBe('');
    });

    it('stops showing an admin after the revoke', function (): void {
        $adminId = connectionsAdminId();
        $approved = connectionsApprove($adminId);

        connectionsTokenResource()->revokeAdminConsent($approved['consent_id'], $adminId);

        expect(connectionsTokenResource()->getApprovingAdmins([$approved['client_id']]))->toBe([]);
    });

    it('deletes a client that nobody approved, with its tokens', function (): void {
        $clientId = connectionsRegister();

        expect(connectionsClientResource()->deleteUnusedClients([$clientId]))->toBe(['deleted' => 1, 'kept' => 0])
            ->and(connectionsClientExists($clientId))->toBeFalse();
    });

    it('keeps a client with a live consent', function (): void {
        $approved = connectionsApprove(connectionsAdminId());

        expect(connectionsClientResource()->deleteUnusedClients([$approved['client_id']]))->toBe(['deleted' => 0, 'kept' => 1])
            ->and(connectionsClientExists($approved['client_id']))->toBeTrue()
            ->and(connectionsIsRevoked($approved['consent_id']))->toBeFalse();
    });

    it('deletes a client and its tokens after the revoke', function (): void {
        $adminId = connectionsAdminId();
        $approved = connectionsApprove($adminId);
        connectionsTokenResource()->revokeAdminConsent($approved['consent_id'], $adminId);

        expect(connectionsClientResource()->deleteUnusedClients([$approved['client_id']]))->toBe(['deleted' => 1, 'kept' => 0])
            ->and(connectionsClientExists($approved['client_id']))->toBeFalse()
            ->and(Mage::getModel('apiplatform/oauth_token')->load($approved['consent_id'])->getId())->toBeNull();
    });
});

describe('The connect permission', function (): void {
    it('is a top-level ACL resource, apart from the grid of every connection', function (): void {
        $resources = Mage::getModel('admin/roles')->getResourcesList2D();

        expect($resources)->toContain('admin/api_connect')
            ->and($resources)->toContain('admin/system/api/oauth_clients')
            ->and(Maho_ApiPlatform_Adminhtml_Apiplatform_OauthController::CONNECT_RESOURCE)->toBe('api_connect');
    });
});

describe('The consent page', function (): void {
    it('is a standalone page that fits the screen of a phone', function (): void {
        $client = Mage::getModel('apiplatform/oauth_client')->setData('client_name', 'Phone App');
        $html = connectionsRenderConsent(['authorization_request' => [
            'client' => $client,
            'redirect_uri' => 'http://127.0.0.1:41234/callback',
            'scope' => 'mcp',
            'resource' => 'https://maho.example',
            'code_challenge' => 'x',
            'state' => '',
        ]]);

        expect($html)->toStartWith('<!DOCTYPE html>')
            ->and($html)->toContain('<meta name="viewport" content="width=device-width, initial-scale=1">')
            ->and($html)->toContain('login.css')
            ->and($html)->toContain('name="decision" value="approve"')
            ->and($html)->toContain('name="form_key"');
    });

    it('escapes the client name', function (): void {
        $client = Mage::getModel('apiplatform/oauth_client')->setData('client_name', 'Evil <script>alert("client")</script> app');
        $html = connectionsRenderConsent(['authorization_request' => [
            'client' => $client,
            'redirect_uri' => 'http://127.0.0.1:41234/callback',
            'scope' => 'mcp',
            'resource' => 'https://maho.example',
            'code_challenge' => 'x',
            'state' => '',
        ]]);

        expect($html)->not->toContain('<script>alert')
            ->and($html)->toContain('&lt;script&gt;alert(&quot;client&quot;)&lt;/script&gt;');
    });

    it('shows an error in place of the approval form', function (): void {
        $html = connectionsRenderConsent(['error_message' => 'This authorization request expired <now>.']);

        expect($html)->toContain('This authorization request expired &lt;now&gt;.')
            ->and($html)->not->toContain('name="decision"');
    });
});
