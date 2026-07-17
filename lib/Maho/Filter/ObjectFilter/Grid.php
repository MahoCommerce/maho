<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Filter\ObjectFilter;

use Maho\Filter\ObjectFilter;

class Grid extends ObjectFilter
{
    /**
     * Filter grid of \Maho\DataObjects
     */
    #[\Override]
    public function filter(\Maho\DataObject|array $grid): \Maho\DataObject|array
    {
        if (is_array($grid)) {
            $out = [];
            foreach ($grid as $i => $object) {
                $out[$i] = parent::filter($object);
            }
            return $out;
        }
        return parent::filter($grid);
    }
}
