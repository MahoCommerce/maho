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

class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Currency extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    protected $_defaultWidth = 100;

    /**
     * Currency objects cache
     */
    protected static $_currencies = [];

    /**
     * Renders grid column
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        if ($data = (string) $row->getData($this->getColumn()->getIndex())) {
            $currencyCode = $this->_getCurrencyCode($row);

            if (!$currencyCode) {
                return $data;
            }

            $data = (float) $data * $this->_getRate($row);
            $sign = (bool) (int) $this->getColumn()->getShowNumberSign() && ($data > 0) ? '+' : '';
            $data = Mage::app()->getLocale()->formatCurrency($data, $currencyCode);
            return $sign . $data;
        }
        return $this->getColumn()->getDefault();
    }

    /**
     * Returns currency code, false on error
     *
     * @param \Maho\DataObject $row
     * @return string|false
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
     * Get rate for current row, 1 by default
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
