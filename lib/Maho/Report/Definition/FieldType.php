<?php

/**
 * The type of the value of a report field.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report\Definition;

enum FieldType: string
{
    case Money = 'money';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case String = 'string';
    case Id = 'id';
    case Date = 'date';
}
