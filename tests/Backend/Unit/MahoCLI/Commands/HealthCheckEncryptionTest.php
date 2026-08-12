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

/**
 * Run $assert with $path holding $value, restoring whatever was there before.
 * (scope, scope_id, path) is unique, so an install that already configures the
 * path must be updated rather than inserted into.
 */
function healthCheckWithConfigValue(string $path, string $value, callable $assert): void
{
    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $table = $resource->getTableName('core_config_data');

    $existing = $write->fetchRow(
        $write->select()
            ->from($table, ['config_id', 'value'])
            ->where('scope = ?', 'default')
            ->where('scope_id = ?', 0)
            ->where('path = ?', $path),
    );

    if ($existing) {
        $configId = (int) $existing['config_id'];
        $write->update($table, ['value' => $value], ['config_id = ?' => $configId]);
    } else {
        $write->insert($table, ['scope' => 'default', 'scope_id' => 0, 'path' => $path, 'value' => $value]);
        $configId = (int) $write->lastInsertId($table);
    }

    try {
        $assert(sprintf('%s #%d (%s)', $table, $configId, $path));
    } finally {
        if ($existing) {
            $write->update($table, ['value' => $existing['value']], ['config_id = ?' => $configId]);
        } else {
            $write->delete($table, ['config_id = ?' => $configId]);
        }
    }
}

it('proves encryption and credential hashing work on a healthy install', function () {
    expect(HealthCheck::findEncryptionFailure())->toBeNull();
});

it('accepts a libsodium key', function () {
    expect(HealthCheck::findEncryptionKeyIssue(Mage::generateEncryptionKeyAsHex()))->toBeNull();
    expect(HealthCheck::findEncryptionKeyIssue(Mage::getEncryptionKeyAsHex()))->toBeNull();
});

it('flags a legacy Magento/OpenMage mcrypt key', function (int $length) {
    $issue = HealthCheck::findEncryptionKeyIssue(Mage::helper('core')->getRandomString($length));

    expect($issue)->toBeString()
        ->and($issue)->toContain('sys:encryptionkey:regenerate')
        ->and($issue)->toContain('mcrypt');
})->with([
    'default md5 key' => 32,
    'custom shorter key' => 16,
    'custom longer key' => 56,
]);

it('flags a missing key', function () {
    expect(HealthCheck::findEncryptionKeyIssue(''))->toContain('No encryption key');
});

it('flags a key of the right length that is not hex', function () {
    $issue = HealthCheck::findEncryptionKeyIssue(str_repeat('z', Mage_Core_Model_Encryption::KEY_LENGTH_HEX));

    expect($issue)->toContain('not hexadecimal');
});

it('reports no undecryptable data on a healthy install', function () {
    expect(HealthCheck::findUndecryptableData())->toBe([]);
});

it('flags config values encrypted under a different key', function () {
    healthCheckWithConfigValue(
        'system/smtp/password',
        healthCheckForeignCiphertext('hunter2'),
        function (string $expectedFailure) {
            expect(HealthCheck::findUndecryptableData())->toContain($expectedFailure);
        },
    );
});

it('accepts config values encrypted under the current key', function () {
    healthCheckWithConfigValue(
        'system/smtp/password',
        Mage::helper('core')->encrypt('hunter2'),
        function (string $failureIfBroken) {
            expect(HealthCheck::findUndecryptableData())->not->toContain($failureIfBroken);
        },
    );
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
    $table = $resource->getTableName('admin/user');
    $write->update(
        $table,
        ['twofa_secret' => healthCheckForeignCiphertext('JBSWY3DPEHPK3PXP')],
        ['user_id = ?' => (int) $user->getId()],
    );

    try {
        expect(HealthCheck::findUndecryptableData())
            ->toContain(sprintf('%s #%d (twofa_secret)', $table, (int) $user->getId()));
    } finally {
        $user->delete();
    }
});
