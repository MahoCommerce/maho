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

class Mage_Adminhtml_Block_Promo_Quote_Edit_Tab_Coupons_Grid_Column_Renderer_Used extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Text
{
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $value = (int) $row->getData($this->getColumn()->getIndex());
        return empty($value) ? Mage::helper('adminhtml')->__('No') : Mage::helper('adminhtml')->__('Yes');
    }
}
