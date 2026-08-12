<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Config;

use Attribute;

/**
 * Registers a method as a message handler for the async queue.
 *
 * Compiled at `composer dump-autoload` into `vendor/composer/maho_attributes.php`.
 * Run `composer dump-autoload` after adding, modifying, or removing this attribute.
 * The handled message class is inferred from the method's first parameter type;
 * pass `$message` explicitly only when that inference is not possible.
 * The declaring class is instantiated via `Mage::getSingleton()` at consume time.
 *
 * @see \Maho\Queue\QueueManager::dispatch()
 */
#[Attribute(Attribute::TARGET_METHOD)]
readonly class MessageHandler
{
    /**
     * @param ?string $message  FQCN of the handled message class (default: inferred from the first parameter)
     * @param int     $priority Handlers for the same message run in descending priority order
     */
    public function __construct(
        public ?string $message = null,
        public int $priority = 0,
    ) {}
}
