<?php

/**
 * Migration of Magento 1 mcrypt-encrypted (Blowfish/ECB) data to sodium during key regeneration.
 *
 * phpseclib/mcrypt_compat is deliberately not a dependency of this repository: it is
 * installed into a throwaway directory for the duration of this file and the mcrypt
 * round trip runs in a PHP subprocess, so the mcrypt_* shims never load into the main
 * test process and cannot influence any other test.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\SysEncryptionKeyRegenerate;

uses(Tests\MahoBackendTestCase::class);

function mcryptCompatTmpDir(): string
{
    static $dir = null;
    return $dir ??= sys_get_temp_dir() . '/maho-mcrypt-compat-test-' . getmypid();
}

/** @return string|null Error message when the ephemeral install is unavailable, null on success */
function mcryptCompatEnsureInstalled(): ?string
{
    static $error = false;
    if ($error !== false) {
        return $error;
    }

    $tmp = mcryptCompatTmpDir();
    if (is_file($tmp . '/vendor/autoload.php')) {
        return $error = null;
    }

    exec('composer --version 2>/dev/null', $out, $code);
    if ($code !== 0) {
        return $error = 'composer binary not available';
    }

    if (!is_dir($tmp)) {
        mkdir($tmp, 0755, true);
    }
    $cmd = sprintf(
        'composer require phpseclib/mcrypt_compat:^2.0 --no-interaction --no-progress --no-audit --no-plugins --working-dir=%s 2>&1',
        escapeshellarg($tmp),
    );
    exec($cmd, $output, $code);
    if ($code !== 0 || !is_file($tmp . '/vendor/autoload.php')) {
        return $error = 'ephemeral phpseclib/mcrypt_compat install failed: ' . implode("\n", $output);
    }

    return $error = null;
}

function mcryptCompatCommandWithKeys(string $oldKey, bool $isM1, string $newKey): SysEncryptionKeyRegenerate
{
    $command = new SysEncryptionKeyRegenerate();
    $reflection = new ReflectionClass($command);
    $reflection->getProperty('oldEncryptionKey')->setValue($command, $oldKey);
    $reflection->getProperty('isOldEncryptionKeyM1')->setValue($command, $isM1);
    $reflection->getProperty('newEncryptionKey')->setValue($command, $newKey);
    return $command;
}

afterAll(function (): void {
    $tmp = mcryptCompatTmpDir();
    if (is_dir($tmp)) {
        exec('rm -rf ' . escapeshellarg($tmp));
    }
});

test('M1 blowfish-encrypted data decrypts and re-encrypts to sodium', function () {
    if (($installError = mcryptCompatEnsureInstalled()) !== null) {
        $this->fail($installError);
    }

    $script = <<<'PHP'
    <?php
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    require '%PROJECT_VENDOR%/autoload.php';
    require '%TMP_VENDOR%/autoload.php';

    $plaintext = 'legacy-payment-gateway-secret-42';
    $m1Key = md5('m1-era-encryption-key'); // M1 keys were md5 hashes: 32 hex chars

    // Encrypt exactly as M1's Varien_Crypt_Mcrypt did (Blowfish, ECB, zero padding)
    $handler = mcrypt_module_open(MCRYPT_BLOWFISH, '', MCRYPT_MODE_ECB, '');
    $initVector = mcrypt_create_iv(mcrypt_enc_get_iv_size($handler), MCRYPT_RAND);
    mcrypt_generic_init($handler, $m1Key, $initVector);
    $legacyCiphertext = base64_encode(mcrypt_generic($handler, $plaintext));
    mcrypt_generic_deinit($handler);
    mcrypt_module_close($handler);

    $newKey = sodium_bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    $command = new \MahoCLI\Commands\SysEncryptionKeyRegenerate();
    $reflection = new ReflectionClass($command);
    $reflection->getProperty('oldEncryptionKey')->setValue($command, $m1Key);
    $reflection->getProperty('isOldEncryptionKeyM1')->setValue($command, true);
    $reflection->getProperty('newEncryptionKey')->setValue($command, $newKey);

    $decrypted = $command->decrypt($legacyCiphertext);
    $reencrypted = $command->encrypt($decrypted);

    $decoded = sodium_base642bin($reencrypted, SODIUM_BASE64_VARIANT_ORIGINAL);
    $roundTrip = sodium_crypto_secretbox_open(
        substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        sodium_hex2bin($newKey),
    );

    echo 'RESULT:' . json_encode([
        'expected' => $plaintext,
        'decrypted' => $decrypted,
        'roundTrip' => $roundTrip,
    ]);
    PHP;

    $script = str_replace(
        ['%PROJECT_VENDOR%', '%TMP_VENDOR%'],
        [dirname(__DIR__, 4) . '/vendor', mcryptCompatTmpDir() . '/vendor'],
        $script,
    );
    $scriptPath = mcryptCompatTmpDir() . '/roundtrip.php';
    file_put_contents($scriptPath, $script);

    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($scriptPath) . ' 2>&1', $output, $code);
    $resultLine = array_values(array_filter($output, fn(string $line) => str_starts_with($line, 'RESULT:')))[0] ?? null;

    if ($code !== 0 || $resultLine === null) {
        $this->fail("mcrypt round-trip subprocess failed (exit $code):\n" . implode("\n", $output));
    }

    $result = json_decode(substr($resultLine, strlen('RESULT:')), true, flags: JSON_THROW_ON_ERROR);
    expect($result['decrypted'])->toBe($result['expected'])
        ->and($result['roundTrip'])->toBe($result['expected']);
});

test('M1 data yields an empty string when mcrypt support is absent', function () {
    if (function_exists('mcrypt_module_open')) {
        $this->fail('mcrypt functions leaked into the main test process; they must only ever load in the subprocess');
    }

    $command = mcryptCompatCommandWithKeys(
        md5('m1-era-encryption-key'),
        true,
        sodium_bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)),
    );

    expect($command->decrypt(base64_encode('any-legacy-ciphertext')))->toBe('');
});

test('sodium-encrypted data re-encrypts from old key to new key', function () {
    $plaintext = 'already-modern-secret';
    $oldKey = sodium_bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    $newKey = sodium_bin2hex(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));

    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $legacyCiphertext = sodium_bin2base64(
        $nonce . sodium_crypto_secretbox($plaintext, $nonce, sodium_hex2bin($oldKey)),
        SODIUM_BASE64_VARIANT_ORIGINAL,
    );

    $command = mcryptCompatCommandWithKeys($oldKey, false, $newKey);

    $decrypted = $command->decrypt($legacyCiphertext);
    expect($decrypted)->toBe($plaintext);

    $decoded = sodium_base642bin($command->encrypt($decrypted), SODIUM_BASE64_VARIANT_ORIGINAL);
    $roundTrip = sodium_crypto_secretbox_open(
        substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
        sodium_hex2bin($newKey),
    );
    expect($roundTrip)->toBe($plaintext);
});
