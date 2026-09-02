<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function adminLoadByUsernameCreateUser(string $password): Mage_Admin_Model_User
{
    $suffix = substr(md5(uniqid()), 0, 8);

    /** @var Mage_Admin_Model_User $user */
    $user = Mage::getModel('admin/user');
    $user->setUsername('lbu_' . $suffix)
        ->setFirstname('Load')
        ->setLastname('ByUsername')
        ->setEmail("lbu_{$suffix}@example.com")
        ->setPassword($password)
        ->setIsActive(1)
        ->save();

    return $user;
}

function adminLoadByUsernameStoredColumn(int $userId, string $column): string
{
    $resource = Mage::getSingleton('core/resource');
    $read = $resource->getConnection('core_read');

    return (string) $read->fetchOne(
        $read->select()
            ->from($resource->getTableName('admin/user'), $column)
            ->where('user_id = ?', $userId),
    );
}

it('decodes extra the way load() does', function () {
    $user = adminLoadByUsernameCreateUser('Lbu-P4ssword-1');
    $userId = (int) $user->getId();

    try {
        $user->saveExtra(['configState' => ['catalog_frontend' => 1]]);

        $extra = Mage::getModel('admin/user')->loadByUsername($user->getUsername())->getExtra();

        expect($extra)->toBeArray()
            ->and($extra['configState']['catalog_frontend'] ?? false)->toBe(1);
    } finally {
        Mage::getModel('admin/user')->load($userId)->delete();
    }
});

// admin:user:enable, admin:user:disable and admin:user:twofa-reset all save a user loaded this way.
it('keeps the stored password when a user loaded by username is saved', function () {
    $password = 'Lbu-P4ssword-1';
    $user = adminLoadByUsernameCreateUser($password);
    $userId = (int) $user->getId();
    $username = (string) $user->getUsername();

    try {
        $storedHash = adminLoadByUsernameStoredColumn($userId, 'password');

        Mage::getModel('admin/user')->loadByUsername($username)->setIsActive(0)->save();

        $afterSave = adminLoadByUsernameStoredColumn($userId, 'password');
        expect($afterSave)->toBe($storedHash)
            ->and(Mage::helper('core')->validateHash($password, $afterSave))->toBeTrue()
            ->and((int) Mage::getModel('admin/user')->load($userId)->getIsActive())->toBe(0);
    } finally {
        Mage::getModel('admin/user')->load($userId)->delete();
    }
});

it('does not add a json layer to extra when a user loaded by username is saved', function () {
    $user = adminLoadByUsernameCreateUser('Lbu-P4ssword-1');
    $userId = (int) $user->getId();
    $username = (string) $user->getUsername();

    try {
        $user->saveExtra(['configState' => ['catalog_frontend' => 1]]);
        $storedExtra = adminLoadByUsernameStoredColumn($userId, 'extra');

        foreach ([1, 0, 1] as $isActive) {
            Mage::getModel('admin/user')->loadByUsername($username)->setIsActive($isActive)->save();
        }

        expect(adminLoadByUsernameStoredColumn($userId, 'extra'))->toBe($storedExtra);
    } finally {
        Mage::getModel('admin/user')->load($userId)->delete();
    }
});
