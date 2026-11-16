<?php

/**
 * The chart that a page draws for a report preset.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report\Definition;

enum ChartType: string
{
    case Line = 'line';
    case Bar = 'bar';
    case None = 'none';
}
