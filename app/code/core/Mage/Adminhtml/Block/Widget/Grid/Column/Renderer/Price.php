<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Price extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    protected $_defaultWidth = 100;
    /**
     * Currency objects cache
     */
    protected static $_currencies = [];

    /**
     * Renders grid column
     *
     * @return  string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        if ($data = $row->getData($this->getColumn()->getIndex())) {
            $currencyCode = $this->_getCurrencyCode($row);

            if (!$currencyCode) {
                return $data;
            }

            $data = (float) $data * $this->_getRate($row);
            $data = Mage::app()->getLocale()->formatCurrency($data, $currencyCode);
            return $data;
        }
        return $this->getColumn()->getDefault();
    }

    /**
     * Returns currency code for the row, false on error
     *
     * @param \Maho\DataObject $row
     * @return string|bool
     */
    protected function _getCurrencyCode($row)
    {
        if ($code = $this->getColumn()->getCurrencyCode()) {
            return $code;
        }
        if ($code = $row->getData($this->getColumn()->getCurrency())) {
            return $code;
        }
        return false;
    }

    /**
     * Returns rate for the row, 1 by default
     *
     * @param \Maho\DataObject $row
     * @return float|int
     */
    protected function _getRate($row)
    {
        if ($rate = $this->getColumn()->getRate()) {
            return (float) $rate;
        }
        if (($rateField = $this->getColumn()->getRateField()) && ($rate = $row->getData($rateField))) {
            return (float) $rate;
        }
        return 1;
    }
}
