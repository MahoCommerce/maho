<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Tax_Rate_Grid_Renderer_Data extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    protected function _getValue(\Maho\DataObject $row)
    {
        $data = parent::_getValue($row);
        if ((int) $data == $data) {
            return (string) number_format((float) $data, 2);
        }
        if (!is_null($data)) {
            return $data * 1;
        }
        return $this->getColumn()->getDefault();
    }
}
