<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

/**
 * Cache-aside helper for API providers.
 *
 * Provides a remember() method that encapsulates the full cache roundtrip:
 * check cache, deserialize on hit, compute + serialize + store on miss.
 * Eliminates repetitive cache load/save boilerplate and custom
 * dtoToArray/arrayToDto method pairs in each provider.
 */
trait CacheTrait
{
    /**
     * Get or compute a cached value.
     *
     * @template T
     * @param array<string> $cacheTags
     * @param int $ttl TTL in seconds
     * @param \Closure(): T $compute Builds the result if cache misses
     * @param \Closure(T): array $serialize Converts result to a cacheable array
     * @param \Closure(array): T $deserialize Converts cached array back to result
     * @return T
     */
    protected function remember(
        string $cacheKey,
        array $cacheTags,
        int $ttl,
        \Closure $compute,
        \Closure $serialize,
        \Closure $deserialize,
    ): mixed {
        $cached = \Mage::app()->getCache()->load($cacheKey);
        if ($cached !== false) {
            try {
                $data = \Mage::helper('core')->jsonDecode($cached);
            } catch (\JsonException) {
                $data = null;
            }
            if ($data !== null) {
                return $deserialize($data);
            }
        }

        $result = $compute();

        \Mage::app()->getCache()->save(
            (string) \Mage::helper('core')->jsonEncode($serialize($result)),
            $cacheKey,
            $cacheTags,
            $ttl,
        );

        return $result;
    }
}
