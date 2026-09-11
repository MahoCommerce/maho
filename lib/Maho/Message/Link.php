<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Message;

/**
 * A link inside a message. The renderer builds the anchor and escapes both parts, so message
 * text stays plain and a caller never writes markup.
 */
final readonly class Link
{
    public function __construct(
        public string $label,
        public string $url,
    ) {}
}
