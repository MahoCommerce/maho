<?php

/**
 * The comparison of a filter on a report field.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

enum Operator: string
{
    case Eq = 'eq';
    case Neq = 'neq';
    case In = 'in';
    case Nin = 'nin';
    case Gt = 'gt';
    case Gte = 'gte';
    case Lt = 'lt';
    case Lte = 'lte';
    case Contains = 'contains';
    case Empty = 'empty';
    case NotEmpty = 'not_empty';

    public function takesList(): bool
    {
        return $this === self::In || $this === self::Nin;
    }

    public function takesValue(): bool
    {
        return $this !== self::Empty && $this !== self::NotEmpty;
    }
}
