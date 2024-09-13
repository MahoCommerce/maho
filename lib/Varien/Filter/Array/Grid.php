<?php
/**
 * Maho
 *
 * @category   Varien
 * @package    Varien_Filter
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Varien_Filter_Array_Grid extends Varien_Filter_Array
{
    #[\Override]
    public function filter($grid)
    {
        $out = [];
        foreach ($grid as $i => $array) {
            $out[$i] = parent::filter($array);
        }
        return $out;
    }
}
