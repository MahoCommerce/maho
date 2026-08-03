<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\HealthCheck;

uses(Tests\MahoBackendTestCase::class);

/**
 * Coverage for the health check that guards the libsodium encryption key and the
 * data encrypted under it. Both fire on stores migrated from Magento/OpenMage:
 * the key check on an mcrypt key copied into local.xml, the data check on a
 * database imported against a freshly generated key.
 */

function healthCheckForeignCiphertext(string $plaintext): string
{
    $key = sodium_crypto_secretbox_keygen();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    return sodium_bin2base64($nonce . sodium_crypto_secretbox($plaintext, $nonce, $key), SODIUM_BASE64_VARIANT_ORIGINAL);
}

it('accepts a libsodium key', function () {
    expect(HealthCheck::findEncryptionKeyIssue(Mage::generateEncryptionKeyAsHex()))->toBeNull();
    expect(HealthCheck::findEncryptionKeyIssue(Mage::getEncryptionKeyAsHex()))->toBeNull();
});

it('flags a legacy Magento/OpenMage mcrypt key', function () {
    $issue = HealthCheck::findEncryptionKeyIssue(Mage::helper('core')->getRandomString(32));

    expect($issue)->toBeString()
        ->and($issue)->toContain('sys:encryptionkey:regenerate')
        ->and($issue)->toContain('mcrypt');
});

it('flags a missing key', function () {
    expect(HealthCheck::findEncryptionKeyIssue(''))->toContain('No encryption key');
});

it('flags a key of the right length that is not hex', function () {
    expect(HealthCheck::findEncryptionKeyIssue(str_repeat('z', 64)))->toBeString();
});

it('reports no undecryptable data on a healthy install', function () {
    expect(HealthCheck::findUndecryptableData())->toBe([]);
});

it('flags config values encrypted under a different key', function () {
    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $table = $resource->getTableName('core_config_data');

    $write->insert($table, [
        'scope' => 'default',
        'scope_id' => 0,
        'path' => 'system/smtp/password',
        'value' => healthCheckForeignCiphertext('hunter2'),
    ]);

    try {
        $undecryptable = HealthCheck::findUndecryptableData();
        expect($undecryptable)->toHaveCount(1)
            ->and($undecryptable[0])->toContain('system/smtp/password');
    } finally {
        $write->delete($table, ['path = ?' => 'system/smtp/password']);
    }
});

it('accepts config values encrypted under the current key', function () {
    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $table = $resource->getTableName('core_config_data');

    $write->insert($table, [
        'scope' => 'default',
        'scope_id' => 0,
        'path' => 'system/smtp/password',
        'value' => Mage::helper('core')->encrypt('hunter2'),
    ]);

    try {
        expect(HealthCheck::findUndecryptableData())->toBe([]);
    } finally {
        $write->delete($table, ['path = ?' => 'system/smtp/password']);
    }
});

it('flags admin two-factor secrets encrypted under a different key', function () {
    $username = 'healthcheck_twofa_' . uniqid();

    /** @var Mage_Admin_Model_User $user */
    $user = Mage::getModel('admin/user');
    $user->setData([
        'username' => $username,
        'firstname' => 'Health',
        'lastname' => 'Check',
        'email' => $username . '@example.test',
        'password' => 'Temporary-P4ssword!',
        'is_active' => 1,
    ])->save();

    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $write->update(
        $resource->getTableName('admin/user'),
        ['twofa_secret' => healthCheckForeignCiphertext('JBSWY3DPEHPK3PXP')],
        ['user_id = ?' => (int) $user->getId()],
    );

    try {
        $undecryptable = HealthCheck::findUndecryptableData();
        expect($undecryptable)->toHaveCount(1)
            ->and($undecryptable[0])->toContain('twofa_secret');
    } finally {
        $user->delete();
    }
});
