<?php

/**
 * A filter of a report request.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Report;

final class RequestFilter
{
    /**
     * @param string|int|float|bool|list<string|int|float>|null $value
     */
    public function __construct(
        public readonly string $field,
        public readonly Operator $operator,
        public readonly string|int|float|bool|array|null $value = null,
    ) {}

    /** @return array{field: string, operator: string, value: string|int|float|bool|list<string|int|float>|null} */
    public function toArray(): array
    {
        return ['field' => $this->field, 'operator' => $this->operator->value, 'value' => $this->value];
    }
}
