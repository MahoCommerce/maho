<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

class TimingAdminUser extends Mage_Admin_Model_User
{
    public static int $passwordChecks = 0;

    #[\Override]
    public function validatePasswordHash(#[\SensitiveParameter] string $string1, string $string2): bool
    {
        self::$passwordChecks++;
        return parent::validatePasswordHash($string1, $string2);
    }
}

beforeEach(function () {
    TimingAdminUser::$passwordChecks = 0;
    $this->user = AdminRoleFixture::login(['admin/system/myaccount']);
});

afterEach(function () {
    AdminRoleFixture::delete($this->user);
});

it('checks a password hash once for an unknown username', function () {
    $user = new TimingAdminUser();

    expect(fn() => $user->authenticate('missing-' . uniqid(), 'wrong-password'))
        ->toThrow(Mage_Core_Exception::class)
        ->and(TimingAdminUser::$passwordChecks)->toBe(1);
});

it('checks a password hash once for a known username with a wrong password', function () {
    $user = new TimingAdminUser();

    expect(fn() => $user->authenticate($this->user->getUsername(), 'wrong-password'))
        ->toThrow(Mage_Core_Exception::class)
        ->and(TimingAdminUser::$passwordChecks)->toBe(1);
});

it('performs the same expensive check after a failed legacy hash', function () {
    Mage::getSingleton('core/resource')->getConnection('core_write')->update(
        Mage::getSingleton('core/resource')->getTableName('admin/user'),
        ['password' => hash('sha256', 'correct-password')],
        ['user_id = ?' => $this->user->getId()],
    );
    $user = new TimingAdminUser();

    expect(fn() => $user->authenticate($this->user->getUsername(), 'wrong-password'))
        ->toThrow(Mage_Core_Exception::class)
        ->and(TimingAdminUser::$passwordChecks)->toBe(2);
});

it('performs a dummy password check for an account without password login', function () {
    $this->user
        ->setPasskeyCredentialIdHash('credential-id')
        ->setPasskeyPublicKey('public-key')
        ->setPasswordEnabled(false)
        ->save();
    $user = new TimingAdminUser();

    expect(fn() => $user->authenticate($this->user->getUsername(), 'not-a-passkey-payload'))
        ->toThrow(Mage_Core_Exception::class)
        ->and(TimingAdminUser::$passwordChecks)->toBe(1);
});
