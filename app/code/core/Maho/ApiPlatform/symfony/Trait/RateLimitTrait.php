<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

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
        if ($limit <= 0) {
            return;
        }

        if (\Mage::helper('core')->isRateLimitExceeded(false, true, $key, $limit, $windowSeconds)) {
            throw new TooManyRequestsHttpException(
                (string) $windowSeconds,
                'Too many requests. Please try again later.',
            );
        }
    }

    /**
     * Throttle by client IP, using Maho's proxy-aware lookup. Fails open
     * (skips the check) if the IP can't be determined, matches the behaviour
     * of `Mage::helper('core')->isRateLimitExceeded()` in IP mode and avoids
     * collapsing every unknown-IP client into a shared bucket.
     */
    protected function checkRateLimitByIp(string $keyPrefix, string $configKey, int $windowSeconds): void
    {
        $ip = \Mage::helper('core/http')->getRemoteAddr();
        if (!$ip) {
            return;
        }

        $this->checkRateLimit($keyPrefix . ':ip:' . $ip, $configKey, $windowSeconds);
    }
}
