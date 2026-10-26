<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

declare(strict_types=1);

use Tests\Helpers\AdminRoleFixture;

uses(Tests\MahoBackendTestCase::class);

/**
 * Payload shaped like the one the admin login page posts for a passkey assertion: the three
 * fields are base64 strings and the authenticator data carries the user verified flag.
 */
function passkeyAssertionPayload(string $signature = 'signature'): string
{
    return (string) json_encode([
        'clientDataJSON' => base64_encode('{"type":"webauthn.get"}'),
        'authenticatorData' => base64_encode(str_repeat("\x00", 32) . chr(0x05) . pack('N', 0)),
        'signature' => base64_encode($signature),
    ]);
}

beforeEach(function () {
    $this->user = AdminRoleFixture::login(['admin/system/myaccount']);
    $this->user->setPasskeyCredentialIdHash(base64_encode(random_bytes(32)))
        ->setPasskeyPublicKey("-----BEGIN PUBLIC KEY-----\nstub\n-----END PUBLIC KEY-----\n")
        ->setPasswordEnabled(false)
        ->save();
    $this->session = Mage::getSingleton('admin/session');
});

afterEach(function () {
    AdminRoleFixture::delete($this->user);
});

// The login page calls prelogin first and submits the form afterwards, and the passkey
// challenge is single use: the second request cannot verify the same assertion again.
it('accepts the login request that follows a verified prelogin assertion, once', function () {
    $payload = passkeyAssertionPayload();
    $this->session->setPasskeyPreloginVerified((int) $this->user->getId(), hash('sha256', $payload));

    $user = Mage::getModel('admin/user')->load($this->user->getId());
    expect($user->authenticate($this->user->getUsername(), $payload))->toBeTrue();

    $user = Mage::getModel('admin/user')->load($this->user->getId());
    expect(fn() => $user->authenticate($this->user->getUsername(), $payload))
        ->toThrow(Mage_Core_Exception::class);
});

it('does not accept the prelogin verification for a different assertion', function () {
    $this->session->setPasskeyPreloginVerified(
        (int) $this->user->getId(),
        hash('sha256', passkeyAssertionPayload('first')),
    );

    $user = Mage::getModel('admin/user')->load($this->user->getId());
    expect(fn() => $user->authenticate($this->user->getUsername(), passkeyAssertionPayload('second')))
        ->toThrow(Mage_Core_Exception::class);
});

it('does not accept a prelogin verification older than the time window', function () {
    $payload = passkeyAssertionPayload();
    $this->session->setData('passkey_prelogin_verified', [
        'user_id' => (int) $this->user->getId(),
        'assertion_hash' => hash('sha256', $payload),
        'at' => time() - 3600,
    ]);

    $user = Mage::getModel('admin/user')->load($this->user->getId());
    expect(fn() => $user->authenticate($this->user->getUsername(), $payload))
        ->toThrow(Mage_Core_Exception::class);
});

it('rejects a passkey payload without a challenge as a failed login', function () {
    $user = Mage::getModel('admin/user')->load($this->user->getId());

    expect(fn() => $user->authenticate($this->user->getUsername(), passkeyAssertionPayload()))
        ->toThrow(Mage_Core_Exception::class);
});

it('forgets the prelogin verification when a new passkey login starts', function () {
    $this->session->setPasskeyPreloginVerified((int) $this->user->getId(), hash('sha256', 'stale'));

    $user = Mage::getModel('admin/user')->load($this->user->getId());
    $user->getPasskeyGetArgs();

    expect($this->session->getPasskeyPreloginVerified())->toBeNull()
        ->and($this->session->getPasskeyChallenge())->not->toBeNull();
});
