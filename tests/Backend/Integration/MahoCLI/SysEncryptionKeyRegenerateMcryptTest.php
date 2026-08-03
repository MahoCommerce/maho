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
 * Before the migration: the compatibility module owns Mage_Core_Model_Encryption, so the
 * encryptor has no validateKeyAsHex() and no KEY_LENGTH_* constants, and a key is valid
 * by Blowfish's rules (any length up to 56) rather than libsodium's.
 *
 * The health check has to survive that and, for every key shape, agree with what the store
 * actually does: if encrypt/decrypt works it is a dated store to migrate (warning); if it
 * throws the store is broken (error). The 64-hex row is the trap in the middle of the
 * migration, where regeneration has run but the module was never removed.
 */
test('the health check agrees with reality on every key shape, before the migration', function () {
    if (($installError = mcryptCompatEnsureModuleInstalled()) !== null) {
        $this->fail($installError);
    }

    $script = <<<'PHP'
    <?php
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    define('MAHO_ROOT_DIR', '%PROJECT%');
    chdir(MAHO_ROOT_DIR);
    require '%PROJECT_VENDOR%/autoload.php';
    require '%TMP_VENDOR%/autoload.php';

    // Defining the module's classes up front is what the module code pool does by
    // resolving ahead of core: Mage_Core_Model_Encryption is now the mcrypt one.
    require '%MODULE%/lib/Varien/Crypt/Abstract.php';
    require '%MODULE%/lib/Varien/Crypt/Mcrypt.php';
    require '%MODULE%/lib/Varien/Crypt.php';
    require '%MODULE%/app/code/core/Mage/Core/Model/Encryption.php';

    Mage::app();

    $shapes = [
        'libsodium 64-hex'  => sodium_bin2hex(sodium_crypto_secretbox_keygen()),
        'mcrypt 32-hex'     => md5('m1-era-encryption-key'),
        'mcrypt 32 non-hex' => 'ThisIsAnOldMagentoKey_32charsXX!',
        'blowfish maximum'  => str_repeat('Kk9', 18) . 'zz',
        '64 non-hex'        => str_repeat('zy', 32),
        'odd-length hex'    => str_repeat('ab', 31) . 'c',
        'empty'             => '',
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
        ];
    }

    echo 'RESULT:' . json_encode($result);
    PHP;

    $script = str_replace(
        ['%PROJECT_VENDOR%', '%TMP_VENDOR%', '%PROJECT%', '%MODULE%'],
        [
            dirname(__DIR__, 4) . '/vendor',
            mcryptCompatTmpDir() . '/vendor',
            dirname(__DIR__, 4),
            mcryptCompatTmpDir() . '/vendor/mahocommerce/module-mcrypt-compat',
        ],
        $script,
    );

    $result = mcryptCompatRunSubprocess($this, 'healthcheck-legacy', $script);

    // The check ran at all: before this it died with an uncaught Error on every shape.
    expect($result)->toHaveCount(7);

    foreach ($result as $shape => $row) {
        expect($row['key'])
            ->toBe($row['works'] ? 'warning' : 'error', "key shape: $shape")
            ->and($row['data'])->toBe('warning', "key shape: $shape");
    }

    // An mcrypt key is the right key here, so the store works and only wants migrating.
    expect($result['mcrypt 32-hex']['works'])->toBeTrue()
        ->and($result['mcrypt 32 non-hex']['works'])->toBeTrue()
        ->and($result['blowfish maximum']['works'])->toBeTrue();

    // Regeneration ran but the module was never removed: Blowfish cannot take a key this
    // long, so every crypt call throws and the check must say so rather than shrug.
    expect($result['libsodium 64-hex']['works'])->toBeFalse()
        ->and($result['libsodium 64-hex']['details'])->toContain('composer remove mahocommerce/module-mcrypt-compat');
});

test('the health check reads clean once the module is gone, after the migration', function () {
    // getCheckResults() builds paths from MAHO_ROOT_DIR, which only the CLI/web entry
    // points define; the subprocess above defines its own.
    if (!defined('MAHO_ROOT_DIR')) {
        define('MAHO_ROOT_DIR', dirname(__DIR__, 3));
    }

    // This process has no compatibility module, which is the finished migration: the
    // sodium encryptor, the sodium key already in local.xml, and data encrypted under it.
    $severity = array_column(\MahoCLI\Commands\HealthCheck::getCheckResults(), 'severity', 'check');

    expect(Mage::helper('core')->isLegacyEncryptor())->toBeFalse()
        ->and($severity['Encryption Key'])->toBe('ok')
        ->and($severity['Encrypted Data'])->toBe('ok');
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
