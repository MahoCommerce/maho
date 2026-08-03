<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use MahoCLI\Commands\HealthCheck;
use Symfony\Component\Console\Output\BufferedOutput;

uses(Tests\MahoBackendTestCase::class);

/**
 * The encryption health checks must survive a store that is still on the legacy
 * mcrypt encryptor. `mahocommerce/module-mcrypt-compat` ships its own
 * app/code/core/Mage/Core/Model/Encryption.php, which the module code pool
 * resolves ahead of core's, so on an M1/OpenMage store the encryptor has no
 * validateKeyAsHex() and its validateKey() returns a crypt object instead of a
 * bool. That is the state the health check is supposed to diagnose, and it is
 * also the state sys:encryptionkey:regenerate is run in.
 *
 * Mage_Core_Helper_Data::getEncryptor() honours global/helpers/core/encryption_model,
 * so the double below stands in for the compat class without touching the code pools.
 * It reproduces the first failure (the undefined method reached through the helper);
 * the undefined KEY_LENGTH_HEX constant is only reachable once that is fixed.
 */
class Maho_Test_LegacyEncryption
{
    protected $_helper;

    public function setHelper($helper)
    {
        $this->_helper = $helper;
        return $this;
    }

    public function encrypt($data)
    {
        return base64_encode(strrev((string) $data));
    }

    public function decrypt($data)
    {
        return strrev((string) base64_decode((string) $data));
    }

    /** The compat module returns Varien_Crypt_Mcrypt here, not a bool. */
    public function validateKey($key)
    {
        return new stdClass();
    }
}

const LEGACY_M1_KEY = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

// getCheckResults() builds paths from MAHO_ROOT_DIR, which only the CLI/web entry
// points define; seed it so these tests exercise the checks, not the harness.
if (!defined('MAHO_ROOT_DIR')) {
    define('MAHO_ROOT_DIR', dirname(__DIR__, 5));
}

function useLegacyEncryptor(string $key = LEGACY_M1_KEY): void
{
    Mage::getConfig()->setNode('global/helpers/core/encryption_model', 'Maho_Test_LegacyEncryption');
    Mage::getConfig()->setNode('global/crypt/key', $key);

    $helper = Mage::helper('core');
    $property = new ReflectionProperty($helper, '_encryptor');
    $property->setAccessible(true);
    $property->setValue($helper, null);
}

describe('Encryption health checks on a legacy mcrypt store', function () {
    it('does not fatal when the helper validates a key', function () {
        useLegacyEncryptor();

        expect(Mage::helper('core')->validateKeyAsHex(LEGACY_M1_KEY))->toBeBool();
    });

    it('diagnoses the key instead of crashing', function () {
        useLegacyEncryptor();

        $issue = HealthCheck::findEncryptionKeyIssue(LEGACY_M1_KEY);

        expect($issue === null || is_string($issue))->toBeTrue();
    });

    it('still renders the admin System > Tools > Health Check page', function () {
        useLegacyEncryptor();

        $rows = array_column(HealthCheck::getCheckResults(), 'severity', 'check');

        expect($rows)->toHaveKey('Encryption Key');
    });

    it('does not abort sys:encryptionkey:regenerate before it can run', function () {
        useLegacyEncryptor();

        // The guard at SysEncryptionKeyRegenerate.php:61 that decides whether the
        // old key is an M1 one. It must work under the very encryptor it detects.
        $isM1 = !Mage::helper('core')->validateKeyAsHex(LEGACY_M1_KEY);

        expect($isM1)->toBeTrue();
    });

    it('keeps validateKey() returning a bool whatever the encryptor is', function () {
        useLegacyEncryptor();

        expect(Mage::helper('core')->validateKey(LEGACY_M1_KEY))->toBeBool();
    });
});

describe('Encryption health check reporting', function () {
    it('tells the CLI that encrypted data went unchecked when the key is broken', function () {
        Mage::getConfig()->setNode('global/crypt/key', LEGACY_M1_KEY);

        $command = new HealthCheck();
        $method = new ReflectionMethod($command, 'checkEncryption');
        $method->setAccessible(true);

        $output = new BufferedOutput();
        $method->invoke($command, $output);

        // getCheckResults() emits an "Encrypted Data / Not checked" row here; the
        // CLI returns early and says nothing, so the two surfaces disagree.
        expect($output->fetch())->toContain('encrypted data');
    });
});
