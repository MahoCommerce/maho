<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function apiKeyTestCreateUser(string $apiKey): Mage_Api_Model_User
{
    $suffix = substr(md5(uniqid()), 0, 8);

    /** @var Mage_Api_Model_User $user */
    $user = Mage::getModel('api/user');
    $user->setUsername('apikey_' . $suffix)
        ->setFirstname('Api')
        ->setLastname('Key')
        ->setEmail("apikey_{$suffix}@example.com")
        ->setApiKey($apiKey)
        ->setIsActive(1)
        ->save();

    return $user;
}

/**
 * The edit form means "leave blank to keep the current key", so it saves a loaded user
 * whose api_key still holds the stored hash. Hashing that hash again would silently
 * lock the key out; Mage_Admin_Model_User::_beforeSave() guards the same case already.
 */
it('keeps the stored api key when a loaded user is saved without a new one', function () {
    $apiKey = 'Api-K3y-Unchanged-1';
    $user = apiKeyTestCreateUser($apiKey);
    $userId = (int) $user->getId();

    try {
        $storedHash = (string) Mage::getModel('api/user')->load($userId)->getApiKey();

        Mage::getModel('api/user')->load($userId)->setFirstname('Renamed')->save();

        $afterSave = Mage::getModel('api/user')->load($userId);
        expect($afterSave->getFirstname())->toBe('Renamed')
            ->and((string) $afterSave->getApiKey())->toBe($storedHash)
            ->and(Mage::getModel('api/user')->authenticate($afterSave->getUsername(), $apiKey))->toBeTrue();
    } finally {
        Mage::getModel('api/user')->load($userId)->delete();
    }
});

it('hashes a new api key typed on an existing user', function () {
    $apiKey = 'Api-K3y-Original-1';
    $newApiKey = 'Api-K3y-Replaced-2';
    $user = apiKeyTestCreateUser($apiKey);
    $userId = (int) $user->getId();

    try {
        Mage::getModel('api/user')->load($userId)->setApiKey($newApiKey)->save();

        $afterSave = Mage::getModel('api/user')->load($userId);
        $username = (string) $afterSave->getUsername();

        expect((string) $afterSave->getApiKey())->not->toBe($newApiKey)
            ->and(Mage::getModel('api/user')->authenticate($username, $newApiKey))->toBeTrue()
            ->and(Mage::getModel('api/user')->authenticate($username, $apiKey))->toBeFalse();
    } finally {
        Mage::getModel('api/user')->load($userId)->delete();
    }
});

it('keeps the stored api key when a user loaded by username is saved', function () {
    $apiKey = 'Api-K3y-ByUsername-1';
    $user = apiKeyTestCreateUser($apiKey);
    $username = (string) $user->getUsername();

    try {
        $storedHash = (string) $user->getApiKey();

        Mage::getModel('api/user')->loadByUsername($username)->setFirstname('Renamed')->save();

        $afterSave = Mage::getModel('api/user')->load($user->getId());
        expect($afterSave->getFirstname())->toBe('Renamed')
            ->and((string) $afterSave->getApiKey())->toBe($storedHash)
            ->and(Mage::getModel('api/user')->authenticate($username, $apiKey))->toBeTrue();
    } finally {
        $user->delete();
    }
});

it('keeps the stored api key when a user loaded by session id is saved', function () {
    $apiKey = 'Api-K3y-BySessId-1';
    $user = apiKeyTestCreateUser($apiKey);
    $username = (string) $user->getUsername();
    $sessId = md5(uniqid());

    try {
        $storedHash = (string) $user->getApiKey();
        $user->setSessid($sessId)->getResource()->recordSession($user);

        $bySession = Mage::getModel('api/user')->loadBySessId($sessId);
        expect((int) $bySession->getId())->toBe((int) $user->getId())
            ->and($bySession->getSessid())->toBe($sessId);

        $bySession->setFirstname('Renamed')->save();

        $afterSave = Mage::getModel('api/user')->load($user->getId());
        expect($afterSave->getFirstname())->toBe('Renamed')
            ->and((string) $afterSave->getApiKey())->toBe($storedHash)
            ->and(Mage::getModel('api/user')->authenticate($username, $apiKey))->toBeTrue();
    } finally {
        $user->delete();
    }
});

it('does not load a user when the username carries a NUL byte', function () {
    $user = apiKeyTestCreateUser('Api-K3y-Nul-1');

    try {
        expect(Mage::getModel('api/user')->loadByUsername($user->getUsername() . "\0")->getId())->toBeNull();
    } finally {
        $user->delete();
    }
});
