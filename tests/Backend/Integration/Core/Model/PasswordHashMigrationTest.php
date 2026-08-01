<?php

/**
 * Migration of Magento 1 and OpenMage era password hashes to the canonical salted SHA256 format.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

dataset('legacy password hash formats', [
    'm1 unsalted md5',
    'm1 salted md5',
    'openmage salted sha512',
    'openmage bcrypt',
]);

function passwordMigrationLegacyHash(string $format, string $password): string
{
    return match ($format) {
        // M1 generated 2-character salts for customers and admins
        'm1 unsalted md5' => md5($password),
        'm1 salted md5' => md5('xY' . $password) . ':xY',
        'openmage salted sha512' => hash('sha512', 'RandomSalt123' . $password) . ':RandomSalt123',
        'openmage bcrypt' => password_hash($password, PASSWORD_DEFAULT),
    };
}

function passwordMigrationCanonicalHash(string $password): string
{
    return Mage::helper('core')->getHash($password, Mage_Admin_Model_User::HASH_SALT_LENGTH);
}

function passwordMigrationExpectCanonical(string $hash, string $password): void
{
    expect($hash)->toContain(':');
    [$digest, $salt] = explode(':', $hash, 2);
    expect($digest)->toBe(hash('sha256', $salt . $password));
}

function passwordMigrationCreateCustomer(string $passwordHash): Mage_Customer_Model_Customer
{
    /** @var Mage_Customer_Model_Customer $customer */
    $customer = Mage::getModel('customer/customer');
    $customer->setWebsiteId(Mage::app()->getWebsite(true)->getId())
        ->setGroupId(1)
        ->setFirstname('Legacy')
        ->setLastname('Hash')
        ->setEmail('legacy-hash-' . uniqid() . '@example.test')
        ->setPasswordHash($passwordHash)
        ->setForceConfirmed(true)
        ->save();
    return $customer;
}

/** @return array{0: Mage_Admin_Model_User, 1: Mage_Admin_Model_Role} */
function passwordMigrationCreateAdmin(string $passwordHash): array
{
    $username = 'legacy_hash_' . uniqid();

    /** @var Mage_Admin_Model_Role $role */
    $role = Mage::getModel('admin/role');
    $role->setData([
        'role_name' => $username . '_role',
        'role_type' => Mage_Admin_Model_Acl::ROLE_TYPE_GROUP,
        'parent_id' => 0,
    ])->save();

    /** @var Mage_Admin_Model_User $user */
    $user = Mage::getModel('admin/user');
    $user->setData([
        'username' => $username,
        'firstname' => 'Legacy',
        'lastname' => 'Hash',
        'email' => $username . '@example.test',
        'password' => 'Temporary-P4ssword!',
        'is_active' => 1,
    ])->save();

    Mage::getModel('admin/user')
        ->setRoleId($role->getId())
        ->setUserId($user->getId())
        ->add();

    // Overwrite directly: saving through the model would re-hash the password
    Mage::getSingleton('core/resource')->getConnection('core_write')->update(
        Mage::getSingleton('core/resource')->getTableName('admin/user'),
        ['password' => $passwordHash],
        ['user_id = ?' => (int) $user->getId()],
    );

    return [$user, $role];
}

describe('Legacy hash validation', function () {
    test('accepts every historical hash format and rejects wrong passwords', function (string $format) {
        $password = 'Legacy-P@ssw0rd-1';
        $hash = passwordMigrationLegacyHash($format, $password);
        $helper = Mage::helper('core');

        expect($helper->validateHash($password, $hash))->toBeTrue()
            ->and($helper->validateHash('wrong-password', $hash))->toBeFalse();
    })->with('legacy password hash formats');

    test('getHash produces the canonical salted sha256 format', function () {
        $password = 'Current-P@ssw0rd-1';
        $hash = passwordMigrationCanonicalHash($password);

        passwordMigrationExpectCanonical($hash, $password);
        expect(Mage::helper('core')->validateHash($password, $hash))->toBeTrue();
    });
});

describe('Customer password migration on login', function () {
    test('upgrades legacy hash to canonical salted sha256', function (string $format) {
        $password = 'Customer-P@ss-42';
        $legacyHash = passwordMigrationLegacyHash($format, $password);
        $customer = passwordMigrationCreateCustomer($legacyHash);

        try {
            $authenticated = Mage::getModel('customer/customer')
                ->setWebsiteId(Mage::app()->getWebsite(true)->getId());
            expect($authenticated->authenticate($customer->getEmail(), $password))->toBeTrue();

            $reloaded = Mage::getModel('customer/customer')->load($customer->getId());
            $newHash = (string) $reloaded->getPasswordHash();

            expect($newHash)->not->toBe($legacyHash);
            passwordMigrationExpectCanonical($newHash, $password);
            expect($reloaded->validatePassword($password))->toBeTrue();
        } finally {
            $customer->delete();
        }
    })->with('legacy password hash formats');

    test('leaves an already canonical hash untouched', function () {
        $password = 'Customer-P@ss-42';
        $canonicalHash = passwordMigrationCanonicalHash($password);
        $customer = passwordMigrationCreateCustomer($canonicalHash);

        try {
            $authenticated = Mage::getModel('customer/customer')
                ->setWebsiteId(Mage::app()->getWebsite(true)->getId());
            expect($authenticated->authenticate($customer->getEmail(), $password))->toBeTrue();

            $reloaded = Mage::getModel('customer/customer')->load($customer->getId());
            expect((string) $reloaded->getPasswordHash())->toBe($canonicalHash);
        } finally {
            $customer->delete();
        }
    });
});

describe('Admin password migration on login', function () {
    test('upgrades legacy hash to canonical salted sha256', function (string $format) {
        $password = 'Admin-P@ss-42';
        $legacyHash = passwordMigrationLegacyHash($format, $password);
        [$user, $role] = passwordMigrationCreateAdmin($legacyHash);

        try {
            expect(Mage::getModel('admin/user')->authenticate($user->getUsername(), $password))->toBeTrue();

            $reloaded = Mage::getModel('admin/user')->load($user->getId());
            $newHash = (string) $reloaded->getPassword();

            expect($newHash)->not->toBe($legacyHash);
            passwordMigrationExpectCanonical($newHash, $password);
            expect($reloaded->validatePasswordHash($password, $newHash))->toBeTrue();
        } finally {
            $user->delete();
            $role->delete();
        }
    })->with('legacy password hash formats');

    test('leaves an already canonical hash untouched', function () {
        $password = 'Admin-P@ss-42';
        $canonicalHash = passwordMigrationCanonicalHash($password);
        [$user, $role] = passwordMigrationCreateAdmin($canonicalHash);

        try {
            expect(Mage::getModel('admin/user')->authenticate($user->getUsername(), $password))->toBeTrue();

            $reloaded = Mage::getModel('admin/user')->load($user->getId());
            expect((string) $reloaded->getPassword())->toBe($canonicalHash);
        } finally {
            $user->delete();
            $role->delete();
        }
    });
});
