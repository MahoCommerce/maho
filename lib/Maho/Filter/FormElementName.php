<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Filter;

class FormElementName
{
    protected bool $allowWhiteSpace = false;

    /**
     * Returns the string $value, removing all but alphabetic (including -_;[]) and digit characters
     */
    public function filter(mixed $value): string
    {
        $whiteSpace = $this->allowWhiteSpace ? '\s' : '';
        // Allow form element specific characters: []_;-
        $pattern = '/[^a-zA-Z0-9\[\];_\-' . $whiteSpace . ']/';
        return preg_replace($pattern, '', (string) $value);
    }
}
