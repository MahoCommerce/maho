<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Massaction extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Checkbox
{
    #[\Override]
    public function getCondition()
    {
        if ($this->getValue()) {
            return ['in' => ($this->getColumn()->getSelected() ?: [0])];
        }
        return ['nin' => ($this->getColumn()->getSelected() ?: [0])];
    }
}
