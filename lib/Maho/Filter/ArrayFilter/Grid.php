<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Filter\ArrayFilter;

use Maho\Filter\ArrayFilter;

class Grid extends ArrayFilter
{
    #[\Override]
    public function filter(array $grid): array
    {
        $out = [];
        foreach ($grid as $i => $array) {
            $out[$i] = parent::filter($array);
        }
        return $out;
    }
}
