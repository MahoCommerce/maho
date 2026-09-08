<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function makeTwofaAdminUser(bool $enabled = false): Mage_Admin_Model_User
{
    $user = Mage::getModel('admin/user');
    $user->addData([
        'username' => 'twofa' . uniqid(),
        'firstname' => 'Two',
        'lastname' => 'Factor',
        'email' => 'twofa.' . uniqid('', true) . '@test.com',
        'is_active' => 1,
        'twofa_enabled' => $enabled ? 1 : 0,
    ]);
    $user->setTwofaSecret(Mage::helper('core/security')->generateTotpSecret());
    return $user;
}

function currentTotpCode(Mage_Admin_Model_User $user): string
{
    return \OTPHP\TOTP::createFromSecret($user->getTwofaSecret())->now();
}

it('refuses to turn 2FA on without a verification code', function () {
    $user = makeTwofaAdminUser();

    expect(fn() => $user->applyTwofaChange(true, ''))
        ->toThrow(Mage_Core_Exception::class);
    expect((bool) $user->getTwofaEnabled())->toBeFalse();
});

it('refuses to turn 2FA on with a wrong verification code', function () {
    $user = makeTwofaAdminUser();

    expect(fn() => $user->applyTwofaChange(true, '000000'))
        ->toThrow(Mage_Core_Exception::class);
    expect((bool) $user->getTwofaEnabled())->toBeFalse();
});

it('turns 2FA on with a valid verification code', function () {
    $user = makeTwofaAdminUser();

    $user->applyTwofaChange(true, currentTotpCode($user));
    expect((bool) $user->getTwofaEnabled())->toBeTrue();
});

it('does not ask for a code when 2FA is already on', function () {
    $user = makeTwofaAdminUser(true);

    $user->applyTwofaChange(true, '');
    expect((bool) $user->getTwofaEnabled())->toBeTrue();
});

it('turns 2FA off without a code', function () {
    $user = makeTwofaAdminUser(true);

    $user->applyTwofaChange(false, '');
    expect((bool) $user->getTwofaEnabled())->toBeFalse();
});
