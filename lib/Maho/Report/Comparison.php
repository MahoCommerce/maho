<?php

/**
 * The period that a report compares its period with.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

enum Comparison: string
{
    case None = 'none';
    case PreviousPeriod = 'previous_period';
    case PreviousYear = 'previous_year';
}
