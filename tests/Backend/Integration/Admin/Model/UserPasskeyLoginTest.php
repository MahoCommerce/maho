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
 * Sign an assertion for the passkey challenge in the admin session, in the shape that the admin
 * login page posts. The flag 0x01 says that the user was present, but not verified.
 */
function signedPasskeyAssertion(OpenSSLAsymmetricKey $key, int $flags = 0x01): string
{
    $host = (string) parse_url(Mage::getBaseUrl(), PHP_URL_HOST);
    $challenge = (string) Mage::getSingleton('admin/session')->getPasskeyChallenge();
    $clientDataJSON = (string) json_encode([
        'type' => 'webauthn.get',
        'challenge' => rtrim(strtr(base64_encode($challenge), '+/', '-_'), '='),
        'origin' => 'https://' . $host,
    ]);
    $authenticatorData = hash('sha256', $host, true) . chr($flags) . pack('N', 1);
    openssl_sign($authenticatorData . hash('sha256', $clientDataJSON, true), $signature, $key, OPENSSL_ALGO_SHA256);

    return (string) json_encode([
        'clientDataJSON' => base64_encode($clientDataJSON),
        'authenticatorData' => base64_encode($authenticatorData),
        'signature' => base64_encode($signature),
    ]);
}

beforeEach(function () {
    $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    $this->user = AdminRoleFixture::login(['admin/system/myaccount']);
    $this->user->setPasskeyCredentialIdHash(base64_encode(random_bytes(32)))
        ->setPasskeyPublicKey(openssl_pkey_get_details($this->key)['key'])
        ->setPasswordEnabled(false)
        ->save();
    $this->session = Mage::getSingleton('admin/session');
    $this->user->getPasskeyGetArgs();
});

afterEach(function () {
    AdminRoleFixture::delete($this->user);
});

// The login page calls prelogin first, then submits the form with the same assertion.
it('signs in with the assertion that prelogin already checked', function () {
    $payload = signedPasskeyAssertion($this->key);

    $this->session->prelogin($this->user->getUsername(), $payload);

    expect(Mage::getModel('admin/user')->authenticate($this->user->getUsername(), $payload))->toBeTrue();
});

it('accepts the assertion for one sign-in only', function () {
    $payload = signedPasskeyAssertion($this->key);
    $this->session->prelogin($this->user->getUsername(), $payload);
    Mage::getModel('admin/user')->authenticate($this->user->getUsername(), $payload);

    expect(fn() => Mage::getModel('admin/user')->authenticate($this->user->getUsername(), $payload))
        ->toThrow(Mage_Core_Exception::class);
});

// The user can take any time to type the code, because the challenge stays in the session.
it('asks for the 2FA code at prelogin and signs in with the code afterwards', function () {
    $secret = Mage::helper('core/security')->generateTotpSecret();
    $this->user->setTwofaSecret($secret)->setTwofaEnabled(true)->save();
    $payload = signedPasskeyAssertion($this->key);

    $this->session->prelogin($this->user->getUsername(), $payload);
    expect($this->session->getRequireTwofa())->toBeTrue();

    $code = \OTPHP\TOTP::createFromSecret($secret)->now();
    expect(Mage::getModel('admin/user')->authenticate($this->user->getUsername(), $payload, $code))->toBeTrue();
});

it('rejects a passkey payload without a challenge as a failed login', function () {
    $payload = signedPasskeyAssertion($this->key);
    $this->session->unsPasskeyChallenge();

    expect(fn() => Mage::getModel('admin/user')->authenticate($this->user->getUsername(), $payload))
        ->toThrow(Mage_Core_Exception::class);
});
