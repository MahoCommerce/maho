<?php

/**
 * The SQL aggregate function of a report metric.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report\Definition;

enum Aggregate: string
{
    case Sum = 'sum';
    case Count = 'count';
    case CountDistinct = 'count_distinct';
    case Min = 'min';
    case Max = 'max';
    case Avg = 'avg';
}
