<?php

/**
 * Fetches and caches provider JWKS documents.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\HttpClient;

class Maho_SocialLogin_Model_JwksClient
{
    public const CACHE_TAG = 'SOCIALLOGIN_JWKS';
    public const CACHE_LIFETIME = 21600;

    /**
     * @return array<int, array<string, mixed>> The JWKS "keys" list
     * @throws RuntimeException When the JWKS endpoint cannot be reached or returns garbage
     */
    public function getKeys(string $url, string $cacheId): array
    {
        $cached = Mage::app()->loadCache($cacheId);
        if (is_string($cached) && $cached !== '') {
            $keys = json_decode($cached, true);
            if (is_array($keys)) {
                return $keys;
            }
        }

        try {
            $response = HttpClient::create(['timeout' => 10])->request('GET', $url);
            $jwks = json_decode($response->getContent(), true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to fetch JWKS from {$url}", 0, $e);
        }

        if (!is_array($jwks['keys'] ?? null) || $jwks['keys'] === []) {
            throw new RuntimeException("JWKS document from {$url} contains no keys");
        }

        Mage::app()->saveCache(
            json_encode($jwks['keys']),
            $cacheId,
            [self::CACHE_TAG],
            self::CACHE_LIFETIME,
        );

        return $jwks['keys'];
    }

    /**
     * @param array<int, array<string, mixed>> $keys
     * @return array<string, mixed>|null
     */
    public function findKey(array $keys, string $kid): ?array
    {
        foreach ($keys as $key) {
            if (is_array($key) && ($key['kid'] ?? null) === $kid) {
                return $key;
            }
        }
        return null;
    }
}
