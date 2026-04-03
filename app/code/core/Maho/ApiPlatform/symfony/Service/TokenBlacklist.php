<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

        \Mage::app()->getCache()->save(
            (string) $expiresAt,
            self::CACHE_PREFIX . preg_replace('/[^a-f0-9]/', '', $jti),
            [self::CACHE_TAG],
            $ttl,
        );
    }

    public function isRevoked(string $jti): bool
    {
        $data = \Mage::app()->getCache()->load(
            self::CACHE_PREFIX . preg_replace('/[^a-f0-9]/', '', $jti),
        );

        return $data !== false;
    }

    public function cleanup(): void
    {
        \Mage::app()->getCache()->clean([self::CACHE_TAG]);
    }
}
