<?php

/**
 * The SQL join type of a report join.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report\Definition;

enum JoinType: string
{
    case Inner = 'inner';
    case Left = 'left';
}
