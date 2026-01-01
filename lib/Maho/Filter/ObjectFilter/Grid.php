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
