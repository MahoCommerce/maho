<?php

/**
 * A named, typed SQL expression on the rows of a report dataset.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report\Definition;

use Maho\Report\Context;

final class Field
{
    /**
     * @param string|\Closure(Context): string $expression SQL on the dataset aliases. A date field is a plain
     *        column reference, such as "main.created_at", because the local-time conversion quotes it.
     * @param list<string> $joins the aliases of the joins that the expression reads
     * @param string|null $labels the code of the label resolver for the raw values
     * @param bool $localDate the column already holds a local date, so it needs no time zone conversion
     * @param array<string|int, string> $options the label of each value, for a field with a short fixed list of values
     * @param bool $filterable whether a request can filter on the field; a metric can still aggregate it
     */
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly FieldType $type,
        private readonly string|\Closure $expression,
        public readonly array $joins = [],
        public readonly bool $personal = false,
        public readonly ?string $labels = null,
        public readonly bool $localDate = false,
        public readonly array $options = [],
        public readonly bool $filterable = true,
    ) {}

    public function sql(Context $context): string
    {
        return is_string($this->expression) ? $this->expression : ($this->expression)($context);
    }
}
