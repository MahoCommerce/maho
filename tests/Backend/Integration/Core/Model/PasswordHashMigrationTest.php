<?php

/**
 * Migration of Magento 1 and OpenMage era password hashes to the canonical bcrypt format.
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
    'openmage salted sha256',
    'openmage salted sha512',
    'bcrypt with outdated cost',
]);

function passwordMigrationLegacyHash(string $format, string $password): string
{
    return match ($format) {
        // M1 generated 2-character salts for customers and admins
        'm1 unsalted md5' => md5($password),
        'm1 salted md5' => md5('xY' . $password) . ':xY',
        'openmage salted sha256' => hash('sha256', 'RandomSalt123' . $password) . ':RandomSalt123',
        'openmage salted sha512' => hash('sha512', 'RandomSalt123' . $password) . ':RandomSalt123',
        // cost 4 is below every PHP default, so this hash needs a cost upgrade on any version
        'bcrypt with outdated cost' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]),
    };
}

function passwordMigrationCanonicalHash(string $password): string
{
    return Mage::helper('core')->getHashPassword($password);
}

function passwordMigrationExpectCanonical(string $hash, string $password): void
{
    expect(password_verify($password, $hash))->toBeTrue()
        ->and(Mage::helper('core')->hashNeedsUpgrade($hash))->toBeFalse();
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

    passwordMigrationOverwriteHash('admin/user', 'password', 'user_id', (int) $user->getId(), $passwordHash);

    return [$user, $role];
}

function passwordMigrationCreateApiUser(string $apiKeyHash): Mage_Api_Model_User
{
    $username = 'legacy_api_' . uniqid();

    /** @var Mage_Api_Model_User $user */
    $user = Mage::getModel('api/user');
    $user->setData([
        'username' => $username,
        'firstname' => 'Legacy',
        'lastname' => 'Hash',
        'email' => $username . '@example.test',
        'api_key' => 'Temporary-4pi-Key',
        'is_active' => 1,
    ])->save();

    passwordMigrationOverwriteHash('api/user', 'api_key', 'user_id', (int) $user->getId(), $apiKeyHash);

    return $user;
}

/** Saving through the model would re-hash, so write the legacy hash straight to the column */
function passwordMigrationOverwriteHash(string $table, string $column, string $idColumn, int $id, string $hash): void
{
    Mage::getSingleton('core/resource')->getConnection('core_write')->update(
        Mage::getSingleton('core/resource')->getTableName($table),
        [$column => $hash],
        [$idColumn . ' = ?' => $id],
    );
}

describe('Legacy hash validation', function () {
    test('accepts every historical hash format and rejects wrong passwords', function (string $format) {
        $password = 'Legacy-P@ssw0rd-1';
        $hash = passwordMigrationLegacyHash($format, $password);
        $helper = Mage::helper('core');

        expect($helper->validateHash($password, $hash))->toBeTrue()
            ->and($helper->validateHash('wrong-password', $hash))->toBeFalse();
    })->with('legacy password hash formats');

    test('getHashPassword produces the canonical bcrypt format', function () {
        $password = 'Current-P@ssw0rd-1';
        $hash = passwordMigrationCanonicalHash($password);

        passwordMigrationExpectCanonical($hash, $password);
        expect(Mage::helper('core')->validateHash($password, $hash))->toBeTrue();
    });

    test('flags every legacy format as needing an upgrade', function (string $format) {
        $hash = passwordMigrationLegacyHash($format, 'Legacy-P@ssw0rd-1');

        expect(Mage::helper('core')->hashNeedsUpgrade($hash))->toBeTrue();
    })->with('legacy password hash formats');

    // A bcrypt hash minted elsewhere at the current default cost is already canonical:
    // accept it and leave the stored hash alone.
    test('accepts a foreign bcrypt hash at the current default cost without re-hashing it', function () {
        $password = 'Legacy-P@ssw0rd-1';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $helper = Mage::helper('core');

        expect($helper->validateHash($password, $hash))->toBeTrue()
            ->and($helper->validateHash('wrong-password', $hash))->toBeFalse()
            ->and($helper->hashNeedsUpgrade($hash))->toBeFalse();
    });

    // Imported argon2 credentials already verify through password_verify(), so leave them
    // alone instead of re-hashing them down to bcrypt on the owner's next login.
    test('accepts an argon2 hash without re-hashing it', function () {
        $password = 'Legacy-P@ssw0rd-1';
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        $helper = Mage::helper('core');

        expect($helper->validateHash($password, $hash))->toBeTrue()
            ->and($helper->hashNeedsUpgrade($hash))->toBeFalse();
    })->skip(!defined('PASSWORD_ARGON2ID'), 'PHP built without argon2 support');

    test('flags an empty stored hash as needing an upgrade', function () {
        expect(Mage::helper('core')->hashNeedsUpgrade(''))->toBeTrue();
    });
});

