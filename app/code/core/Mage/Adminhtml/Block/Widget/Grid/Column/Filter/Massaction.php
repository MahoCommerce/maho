<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
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
