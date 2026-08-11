<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Queue\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Proof of claim ownership: written to the row when a worker claims it and
 * carried on the envelope, so a stale worker's late ack, reject, re-send or
 * keepalive can never touch a row another worker has since claimed.
 */
final readonly class ClaimTokenStamp implements StampInterface
{
    public function __construct(
        public string $token,
    ) {}
}
