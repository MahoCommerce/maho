<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Review_Grid_Filter_Type extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Select
{
    /**
     * @return array
     */
    #[\Override]
    protected function _getOptions()
    {
        return [
            ['label' => '', 'value' => ''],
            ['label' => Mage::helper('review')->__('Administrator'), 'value' => 1],
            ['label' => Mage::helper('review')->__('Customer'), 'value' => 2],
            ['label' => Mage::helper('review')->__('Guest'), 'value' => 3],
        ];
    }

    /**
     * @return int
     */
    #[\Override]
    public function getCondition()
    {
        if ($this->getValue() == 1) {
            return 1;
        }
        if ($this->getValue() == 2) {
            return 2;
        }
        return 3;
    }
}
