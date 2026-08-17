<?php

/**
 * Converts an RSA JSON Web Key to a PEM-encoded SubjectPublicKeyInfo.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

use ParagonIE\ConstantTime\Base64UrlSafe;

class Maho_SocialLogin_Model_JwkToPem
{
    private const RSA_ENCRYPTION_OID = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01";

    /**
     * @param array<string, mixed> $jwk
     * @throws InvalidArgumentException When the JWK is not a usable RSA public key
     */
    public static function convert(array $jwk): string
    {
        if (($jwk['kty'] ?? '') !== 'RSA' || empty($jwk['n']) || empty($jwk['e'])) {
            throw new InvalidArgumentException('Not an RSA public JWK');
        }

        $modulus = Base64UrlSafe::decodeNoPadding((string) $jwk['n']);
        $exponent = Base64UrlSafe::decodeNoPadding((string) $jwk['e']);
        if ($modulus === '' || $exponent === '') {
            throw new InvalidArgumentException('Empty RSA key material');
        }

        $rsaPublicKey = self::sequence(self::integer($modulus) . self::integer($exponent));
        $algorithm = self::sequence(self::RSA_ENCRYPTION_OID . "\x05\x00");
        $spki = self::sequence($algorithm . self::bitString($rsaPublicKey));

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spki), 64, "\n")
            . '-----END PUBLIC KEY-----';
    }

    private static function length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }
        $bytes = ltrim(pack('N', $length), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function integer(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || (ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . self::length(strlen($bytes)) . $bytes;
    }

    private static function sequence(string $content): string
    {
        return "\x30" . self::length(strlen($content)) . $content;
    }

    private static function bitString(string $content): string
    {
        return "\x03" . self::length(strlen($content) + 1) . "\x00" . $content;
    }
}
