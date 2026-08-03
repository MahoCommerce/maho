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

/**
 * Additionally install the compatibility module itself, whose Mage_Core_Model_Encryption
 * replaces core's from the module code pool. Same throwaway directory, same subprocess
 * isolation: the mcrypt encryptor must never become this process' encryptor.
 *
 * @return string|null Error message when the ephemeral install is unavailable, null on success
 */
function mcryptCompatEnsureModuleInstalled(): ?string
{
    static $error = false;
    if ($error !== false) {
        return $error;
    }
    if (($installError = mcryptCompatEnsureInstalled()) !== null) {
        return $error = $installError;
    }

    $tmp = mcryptCompatTmpDir();
    if (is_dir($tmp . '/vendor/mahocommerce/module-mcrypt-compat')) {
        return $error = null;
    }

    $cmd = sprintf(
        'composer require mahocommerce/module-mcrypt-compat --no-interaction --no-progress --no-audit --no-plugins --working-dir=%s 2>&1',
        escapeshellarg($tmp),
    );
    exec($cmd, $output, $code);
    if ($code !== 0 || !is_dir($tmp . '/vendor/mahocommerce/module-mcrypt-compat')) {
        return $error = 'ephemeral mahocommerce/module-mcrypt-compat install failed: ' . implode("\n", $output);
    }

    return $error = null;
}

/**
 * Run $script in a PHP subprocess and return its decoded RESULT payload.
 *
 * @return array<string, mixed>
 */
function mcryptCompatRunSubprocess(object $test, string $name, string $script): array
{
    $scriptPath = mcryptCompatTmpDir() . '/' . $name . '.php';
    file_put_contents($scriptPath, $script);

    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($scriptPath) . ' 2>&1', $output, $code);
    $resultLine = array_values(array_filter($output, fn(string $line) => str_starts_with($line, 'RESULT:')))[0] ?? null;

    if ($code !== 0 || $resultLine === null) {
        $test->fail("$name subprocess failed (exit $code):\n" . implode("\n", $output));
    }

    return json_decode(substr($resultLine, strlen('RESULT:')), true, flags: JSON_THROW_ON_ERROR);
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

/**
 * The health check runs on two encryption backends and has to be right on both.
 *
 * WITHOUT mahocommerce/module-mcrypt-compat: Maho's own libsodium encryptor, where a key
 * is 64 hexadecimal characters and nothing else.
 *
 * WITH it: the module's Mage_Core_Model_Encryption resolves ahead of core's from its own
 * code pool, so the encryptor is Blowfish. It has no validateKeyAsHex() and no KEY_LENGTH_*
 * constants, and a key is anything up to 56 bytes.
 *
 * For every key shape the verdict has to match what the store actually does: if encrypt and
 * decrypt work it is a dated store to migrate (warning), if they throw the store is broken
 * (error). Both halves run the same script through the same code path, the only difference
 * being whether the module is installed, so the two columns are directly comparable.
 *
 * @return array<string, array{works: bool, key: string, data: string, details: string, encryptor: string}>
 */
