<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Report_Grid_Column_Renderer_Currency extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Currency
{
    /**
     * Renders grid column
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $data = $row->getData($this->getColumn()->getIndex());
        $currencyCode = $this->_getCurrencyCode($row);

        if (!$currencyCode) {
            return $data;
        }

        $data = (float) $data * $this->_getRate($row);
        return Mage::app()->getLocale()->formatCurrency($data, $currencyCode);
    }
}
