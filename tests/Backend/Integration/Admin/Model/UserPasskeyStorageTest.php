<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

afterEach(function () {
    $this->user?->delete();
});

// Windows Hello registers an RS256 key, and WebAuthn allows a credential ID of up to 1023 bytes.
it('stores the passkey of an RS256 authenticator with the longest credential ID', function () {
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    $publicKey = openssl_pkey_get_details($key)['key'];
    $credentialId = base64_encode(random_bytes(1023));

    $suffix = substr(md5(uniqid()), 0, 8);
    $this->user = Mage::getModel('admin/user')
        ->setUsername('passkey_' . $suffix)
        ->setFirstname('Passkey')
        ->setLastname('Storage')
        ->setEmail("passkey_{$suffix}@example.com")
        ->setPassword('Pk-P4ssword-1')
        ->setIsActive(1)
        ->setPasskeyCredentialIdHash($credentialId)
        ->setPasskeyPublicKey($publicKey);
    $this->user->save();

    $stored = Mage::getModel('admin/user')->load($this->user->getId());

    expect(strlen($publicKey))->toBe(451)
        ->and($stored->getPasskeyPublicKey())->toBe($publicKey)
        ->and($stored->getPasskeyCredentialIdHash())->toBe($credentialId);
});
