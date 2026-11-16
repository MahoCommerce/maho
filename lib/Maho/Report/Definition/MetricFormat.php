<?php

/**
 * How a page shows the value of a report metric.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report\Definition;

enum MetricFormat: string
{
    case Money = 'money';
    case Number = 'number';
    case Decimal = 'decimal';
    case Percent = 'percent';
    case Date = 'date';
}