describe('Customer password migration on login', function () {
    test('stores new passwords as bcrypt', function () {
        $password = 'Customer-P@ss-42';
        $customer = passwordMigrationCreateCustomer(passwordMigrationCanonicalHash('irrelevant'));

        try {
            $customer->setPassword($password)->save();
            passwordMigrationExpectCanonical((string) $customer->getPasswordHash(), $password);
        } finally {
            $customer->delete();
        }
    });

    test('upgrades legacy hash to canonical bcrypt', function (string $format) {
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

    // A transparent re-hash is not a password change: bumping password_created_at would
    // invalidate the customer's sessions on every other device (see Mage_Core_Model_Session_Abstract).
    test('preserves password_created_at while upgrading the hash', function () {
        $password = 'Customer-P@ss-42';
        $passwordCreatedAt = 1700000000;
        $customer = passwordMigrationCreateCustomer(passwordMigrationLegacyHash('m1 salted md5', $password));

        try {
            $customer->setPasswordCreatedAt($passwordCreatedAt);
            $customer->getResource()->saveAttribute($customer, 'password_created_at');

            $authenticated = Mage::getModel('customer/customer')
                ->setWebsiteId(Mage::app()->getWebsite(true)->getId());
            expect($authenticated->authenticate($customer->getEmail(), $password))->toBeTrue();

            $reloaded = Mage::getModel('customer/customer')->load($customer->getId());
            passwordMigrationExpectCanonical((string) $reloaded->getPasswordHash(), $password);
            expect((int) $reloaded->getPasswordCreatedAt())->toBe($passwordCreatedAt);
        } finally {
            $customer->delete();
        }
    });

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
    test('stores new passwords as bcrypt', function () {
        $password = 'Admin-P@ss-42';
        [$user, $role] = passwordMigrationCreateAdmin(passwordMigrationCanonicalHash('irrelevant'));

        try {
            $user->setNewPassword($password)->save();

            $reloaded = Mage::getModel('admin/user')->load($user->getId());
            passwordMigrationExpectCanonical((string) $reloaded->getPassword(), $password);
        } finally {
            $user->delete();
            $role->delete();
        }
    });

    test('upgrades legacy hash to canonical bcrypt', function (string $format) {
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

describe('API key migration on login', function () {
    test('stores new api keys as bcrypt', function () {
        $apiKey = 'Api-K3y-42';
        $user = passwordMigrationCreateApiUser(passwordMigrationCanonicalHash('irrelevant'));

        try {
            $user->setNewApiKey($apiKey)->save();

            $reloaded = Mage::getModel('api/user')->load($user->getId());
            passwordMigrationExpectCanonical((string) $reloaded->getApiKey(), $apiKey);
        } finally {
            $user->delete();
        }
    });

    test('upgrades legacy hash to canonical bcrypt', function (string $format) {
        $apiKey = 'Api-K3y-42';
        $legacyHash = passwordMigrationLegacyHash($format, $apiKey);
        $user = passwordMigrationCreateApiUser($legacyHash);

        try {
            Mage::getModel('api/user')->setSessid(uniqid())->login($user->getUsername(), $apiKey);

            $reloaded = Mage::getModel('api/user')->load($user->getId());
            $newHash = (string) $reloaded->getApiKey();

            expect($newHash)->not->toBe($legacyHash);
            passwordMigrationExpectCanonical($newHash, $apiKey);
        } finally {
            $user->delete();
        }
    })->with('legacy password hash formats');

    test('leaves an already canonical hash untouched', function () {
        $apiKey = 'Api-K3y-42';
        $canonicalHash = passwordMigrationCanonicalHash($apiKey);
        $user = passwordMigrationCreateApiUser($canonicalHash);

        try {
            Mage::getModel('api/user')->setSessid(uniqid())->login($user->getUsername(), $apiKey);

            $reloaded = Mage::getModel('api/user')->load($user->getId());
            expect((string) $reloaded->getApiKey())->toBe($canonicalHash);
        } finally {
            $user->delete();
        }
    });
});
