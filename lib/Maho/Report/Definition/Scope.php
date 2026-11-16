<?php

/**
 * A named filter that a report dataset always applies, such as the order states that count.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report\Definition;

use Maho\Report\Context;

final class Scope
{
    /**
     * @param string|\Closure(Context): string|null $condition SQL on the dataset aliases; null filters nothing
     * @param list<string> $joins the aliases of the joins that the condition reads
     */
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        private readonly string|\Closure|null $condition,
        public readonly array $joins = [],
    ) {}

    public function sql(Context $context): ?string
    {
        return $this->condition instanceof \Closure ? ($this->condition)($context) : $this->condition;
    }
}
