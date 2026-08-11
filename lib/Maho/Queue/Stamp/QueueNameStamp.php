<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Names the logical queue a message belongs to (e.g. 'default', 'email').
 */
final readonly class QueueNameStamp implements StampInterface
{
    public function __construct(
        public string $queue,
    ) {}
}
