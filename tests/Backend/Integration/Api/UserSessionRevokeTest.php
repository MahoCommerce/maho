<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * HackerOne #4084309: a SOAP, XML-RPC or JSON-RPC session must end when its API user is
 * deactivated or gets a new API key. Both api_user models save through the same event.
 */

function sessionRevokeCreateUser(string $apiKey = 'Api-K3y-Session-1'): Mage_Api_Model_User
{
    $suffix = substr(md5(uniqid()), 0, 8);

    /** @var Mage_Api_Model_User $user */
    $user = Mage::getModel('api/user');
    $user->setUsername('session_' . $suffix)
        ->setFirstname('Api')
        ->setLastname('Session')
        ->setEmail("session_{$suffix}@example.com")
        ->setApiKey($apiKey)
        ->setIsActive()
        ->save();

    return $user;
}

function sessionRevokeOpenSession(Mage_Api_Model_User $user): string
{
    $sessId = md5(uniqid('', true));
    $user->setSessid($sessId)->getResource()->recordSession($user);
    return $sessId;
}

function sessionRevokeSessionExists(string $sessId): bool
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');

    return (bool) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('api/session'), ['user_id'])
            ->where('sessid = ?', $sessId),
    );
}

it('resumes the session of an active user', function () {
    $user = sessionRevokeCreateUser();

    try {
        $sessId = sessionRevokeOpenSession($user);

        expect(Mage::getModel('api/session')->isLoggedIn($sessId))->toBeTrue();
    } finally {
        $user->delete();
    }
});

it('refuses and deletes the session of a user that is deactivated outside the model', function () {
    $user = sessionRevokeCreateUser();

    try {
        $sessId = sessionRevokeOpenSession($user);

        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->update(
            $resource->getTableName('api/user'),
            ['is_active' => 0],
            ['user_id = ?' => (int) $user->getId()],
        );

        expect(Mage::getModel('api/session')->isLoggedIn($sessId))->toBeFalse()
            ->and(sessionRevokeSessionExists($sessId))->toBeFalse();
    } finally {
        $user->delete();
    }
});

it('deletes the sessions of a user that the legacy model deactivates', function () {
    $user = sessionRevokeCreateUser();

    try {
        $sessId = sessionRevokeOpenSession($user);

        Mage::getModel('api/user')->load($user->getId())->setIsActive(false)->save();

        expect(sessionRevokeSessionExists($sessId))->toBeFalse();
    } finally {
        $user->delete();
    }
});

it('deletes the sessions of a user that gets a new api key', function () {
    $user = sessionRevokeCreateUser('Api-K3y-Original-1');

    try {
        $sessId = sessionRevokeOpenSession($user);

        Mage::getModel('api/user')->load($user->getId())->setData('new_api_key', 'Api-K3y-Rotated-2')->save();

        expect(sessionRevokeSessionExists($sessId))->toBeFalse();
    } finally {
        $user->delete();
    }
});

it('keeps the sessions when a save does not change the access', function () {
    $apiKey = 'Api-K3y-Same-1';
    $user = sessionRevokeCreateUser($apiKey);

    try {
        $sessId = sessionRevokeOpenSession($user);

        Mage::getModel('api/user')->load($user->getId())->setFirstname('Renamed')->save();
        Mage::getModel('api/user')->load($user->getId())->setData('new_api_key', $apiKey)->save();

        expect(sessionRevokeSessionExists($sessId))->toBeTrue();
    } finally {
        $user->delete();
    }
});

it('keeps the session that a login opens when the login upgrades a legacy hash', function () {
    $apiKey = 'Api-K3y-Legacy-1';
    $user = sessionRevokeCreateUser($apiKey);
    $legacyHash = md5('xy' . $apiKey) . ':xy';

    try {
        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->update(
            $resource->getTableName('api/user'),
            ['api_key' => $legacyHash],
            ['user_id = ?' => (int) $user->getId()],
        );

        $sessId = md5(uniqid('', true));
        $login = Mage::getModel('api/user')->setSessid($sessId)->login((string) $user->getUsername(), $apiKey);

        expect((int) $login->getId())->toBe((int) $user->getId())
            ->and((string) Mage::getModel('api/user')->load($user->getId())->getApiKey())->not->toBe($legacyHash)
            ->and(sessionRevokeSessionExists($sessId))->toBeTrue();
    } finally {
        $user->delete();
    }
});

it('deletes the sessions of a user that the API Platform model deactivates or gives a new key', function () {
    $apiKey = 'Api-K3y-Platform-1';
    $user = sessionRevokeCreateUser($apiKey);

    try {
        $sessId = sessionRevokeOpenSession($user);
        Mage::getModel('apiplatform/user')->load($user->getId())->setApiKey($apiKey)->save();
        expect(sessionRevokeSessionExists($sessId))->toBeTrue();

        Mage::getModel('apiplatform/user')->load($user->getId())->setApiKey('Api-K3y-Platform-2')->save();
        expect(sessionRevokeSessionExists($sessId))->toBeFalse();

        $sessId = sessionRevokeOpenSession($user);
        Mage::getModel('apiplatform/user')->load($user->getId())->setIsActive(false)->save();
        expect(sessionRevokeSessionExists($sessId))->toBeFalse();
    } finally {
        $user->delete();
    }
});
