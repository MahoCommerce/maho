<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function makeAdminUser(array $data = []): Mage_Admin_Model_User
{
    $user = Mage::getModel('admin/user');
    $user->addData(array_merge([
        'username' => 'twofa' . uniqid(),
        'firstname' => 'Two',
        'lastname' => 'Factor',
        'email' => 'twofa.' . uniqid('', true) . '@test.com',
        'is_active' => 1,
    ], $data));
    return $user;
}

function setTwofaEnforcement(string $required, string $from = ''): void
{
    $store = Mage::app()->getStore();
    $store->setConfig(Mage_Admin_Helper_Data::XML_PATH_TWOFA_REQUIRED, $required);
    $store->setConfig(Mage_Admin_Helper_Data::XML_PATH_TWOFA_REQUIRED_FROM, $from);
}

function twofaState(Mage_Admin_Model_User $user): string
{
    return Mage::helper('admin')->getTwofaEnforcementState($user);
}

function dateOffsetByDays(int $days): string
{
    return (new DateTimeImmutable(Mage_Core_Model_Locale::todayUtc(), new DateTimeZone('UTC')))
        ->modify(sprintf('%+d days', $days))
        ->format(Mage_Core_Model_Locale::DATE_FORMAT);
}

afterEach(function () {
    setTwofaEnforcement('0', '');
});

it('reports the off state when the requirement is disabled', function () {
    setTwofaEnforcement('0', dateOffsetByDays(-30));

    expect(twofaState(makeAdminUser()))->toBe(Mage_Admin_Helper_Data::TWOFA_STATE_OFF);
});

it('reports the compliant state for a user enrolled in 2FA', function () {
    setTwofaEnforcement('1', '');
    $user = makeAdminUser(['twofa_enabled' => 1]);

    expect(twofaState($user))->toBe(Mage_Admin_Helper_Data::TWOFA_STATE_COMPLIANT);
});

it('reports the compliant state for a user with a passkey and no 2FA', function () {
    setTwofaEnforcement('1', '');
    $user = makeAdminUser([
        'passkey_credential_id_hash' => 'hash',
        'passkey_public_key' => 'key',
    ]);

    expect(twofaState($user))->toBe(Mage_Admin_Helper_Data::TWOFA_STATE_COMPLIANT);
});

it('reports the warn state before the cutover date', function () {
    setTwofaEnforcement('1', dateOffsetByDays(7));

    expect(twofaState(makeAdminUser()))->toBe(Mage_Admin_Helper_Data::TWOFA_STATE_WARN);
    expect(Mage::helper('admin')->getTwofaDaysUntilEnforcement())->toBe(7);
});

it('reports the block state on the cutover date', function () {
    setTwofaEnforcement('1', dateOffsetByDays(0));

    expect(twofaState(makeAdminUser()))->toBe(Mage_Admin_Helper_Data::TWOFA_STATE_BLOCK);
    expect(Mage::helper('admin')->getTwofaDaysUntilEnforcement())->toBe(0);
});

it('reports the block state after the cutover date', function () {
    setTwofaEnforcement('1', dateOffsetByDays(-1));

    expect(twofaState(makeAdminUser()))->toBe(Mage_Admin_Helper_Data::TWOFA_STATE_BLOCK);
});

it('reports the block state when no cutover date is set', function () {
    setTwofaEnforcement('1', '');

    expect(twofaState(makeAdminUser()))->toBe(Mage_Admin_Helper_Data::TWOFA_STATE_BLOCK);
    expect(Mage::helper('admin')->getTwofaDaysUntilEnforcement())->toBeNull();
});

it('reports the block state when the cutover date is not a valid date', function () {
    setTwofaEnforcement('1', 'not-a-date');

    expect(twofaState(makeAdminUser()))->toBe(Mage_Admin_Helper_Data::TWOFA_STATE_BLOCK);
});
