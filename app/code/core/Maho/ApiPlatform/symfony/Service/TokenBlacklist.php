<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Service;

/**
 * JWT token blacklist for logout/revocation using the Mage cache backend.
 *
 * This automatically benefits from whatever cache backend is configured
 * (file, Redis, memcached), including multi-server deployments.
 */
class TokenBlacklist
{
    private const CACHE_PREFIX = 'API_TOKEN_BLACKLIST_';
    private const CACHE_TAG = 'API_TOKEN_BLACKLIST';

    public function revoke(string $jti, int $expiresAt): void
    {
        $ttl = $expiresAt - time();
        if ($ttl <= 0) {
            return;
        }

        $key = self::cacheKey($jti);
        if ($key === null) {
            return;
        }

        \Mage::app()->getCache()->save(
            (string) $expiresAt,
            $key,
            [self::CACHE_TAG],
            $ttl,
        );
    }

    public function isRevoked(string $jti): bool
    {
        $key = self::cacheKey($jti);
        if ($key === null) {
            // Reject malformed JTIs by treating them as revoked rather than
            // collapsing non-hex characters into a shared bucket where
            // 'aaa@bbb' and 'aaabbb' would share blacklist state.
            return true;
        }
        return \Mage::app()->getCache()->load($key) !== false;
    }

    /**
     * Build the cache key for a JTI, or null when the JTI is malformed.
     * JwtService issues hex JTIs (`bin2hex(random_bytes(16))`), so anything
     * else is suspicious and rejected rather than silently normalized.
     */
    private static function cacheKey(string $jti): ?string
    {
        if ($jti === '' || !preg_match('/^[a-f0-9]+$/i', $jti)) {
            return null;
        }
        return self::CACHE_PREFIX . strtolower($jti);
    }
}
