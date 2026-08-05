<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Sender-side deduplication: while a pending or processing message with the
 * same key exists, dispatching another one is a silent no-op. DB transport only.
 */
final readonly class DedupeKeyStamp implements StampInterface
{
    public function __construct(
        public string $key,
    ) {}
}
