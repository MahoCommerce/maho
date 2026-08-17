<?php

/**
 * Shared RSA keypair, JWKS fixtures, and ID-token builder for the social
 * login test suites.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use ParagonIE\ConstantTime\Base64UrlSafe;

final class SocialLoginJwt
{
    /** @var array{private: string, public: string, n: string, e: string}|null */
    private static ?array $keypair = null;

    /**
     * @return array{private: string, public: string, n: string, e: string}
     */
    public static function keypair(): array
    {
        if (self::$keypair === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $privatePem);
            $details = openssl_pkey_get_details($key);
            self::$keypair = [
                'private' => $privatePem,
                'public' => $details['key'],
                'n' => $details['rsa']['n'],
                'e' => $details['rsa']['e'],
            ];
        }
        return self::$keypair;
    }

    /**
     * @return array<string, string>
     */
    public static function jwk(string $kid): array
    {
        $pair = self::keypair();
        return [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => $kid,
            'n' => Base64UrlSafe::encodeUnpadded($pair['n']),
            'e' => Base64UrlSafe::encodeUnpadded($pair['e']),
        ];
    }

    public static function seedJwksCache(string $kid, string ...$cacheIds): void
    {
        foreach ($cacheIds as $cacheId) {
            \Mage::app()->saveCache(
                json_encode([self::jwk($kid)]),
                $cacheId,
                [\Maho_SocialLogin_Model_JwksClient::CACHE_TAG],
                300,
            );
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    public static function idToken(string $kid, string $issuer, string $audience, array $claims = [], ?\DateTimeImmutable $expiresAt = null): string
    {
        $pair = self::keypair();
        $config = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($pair['private']),
            InMemory::plainText($pair['public']),
        );
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $builder = $config->builder()
            ->withHeader('kid', $kid)
            ->issuedBy($issuer)
            ->permittedFor($audience)
            ->issuedAt($now)
            ->expiresAt($expiresAt ?? $now->modify('+1 hour'));
        foreach ($claims as $name => $value) {
            // "sub" is a registered claim; lcobucci's builder rejects it in withClaim()
            $builder = $name === 'sub' ? $builder->relatedTo($value) : $builder->withClaim($name, $value);
        }
        return $builder->getToken($config->signer(), $config->signingKey())->toString();
    }
}
