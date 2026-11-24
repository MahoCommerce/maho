<?php

/**
 * A dimension of a report request, with the granularity of the period dimension.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

use Maho\Report\Definition\Granularity;

final class RequestDimension
{
    public function __construct(
        public readonly string $code,
        public readonly ?Granularity $granularity = null,
    ) {}

    public function toString(): string
    {
        return $this->granularity === null ? $this->code : "{$this->code}:{$this->granularity->value}";
    }
}
