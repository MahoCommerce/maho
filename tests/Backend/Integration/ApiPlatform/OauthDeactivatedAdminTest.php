<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * Deactivating an admin user revokes the OAuth grants it approved, so that a later
 * reactivation of the account does not connect the applications again.
 */

function deactivatedAdminGrantCreateAdmin(): Mage_Admin_Model_User
{
    $username = 'grant_revoke_' . uniqid();

    /** @var Mage_Admin_Model_User $user */
    $user = Mage::getModel('admin/user');
    $user->setData([
        'username' => $username,
        'firstname' => 'Grant',
        'lastname' => 'Revoke',
        'email' => $username . '@example.test',
        'password' => 'Temporary-P4ssword!',
        'is_active' => 1,
    ])->save();

    return $user;
}

/**
 * @param array<string, mixed> $data
 */
function deactivatedAdminGrantCreateRow(array $data): int
{
    $token = Mage::getModel('apiplatform/oauth_token')->setData($data + [
        'client_id' => 'grant_revoke_' . uniqid(),
        'scope' => 'mcp',
        'revoked' => 0,
    ]);
    $token->save();

    return (int) $token->getId();
}

function deactivatedAdminGrantIsRevoked(int $entityId): bool
{
    return (bool) Mage::getModel('apiplatform/oauth_token')->load($entityId)->getData('revoked');
}

it('revokes the grants of an admin user that is deactivated', function () {
    $user = deactivatedAdminGrantCreateAdmin();
    $other = deactivatedAdminGrantCreateAdmin();

    try {
        $consentId = deactivatedAdminGrantCreateRow([
            'admin_id' => (int) $user->getId(),
            'type' => Maho_ApiPlatform_Model_Oauth_Token::TYPE_CONSENT,
        ]);
        $refreshId = deactivatedAdminGrantCreateRow([
            'parent_id' => $consentId,
            'admin_id' => (int) $user->getId(),
            'type' => Maho_ApiPlatform_Model_Oauth_Token::TYPE_REFRESH,
            'token_hash' => hash('sha256', uniqid('', true)),
            'expires_at' => time() + 3600,
        ]);
        $otherConsentId = deactivatedAdminGrantCreateRow([
            'admin_id' => (int) $other->getId(),
            'type' => Maho_ApiPlatform_Model_Oauth_Token::TYPE_CONSENT,
        ]);

        Mage::getModel('admin/user')->load($user->getId())->setIsActive(false)->save();

        expect(deactivatedAdminGrantIsRevoked($consentId))->toBeTrue()
            ->and(deactivatedAdminGrantIsRevoked($refreshId))->toBeTrue()
            ->and(deactivatedAdminGrantIsRevoked($otherConsentId))->toBeFalse();
    } finally {
        $user->delete();
        $other->delete();
    }
});

it('keeps the grants of an admin user that a save leaves active', function () {
    $user = deactivatedAdminGrantCreateAdmin();

    try {
        $consentId = deactivatedAdminGrantCreateRow([
            'admin_id' => (int) $user->getId(),
            'type' => Maho_ApiPlatform_Model_Oauth_Token::TYPE_CONSENT,
        ]);

        Mage::getModel('admin/user')->load($user->getId())->setFirstname('Renamed')->save();

        expect(deactivatedAdminGrantIsRevoked($consentId))->toBeFalse();
    } finally {
        $user->delete();
    }
});
