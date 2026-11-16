<?php

/**
 * A field that a report can group its rows by.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report\Definition;

final class Dimension
{
    /** The code of the date dimension of a dated dataset. It reads the date field of the request. */
    public const PERIOD = 'period';

    /**
     * @param string $field the code of the field to group by; empty for the period dimension
     * @param string|null $labels the code of the label resolver; null takes the one of the field
     * @param bool $nonAdditive a row can appear under several values, so the rows do not add up to the total
     */
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly string $field,
        public readonly ?string $labels = null,
        public readonly bool $nonAdditive = false,
    ) {}

    public function isPeriod(): bool
    {
        return $this->code === self::PERIOD;
    }
}
