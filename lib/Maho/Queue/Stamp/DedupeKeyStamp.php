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
 *
 * A handler continuing its own chain passes `enforce: false`: its own row is
 * still in flight under the key and would swallow the continuation, yet the key
 * has to stay on the new message so outside dispatchers keep seeing the chain.
 *
 * With `pendingOnly: true`, only a pending message drops the new one. Use it when the
 * job reads its data at its start: a request that comes while it runs needs a new run.
 */
final readonly class DedupeKeyStamp implements StampInterface
{
    public function __construct(
        public string $key,
        public bool $enforce = true,
        public bool $pendingOnly = false,
    ) {}
}
