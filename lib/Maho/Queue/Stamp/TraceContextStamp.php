<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Carries the W3C trace context of the request that dispatched the message, so
 * the consumer trace continues the producing trace instead of starting a new one.
 */
final readonly class TraceContextStamp implements StampInterface
{
    /**
     * @param array<string, string> $carrier Propagation headers, e.g. traceparent and tracestate
     */
    public function __construct(
        public array $carrier,
    ) {}
}
