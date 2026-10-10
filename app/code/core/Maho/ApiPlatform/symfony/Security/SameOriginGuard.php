<?php

/**
 * CSRF guard for the cookie-authenticated admin endpoints.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The request's Origin header, or its Referer as a fallback, must match a configured store
 * base URL. A browser attaches one of them to every cross-site POST and cannot spoof them,
 * so a forged form submission is rejected.
 */
final class SameOriginGuard
{
    public static function assert(Request $request): void
    {
        $origin = $request->headers->get('Origin')
            ?? self::originOf((string) $request->headers->get('Referer'));

        if ($origin === null || !in_array($origin, self::allowedOrigins(), true)) {
            throw new AccessDeniedHttpException('Cross-origin request rejected');
        }
    }

    /** Normalize a URL to scheme://host[:port], or null if it has no host. */
    public static function originOf(string $url): ?string
    {
        $parts = parse_url($url);
        if (empty($parts['host']) || empty($parts['scheme'])) {
            return null;
        }
        return $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /** @return list<string> Origins of the store's secure and unsecure base URLs. */
    public static function allowedOrigins(): array
    {
        $origins = [];
        foreach (['web/secure/base_url', 'web/unsecure/base_url'] as $path) {
            $origin = self::originOf((string) \Mage::getStoreConfig($path));
            if ($origin !== null) {
                $origins[] = $origin;
            }
        }
        return array_values(array_unique($origins));
    }
}
