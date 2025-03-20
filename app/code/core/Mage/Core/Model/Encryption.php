<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Provides basic logic for hashing passwords and encrypting/decrypting misc data
 *
 * @package    Mage_Core
 */
class Mage_Core_Model_Encryption
{
    public const HASH_VERSION_MD5    = 0;
    public const HASH_VERSION_SHA256 = 1;
    public const HASH_VERSION_SHA512 = 2;

    /**
     * Encryption method bcrypt
     */
    public const HASH_VERSION_LATEST = 3;

    /**
     * Maximum Password Length
     */
    public const MAXIMUM_PASSWORD_LENGTH = 256;

    /**
     * @var Mage_Core_Helper_Data
     */
    protected $_helper;

    /**
     * Set helper instance
     *
     * @param Mage_Core_Helper_Data $helper
     * @return $this
     */
    public function setHelper($helper)
    {
        $this->_helper = $helper;
        return $this;
    }

    /**
     * Generate a [salted] hash.
     *
     * $salt can be:
     * false - a random will be generated
     * integer - a random with specified length will be generated
     * string
     *
     * @param string $password
     * @param mixed $salt
     * @return string
     */
    public function getHash(#[\SensitiveParameter] $password, $salt = false)
    {
        if (is_int($salt)) {
            $salt = $this->_helper->getRandomString($salt);
        }
        return $salt === false
            ? $this->hash($password)
            : $this->hash($salt . $password, self::HASH_VERSION_SHA256) . ':' . $salt;
    }

    /**
     * Generate hash for customer password
     *
     * @param string $password
     * @param mixed $salt
     * @return string
     */
    public function getHashPassword(#[\SensitiveParameter] $password, $salt = null)
    {
        if (is_int($salt)) {
            $salt = $this->_helper->getRandomString($salt);
        }
        return (bool) $salt
            ? $this->hash($salt . $password, $this->_helper->getVersionHash($this)) . ':' . $salt
            : $this->hash($password, $this->_helper->getVersionHash($this));
    }

    /**
     * Hash a string
     *
     * @param string $data
     * @param int $version
     * @return bool|string
     */
    public function hash(#[\SensitiveParameter] $data, $version = self::HASH_VERSION_MD5)
    {
        if (self::HASH_VERSION_LATEST === $version && $version === $this->_helper->getVersionHash($this)) {
            return password_hash($data, PASSWORD_DEFAULT);
        } elseif (self::HASH_VERSION_SHA256 == $version) {
            return hash('sha256', $data);
        } elseif (self::HASH_VERSION_SHA512 == $version) {
            return hash('sha512', $data);
        }
        return md5($data);
    }

    /**
     * Validate hash against hashing method (with or without salt)
     *
     * @param string $password
     * @param string $hash
     * @return bool
     * @throws Exception
     */
    public function validateHash(#[\SensitiveParameter] $password, #[\SensitiveParameter] $hash)
    {
        if (strlen($password) > self::MAXIMUM_PASSWORD_LENGTH) {
            return false;
        }

        return $this->validateHashByVersion($password, $hash, self::HASH_VERSION_LATEST)
            || $this->validateHashByVersion($password, $hash, self::HASH_VERSION_SHA512)
            || $this->validateHashByVersion($password, $hash, self::HASH_VERSION_SHA256)
            || $this->validateHashByVersion($password, $hash, self::HASH_VERSION_MD5);
    }

    /**
     * Validate hash by specified version
     *
     * @param string $password
     * @param string $hash
     * @param int $version
     * @return bool
     */
    public function validateHashByVersion(#[\SensitiveParameter] $password, #[\SensitiveParameter] $hash, $version = self::HASH_VERSION_MD5)
    {
        if ($version == self::HASH_VERSION_LATEST && $version == $this->_helper->getVersionHash($this)) {
            return password_verify($password, $hash);
        }
        // look for salt
        $hashArr = explode(':', $hash, 2);
        if (count($hashArr) === 1) {
            return hash_equals($this->hash($password, $version), $hash);
        }
        [$hash, $salt] = $hashArr;
        return hash_equals($this->hash($salt . $password, $version), $hash);
    }

    public function encrypt(#[\SensitiveParameter] string $data): string
    {
        $key = (string) Mage::getConfig()->getNode('global/crypt/key');
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($data, $nonce, $key);
        $encrypted = base64_encode($nonce . $ciphertext);
        sodium_memzero($data); // Clean sensitive data from memory
        return $encrypted;
    }

    public function decrypt(#[\SensitiveParameter] string $data): string
    {
        $decoded = base64_decode($data);
        if ($decoded === false) {
            return '';
            throw new Exception('Invalid base64 encoding');
        }

        if (mb_strlen($decoded, '8bit') < (SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES)) {
            return '';
            throw new Exception('Data is too short to be valid');
        }

        $key = (string) Mage::getConfig()->getNode('global/crypt/key');
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if ($plaintext === false) {
            return '';
            throw new Exception('Decryption failed: data may be corrupted or tampered with');
        }

        // Clean sensitive data from memory
        sodium_memzero($decoded);
        sodium_memzero($nonce);
        sodium_memzero($ciphertext);

        return $plaintext;
    }

    public function validateKey(#[\SensitiveParameter]string $key): bool
    {
        return strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
    }
}
