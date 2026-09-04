<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

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

beforeEach(function () {
    $suffix = substr(md5(uniqid()), 0, 8);
    $this->password = 'Lbu-P4ssword-1';

    /** @var Mage_Admin_Model_User $user */
    $user = Mage::getModel('admin/user');
    $user->setUsername('lbu_' . $suffix)
        ->setFirstname('Load')
        ->setLastname('ByUsername')
        ->setEmail("lbu_{$suffix}@example.com")
        ->setPassword($this->password)
        ->setIsActive(1)
        ->save();

    $this->user = $user;
    $this->userId = (int) $user->getId();
    $this->username = (string) $user->getUsername();
});

afterEach(function () {
    $this->user->delete();
});

it('decodes extra the way load() does', function () {
    $this->user->saveExtra(['configState' => ['catalog_frontend' => 1]]);

    $extra = Mage::getModel('admin/user')->loadByUsername($this->username)->getExtra();

    expect($extra)->toBeArray()
        ->and($extra['configState']['catalog_frontend'] ?? false)->toBe(1);
});

// admin:user:enable, admin:user:disable and admin:user:twofa-reset all save a user loaded this way.
it('keeps the stored password when a user loaded by username is saved', function () {
    $storedHash = adminLoadByUsernameStoredColumn($this->userId, 'password');

    Mage::getModel('admin/user')->loadByUsername($this->username)->setIsActive(0)->save();

    $afterSave = adminLoadByUsernameStoredColumn($this->userId, 'password');
    expect($afterSave)->toBe($storedHash)
        ->and(Mage::helper('core')->validateHash($this->password, $afterSave))->toBeTrue()
        ->and(adminLoadByUsernameStoredColumn($this->userId, 'is_active'))->toBe('0');
});

it('does not add a json layer to extra when a user loaded by username is saved', function () {
    $this->user->saveExtra(['configState' => ['catalog_frontend' => 1]]);
    $storedExtra = adminLoadByUsernameStoredColumn($this->userId, 'extra');

    Mage::getModel('admin/user')->loadByUsername($this->username)->setIsActive(0)->save();

    expect(adminLoadByUsernameStoredColumn($this->userId, 'extra'))->toBe($storedExtra);
});

// A collection hydrates the model without the resource _afterLoad(), so extra stays a JSON string.
it('does not add a json layer to extra when a collection-loaded user is saved', function () {
    $this->user->saveExtra(['configState' => ['catalog_frontend' => 1]]);
    $storedExtra = adminLoadByUsernameStoredColumn($this->userId, 'extra');

    $collection = Mage::getResourceModel('admin/user_collection')->addFieldToFilter('user_id', $this->userId);
    foreach ($collection as $user) {
        $user->setIsActive(0)->save();
    }

    expect(adminLoadByUsernameStoredColumn($this->userId, 'extra'))->toBe($storedExtra);
});

it('repairs extra that an earlier save encoded twice', function () {
    $twiceEncoded = Mage::helper('core')->jsonEncode(
        Mage::helper('core')->jsonEncode(['configState' => ['catalog_frontend' => 1]]),
    );
    $resource = Mage::getSingleton('core/resource');
    $resource->getConnection('core_write')->update(
        $resource->getTableName('admin/user'),
        ['extra' => $twiceEncoded],
        ['user_id = ?' => $this->userId],
    );

    $extra = Mage::getModel('admin/user')->load($this->userId)->getExtra();

    expect($extra)->toBeArray()
        ->and($extra['configState']['catalog_frontend'] ?? false)->toBe(1);
});

// MySQL drops NUL bytes from a varchar value, so "name\0" would otherwise resolve to "name".
it('does not load a user when the username carries a NUL byte', function () {
    $user = Mage::getModel('admin/user')->loadByUsername($this->username . "\0");

    expect($user->getId())->toBeNull();
});
