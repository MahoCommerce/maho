<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Message;

/**
 * A link inside a message. The renderer builds the anchor and escapes the label and the URL.
 * The message text stays plain text. The caller does not write HTML.
 */
final readonly class Link
{
    public function __construct(
        public string $label,
        public string $url,
    ) {}
}
