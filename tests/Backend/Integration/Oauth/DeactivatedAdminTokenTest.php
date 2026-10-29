<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * HackerOne #4084309: deactivating an admin user revokes its OAuth 1.0a tokens, so that a
 * later reactivation of the account does not bring them back.
 */

function deactivatedAdminTokenCreateAdmin(): Mage_Admin_Model_User
{
    $username = 'oauth_revoke_' . uniqid();

    /** @var Mage_Admin_Model_User $user */
    $user = Mage::getModel('admin/user');
    $user->setData([
        'username' => $username,
        'firstname' => 'Oauth',
        'lastname' => 'Revoke',
        'email' => $username . '@example.test',
        'password' => 'Temporary-P4ssword!',
        'is_active' => 1,
    ])->save();

    return $user;
}

function deactivatedAdminTokenCreateToken(int $adminId): Mage_Oauth_Model_Token
{
    $helper = Mage::helper('oauth');

    $consumer = Mage::getModel('oauth/consumer')->setData([
        'name' => 'Revoke ' . uniqid(),
        'key' => $helper->generateConsumerKey(),
        'secret' => $helper->generateConsumerSecret(),
    ]);
    $consumer->save();

    $token = Mage::getModel('oauth/token')->setData([
        'consumer_id' => $consumer->getId(),
        'admin_id' => $adminId,
        'type' => Mage_Oauth_Model_Token::TYPE_ACCESS,
        'token' => $helper->generateToken(),
        'secret' => $helper->generateTokenSecret(),
        'verifier' => $helper->generateVerifier(),
        'callback_url' => Mage_Oauth_Model_Server::CALLBACK_ESTABLISHED,
        'authorized' => 1,
        'revoked' => 0,
    ]);
    $token->save();

    return $token;
}

function deactivatedAdminTokenIsRevoked(Mage_Oauth_Model_Token $token): bool
{
    return (bool) Mage::getModel('oauth/token')->load($token->getId())->getRevoked();
}

it('revokes the tokens of an admin user that is deactivated', function () {
    $user = deactivatedAdminTokenCreateAdmin();
    $other = deactivatedAdminTokenCreateAdmin();

    try {
        $token = deactivatedAdminTokenCreateToken((int) $user->getId());
        $otherToken = deactivatedAdminTokenCreateToken((int) $other->getId());

        Mage::getModel('admin/user')->load($user->getId())->setIsActive(false)->save();

        expect(deactivatedAdminTokenIsRevoked($token))->toBeTrue()
            ->and(deactivatedAdminTokenIsRevoked($otherToken))->toBeFalse();
    } finally {
        $user->delete();
        $other->delete();
    }
});

it('keeps the tokens of an admin user that a save leaves active', function () {
    $user = deactivatedAdminTokenCreateAdmin();

    try {
        $token = deactivatedAdminTokenCreateToken((int) $user->getId());

        Mage::getModel('admin/user')->load($user->getId())->setFirstname('Renamed')->save();

        expect(deactivatedAdminTokenIsRevoked($token))->toBeFalse();
    } finally {
        $user->delete();
    }
});
