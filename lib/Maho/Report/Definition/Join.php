<?php

/**
 * A table that a report dataset joins only when a selected field reads it.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report\Definition;

use Maho\Db\Select;
use Maho\Report\Context;

final class Join
{
    /**
     * @param string|\Closure(Context): Select $table a table name, or a closure that builds a derived table
     * @param string|\Closure(Context): string $condition the ON condition
     * @param list<string> $requires the aliases of the joins that the condition reads
     * @param bool $manyRows the join has many rows for one row of the dataset, such as the categories of a product
     */
    public function __construct(
        public readonly string $alias,
        private readonly string|\Closure $table,
        private readonly string|\Closure $condition,
        public readonly JoinType $type = JoinType::Left,
        public readonly array $requires = [],
        public readonly bool $manyRows = false,
    ) {}

    public function table(Context $context): string|Select
    {
        return is_string($this->table) ? $this->table : ($this->table)($context);
    }

    public function condition(Context $context): string
    {
        return is_string($this->condition) ? $this->condition : ($this->condition)($context);
    }
}
