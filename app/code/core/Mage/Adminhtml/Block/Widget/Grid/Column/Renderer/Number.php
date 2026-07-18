<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Number extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    protected $_defaultWidth = 100;

    /**
     * Returns value of the row
     *
     * @return mixed|string
     */
    #[\Override]
    protected function _getValue(\Maho\DataObject $row)
    {
        $data = parent::_getValue($row);
        if (is_numeric($data)) {
            $value = $data * 1;
            $sign = (bool) (int) $this->getColumn()->getShowNumberSign() && ($value > 0) ? '+' : '';
            if ($sign) {
                $value = $sign . $value;
            }
            return $value ?: '0'; // fixed for showing zero in grid
        }
        return $this->getColumn()->getDefault();
    }
}
