<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Maho\Security\RateLimitScope;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Request throttling helpers shared by API providers and processors.
 *
 * Limits are read from `system/rate_limit/{configKey}`; a value of 0 disables
 * the check. Ship a non-zero default in config.xml for any key you rely on,
 * an unset key resolves to 0 and silently turns the limit off.
 */
trait RateLimitTrait
{
    /**
     * Throttle a caller-supplied key. Reads the limit from
     * `system/rate_limit/{configKey}`; 0 disables the limit. Throws
     * TooManyRequestsHttpException when the cap is hit.
     */
    protected function checkRateLimit(string $key, string $configKey, int $windowSeconds): void
    {
        $limit = (int) \Mage::getStoreConfig('system/rate_limit/' . $configKey);

        // A non-positive limit disables the limiter inside RateLimiter, so no guard needed here.
        if (!\Mage::helper('core')->rateLimiterBy($configKey, $key, $limit, $windowSeconds)->attempt()) {
            throw new TooManyRequestsHttpException(
                (string) $windowSeconds,
                'Too many requests. Please try again later.',
            );
        }
    }

    /**
     * Throttle by client IP. Core resolves the identity (proxy-aware) from the
     * Ip scope, so this never reads the remote address itself. Reads the limit
     * from `system/rate_limit/{configKey}`; a non-positive value disables it.
     */
    protected function checkRateLimitByIp(string $keyPrefix, string $configKey, int $windowSeconds): void
    {
        $limit = (int) \Mage::getStoreConfig('system/rate_limit/' . $configKey);

        // A non-positive limit disables the limiter inside RateLimiter, so no guard needed here.
        if (!\Mage::helper('core')->rateLimiter($keyPrefix, $limit, $windowSeconds, RateLimitScope::Ip)->attempt()) {
            throw new TooManyRequestsHttpException(
                (string) $windowSeconds,
                'Too many requests. Please try again later.',
            );
        }
    }
}
