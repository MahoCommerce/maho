<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
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
