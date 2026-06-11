<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Radio extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Select
{
    #[\Override]
    protected function _getOptions()
    {
        return [
            [
                'label' => Mage::helper('adminhtml')->__('Any'),
                'value' => '',
            ],
            [
                'label' => Mage::helper('adminhtml')->__('Yes'),
                'value' => 1,
            ],
            [
                'label' => Mage::helper('adminhtml')->__('No'),
                'value' => 0,
            ],
        ];
    }

    #[\Override]
    public function getCondition()
    {
        if ($this->getValue()) {
            return $this->getColumn()->getValue();
        }
        return [
            ['neq' => $this->getColumn()->getValue()],
            ['is' => new Maho\Db\Expr('NULL')],
        ];
    }
}
