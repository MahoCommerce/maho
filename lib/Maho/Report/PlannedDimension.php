<?php

/**
 * A dimension of a plan, with its field, its granularity and its SQL alias.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

use Maho\Report\Definition\Dimension;
use Maho\Report\Definition\Field;
use Maho\Report\Definition\Granularity;

final class PlannedDimension
{
    public function __construct(
        public readonly Dimension $dimension,
        public readonly Field $field,
        public readonly ?Granularity $granularity,
        public readonly string $alias,
    ) {}
}
