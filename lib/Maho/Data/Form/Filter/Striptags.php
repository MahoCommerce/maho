<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Data\Form\Filter;

use Maho\Data\Form\Filter\FilterInterface;

class Striptags implements FilterInterface
{
    /**
     * Returns the result of filtering $value
     *
     * @param string $value
     * @return string
     */
    #[\Override]
    public function inputFilter($value)
    {
        return strip_tags($value);
    }

    /**
     * Returns the result of filtering $value
     *
     * @param string $value
     * @return string
     */
    #[\Override]
    public function outputFilter($value)
    {
        return $value;
    }
}