function mcryptCompatHealthCheckMatrix(object $test, bool $withMcryptCompat): array
{
    $script = <<<'PHP'
    <?php
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    define('MAHO_ROOT_DIR', '%PROJECT%');
    chdir(MAHO_ROOT_DIR);
    $loader = require '%PROJECT_VENDOR%/autoload.php';
    %WITH_MODULE%

    Mage::app();

    $shapes = [
        'Maho libsodium key (64 hex)'                        => sodium_bin2hex(sodium_crypto_secretbox_keygen()),
        'Magento 1 / OpenMage default key (32-char md5)'     => md5('m1-era-encryption-key'),
        'Magento 1 / OpenMage custom key (32, not hex)'      => 'ThisIsAnOldMagentoKey_32charsXX!',
        'Magento 1 / OpenMage custom key (56, Blowfish max)' => str_repeat('Kk9', 18) . 'zz',
        'corrupted key (64 chars, not hex)'                  => str_repeat('zy', 32),
        'corrupted key (63 hex, truncated)'                  => str_repeat('ab', 31) . 'c',
        'no key configured'                                  => '',
    ];

    $result = [];
    foreach ($shapes as $name => $key) {
        Mage::getConfig()->setNode('global/crypt/key', $key);
        $helper = Mage::helper('core');
        (new ReflectionProperty($helper, '_encryptor'))->setValue($helper, null);

        $works = false;
        try {
            $works = $helper->decrypt($helper->encrypt('probe-value')) === 'probe-value';
        } catch (Throwable) {
        }

        $rows = \MahoCLI\Commands\HealthCheck::getCheckResults();
        $severity = array_column($rows, 'severity', 'check');
        $details = array_column($rows, 'details', 'check');

        $result[$name] = [
            'works' => $works,
            'key' => $severity['Encryption Key'],
            'data' => $severity['Encrypted Data'],
            'details' => $details['Encryption Key'],
            'encryptor' => (new ReflectionClass('Mage_Core_Model_Encryption'))->getFileName(),
        ];
    }

    echo 'RESULT:' . json_encode($result);

    // The module registers a shutdown function per crypt object, and the one built for the
    // empty key has no key to deinitialize, so it warns once the result above is printed.
    // Developer mode would turn that into an uncaught fatal and lose the whole run.
    set_error_handler(fn(int $errno, string $error): bool => str_contains($error, 'mcrypt_generic_deinit'));
    PHP;

    // Registering the module's code pool ahead of core's on the PSR-0 prefix is exactly what
    // the Maho composer plugin does for an installed maho-module, so Mage_Core_Model_Encryption
    // is resolved from the real package by the autoloader rather than substituted by the test.
    $withModule = '';
    if ($withMcryptCompat) {
        $module = mcryptCompatTmpDir() . '/vendor/mahocommerce/module-mcrypt-compat';
        $withModule = sprintf("require '%s/vendor/autoload.php';\n    ", mcryptCompatTmpDir());
        $withModule .= sprintf(
            "\$loader->add('Mage_Core_', '%s/app/code/core', true);\n    \$loader->add('Varien_', '%s/lib', true);",
            $module,
            $module,
        );
    }

    $script = str_replace(
        ['%PROJECT_VENDOR%', '%PROJECT%', '%WITH_MODULE%'],
        [dirname(__DIR__, 4) . '/vendor', dirname(__DIR__, 4), $withModule],
        $script,
    );

    return mcryptCompatRunSubprocess(
        $test,
        'healthcheck-' . ($withMcryptCompat ? 'with' : 'without') . '-mcrypt-compat',
        $script,
    );
}

test('a Magento 1 / OpenMage store, with mcrypt-compat installed', function () {
    if (($installError = mcryptCompatEnsureModuleInstalled()) !== null) {
        $this->fail($installError);
    }

    $matrix = mcryptCompatHealthCheckMatrix($this, withMcryptCompat: true);

    // The encryptor really is the installed module's, resolved by the autoloader.
    expect($matrix)->toHaveCount(7)
        ->and($matrix['Magento 1 / OpenMage default key (32-char md5)']['encryptor'])
        ->toContain('mahocommerce/module-mcrypt-compat');

    foreach ($matrix as $shape => $row) {
        expect($row['key'])
            ->toBe($row['works'] ? 'warning' : 'error', "key shape: $shape")
            ->and($row['data'])->toBe('warning', "key shape: $shape");
    }

    // An mcrypt key is the right key for an mcrypt encryptor: the store works and only
    // wants migrating, so none of these may be reported as a broken key.
    expect($matrix['Magento 1 / OpenMage default key (32-char md5)']['works'])->toBeTrue()
        ->and($matrix['Magento 1 / OpenMage custom key (32, not hex)']['works'])->toBeTrue()
        ->and($matrix['Magento 1 / OpenMage custom key (56, Blowfish max)']['works'])->toBeTrue();

    // Halfway through the migration: sys:encryptionkey:regenerate has run, but the module
    // was never removed. Blowfish cannot take a 64-byte key, so the store is broken.
    expect($matrix['Maho libsodium key (64 hex)']['works'])->toBeFalse()
        ->and($matrix['Maho libsodium key (64 hex)']['details'])
        ->toContain('composer remove mahocommerce/module-mcrypt-compat');
});

test('a Maho store, without mcrypt-compat installed', function () {
    $matrix = mcryptCompatHealthCheckMatrix($this, withMcryptCompat: false);

    expect($matrix)->toHaveCount(7)
        ->and($matrix['Maho libsodium key (64 hex)']['encryptor'])
        ->not()->toContain('mcrypt-compat');

    foreach ($matrix as $shape => $row) {
        expect($row['key'])->toBe($row['works'] ? 'ok' : 'error', "key shape: $shape");
    }

    // The finished migration is the only shape libsodium accepts; every Magento 1 /
    // OpenMage key must be reported as unusable, pointing at the compatibility module.
    expect($matrix['Maho libsodium key (64 hex)']['works'])->toBeTrue()
        ->and($matrix['Magento 1 / OpenMage default key (32-char md5)']['works'])->toBeFalse()
        ->and($matrix['Magento 1 / OpenMage default key (32-char md5)']['details'])
        ->toContain('mahocommerce/module-mcrypt-compat');
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
