<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * Maho_ApiPlatform_Model_User is the API Platform's own service account model.
 * It shares the api_user, api_role and api_rule tables with the deprecated
 * Mage_Api module but must not depend on any of its classes.
 */

function apiPlatformTestUser(string $suffix): Maho_ApiPlatform_Model_User
{
    return Mage::getModel('apiplatform/user')
        ->setUsername('apiplatform_' . $suffix)
        ->setFirstname('Api')
        ->setLastname('Platform')
        ->setEmail("apiplatform_{$suffix}@example.com")
        ->setApiKey('Secret' . $suffix . '1')
        ->setIsActive();
}

function apiPlatformTestGroupRole(string $name): int
{
    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $write->insert($resource->getTableName('apiplatform/role'), [
        'parent_id'  => 0,
        'tree_level' => 1,
        'sort_order' => 0,
        'role_type'  => Maho_ApiPlatform_Model_User::ROLE_TYPE_GROUP,
        'user_id'    => 0,
        'role_name'  => $name,
    ]);
    return (int) $write->lastInsertId();
}

it('hashes the api key on save and keeps the hash on a later save', function () {
    $suffix = substr(md5(uniqid()), 0, 8);
    $user = apiPlatformTestUser($suffix);
    $user->save();
    $userId = (int) $user->getId();

    try {
        $stored = Mage::getModel('apiplatform/user')->load($userId);
        expect($stored->getApiKey())->not->toBe('Secret' . $suffix . '1')
            ->and(Mage::helper('core')->validateHash('Secret' . $suffix . '1', (string) $stored->getApiKey()))->toBeTrue();

        $stored->setFirstname('Renamed')->save();
        $again = Mage::getModel('apiplatform/user')->load($userId);
        expect($again->getApiKey())->toBe($stored->getApiKey())
            ->and($again->getFirstname())->toBe('Renamed');

        expect(Mage::getModel('apiplatform/user')->authenticate('apiplatform_' . $suffix, 'Secret' . $suffix . '1'))->toBeTrue()
            ->and(Mage::getModel('apiplatform/user')->authenticate('apiplatform_' . $suffix, 'wrong'))->toBeFalse();
    } finally {
        Mage::getModel('apiplatform/user')->load($userId)->delete();
    }
});

it('stores allowed store ids as json and reads them back as ints', function () {
    $suffix = substr(md5(uniqid()), 0, 8);
    $user = apiPlatformTestUser($suffix)->setAllowedStoreIds([1, '2', 0]);
    $user->save();
    $userId = (int) $user->getId();

    try {
        $reloaded = Mage::getModel('apiplatform/user')->load($userId);
        expect($reloaded->getData('allowed_store_ids'))->toBe('[1,2]')
            ->and($reloaded->getAllowedStoreIds())->toBe([1, 2]);

        $reloaded->setAllowedStoreIds([])->save();
        $cleared = Mage::getModel('apiplatform/user')->load($userId);
        expect($cleared->getData('allowed_store_ids'))->toBeNull()
            ->and($cleared->getAllowedStoreIds())->toBe([]);
    } finally {
        Mage::getModel('apiplatform/user')->load($userId)->delete();
    }
});

it('generates client credentials that verify and survive a save', function () {
    $suffix = substr(md5(uniqid()), 0, 8);
    $user = apiPlatformTestUser($suffix);
    $secret = $user->generateClientCredentials();
    $user->save();
    $userId = (int) $user->getId();

    try {
        $byClient = Mage::getModel('apiplatform/user')->loadByClientId((string) $user->getClientId());
        expect((int) $byClient->getId())->toBe($userId)
            ->and($byClient->verifyClientSecret($secret))->toBeTrue()
            ->and($byClient->verifyClientSecret('nope'))->toBeFalse()
            ->and(Mage::getModel('apiplatform/user')->loadByClientId('')->getId())->toBeNull();
    } finally {
        Mage::getModel('apiplatform/user')->load($userId)->delete();
    }
});

it('assigns one group role, reports it and drops the assignment on delete', function () {
    $suffix = substr(md5(uniqid()), 0, 8);
    $roleId = apiPlatformTestGroupRole('apiplatform-test-role-' . $suffix);
    $otherRoleId = apiPlatformTestGroupRole('apiplatform-test-role-other-' . $suffix);
    $user = apiPlatformTestUser($suffix);
    $user->save();
    $userId = (int) $user->getId();

    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $roleTable = $resource->getTableName('apiplatform/role');

    try {
        expect($user->getRoleIds())->toBe([]);

        $user->assignRole($roleId);
        expect(Mage::getModel('apiplatform/user')->load($userId)->getRoleIds())->toBe([$roleId]);

        $user->assignRole($otherRoleId);
        expect(Mage::getModel('apiplatform/user')->load($userId)->getRoleIds())->toBe([$otherRoleId]);

        $user->assignRole(0);
        expect(Mage::getModel('apiplatform/user')->load($userId)->getRoleIds())->toBe([]);

        $user->assignRole($roleId);
        Mage::getModel('apiplatform/user')->load($userId)->delete();
        $leftover = $write->fetchOne(
            $write->select()->from($roleTable, 'COUNT(*)')->where('user_id = ?', $userId),
        );
        expect((int) $leftover)->toBe(0);
    } finally {
        $write->delete($roleTable, ['user_id = ?' => $userId]);
        $write->delete($roleTable, ['role_id IN (?)' => [$roleId, $otherRoleId]]);
        $stale = Mage::getModel('apiplatform/user')->load($userId);
        if ($stale->getId()) {
            $stale->delete();
        }
    }
});

it('rejects a username or email that another api user already holds', function () {
    $suffix = substr(md5(uniqid()), 0, 8);
    $first = apiPlatformTestUser($suffix);
    $first->save();
    $firstId = (int) $first->getId();

    try {
        $second = apiPlatformTestUser($suffix . 'b')->setUsername('apiplatform_' . $suffix);
        expect(fn() => $second->save())->toThrow(Mage_Core_Exception::class);
    } finally {
        Mage::getModel('apiplatform/user')->load($firstId)->delete();
    }
});
