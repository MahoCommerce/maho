<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
            $data = json_decode($cached, true);
            if ($data !== null) {
                return $deserialize($data);
            }
        }

        $result = $compute();

        \Mage::app()->getCache()->save(
            (string) json_encode($serialize($result)),
            $cacheKey,
            $cacheTags,
            $ttl,
        );

        return $result;
    }
}
