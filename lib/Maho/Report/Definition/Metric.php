<?php

/**
 * A number that a report measures: an aggregate of a field, or a formula over other metrics.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report\Definition;

final class Metric
{
    private function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly MetricFormat $format,
        public readonly ?Aggregate $aggregate,
        public readonly ?string $field,
        public readonly ?string $condition,
        public readonly ?string $formula,
        public readonly bool $hidden,
        public readonly bool $higherIsBetter,
        public readonly ?string $description,
    ) {}

    /**
     * @param string|null $field the field to aggregate; null counts the rows
     * @param string|null $condition a boolean field: only the rows where it is 1 count
     */
    public static function aggregate(
        string $code,
        string $label,
        Aggregate $aggregate,
        ?string $field,
        MetricFormat $format = MetricFormat::Number,
        ?string $condition = null,
        bool $hidden = false,
        bool $higherIsBetter = true,
        ?string $description = null,
    ): self {
        return new self($code, $label, $format, $aggregate, $field, $condition, null, $hidden, $higherIsBetter, $description);
    }

    /**
     * @param string $formula metric codes, numbers, + - * / and parentheses, such as "net_sales / orders"
     */
    public static function formula(
        string $code,
        string $label,
        string $formula,
        MetricFormat $format = MetricFormat::Number,
        bool $hidden = false,
        bool $higherIsBetter = true,
        ?string $description = null,
    ): self {
        return new self($code, $label, $format, null, null, null, $formula, $hidden, $higherIsBetter, $description);
    }

    public function isFormula(): bool
    {
        return $this->formula !== null;
    }
}
