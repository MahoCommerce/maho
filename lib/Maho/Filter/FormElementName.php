<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
