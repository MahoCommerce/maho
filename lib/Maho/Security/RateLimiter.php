<?php

/**
 * Cache-backed sliding-window rate limiter over an arbitrary key.
 *
 * The read-modify-write is not atomic, so concurrent hits can race and slightly under-count.
 * That is acceptable for abuse mitigation: this is a soft throttle, not a hard guarantee. A
 * non-positive limit disables the limiter (it never blocks and records nothing).
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Security;

final class RateLimiter
{
    public const CACHE_TAG = 'rate_limit';

    public function __construct(
        private readonly string $key,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
    ) {}

    /**
     * True when the key is at or over its budget. Pure read, records nothing.
     */
    public function tooManyAttempts(): bool
    {
        if ($this->maxAttempts <= 0) {
            return false;
        }
        return count($this->loadHits()) >= $this->maxAttempts;
    }

    /**
     * Check-and-record: records a hit unless already over budget. Returns true when the request
     * is allowed, false when blocked.
     */
    public function attempt(): bool
    {
        if ($this->tooManyAttempts()) {
            return false;
        }
        $this->hit();
        return true;
    }

    /**
     * Record a single hit. For check-up-front / record-on-failure flows where the check and the
     * record happen at different points (see Mage_Sales_Helper_Guest).
     */
    public function hit(): void
    {
        if ($this->maxAttempts <= 0) {
            return;
        }
        $hits = $this->loadHits();
        $hits[] = time();
        \Mage::app()->getCache()->save(json_encode($hits), $this->cacheId(), [self::CACHE_TAG], $this->windowSeconds);
    }

    /**
     * Number of hits still inside the window.
     */
    public function attempts(): int
    {
        return count($this->loadHits());
    }

    /**
     * Hits left before the next one blocks.
     */
    public function remaining(): int
    {
        if ($this->maxAttempts <= 0) {
            return PHP_INT_MAX;
        }
        return max(0, $this->maxAttempts - $this->attempts());
    }

    /**
     * Forget every hit for this key.
     */
    public function clear(): void
    {
        \Mage::app()->getCache()->remove($this->cacheId());
    }

    private function cacheId(): string
    {
        return 'rate_limit_' . md5($this->key);
    }

    /**
     * @return list<int>
     */
    private function loadHits(): array
    {
        $cutoff = time() - $this->windowSeconds;
        $data = \Mage::app()->getCache()->load($this->cacheId());
        $hits = is_string($data) && $data !== '' ? json_decode($data, true) : [];
        if (!is_array($hits)) {
            $hits = [];
        }
        return array_values(array_filter($hits, fn($ts): bool => (int) $ts > $cutoff));
    }
}
