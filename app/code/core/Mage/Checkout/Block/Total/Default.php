<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

/**
 * Default Total Row Renderer
 *
 * @package    Mage_Checkout
 *
 * @method Mage_Sales_Model_Quote_Address_Total getTotal()
 */
class Mage_Checkout_Block_Total_Default extends Mage_Checkout_Block_Cart_Totals
{
    protected $_template = 'checkout/total/default.phtml';

    /**
     * @var Mage_Core_Model_Store
     */
    protected $_store;

    #[\Override]
    protected function _construct()
    {
        $this->setTemplate($this->_template);
        $this->_store = Mage::app()->getStore();
    }

    /**
     * Get style assigned to total object
     *
     * @return string
     */
    public function getStyle()
    {
        return $this->getTotal()->getStyle();
    }

    /**
     * @param Mage_Sales_Model_Quote_Address_Total $total
     * @return $this
     */
    public function setTotal($total)
    {
        $this->setData('total', $total);
        if ($total->getAddress()) {
            $this->_store = $total->getAddress()->getQuote()->getStore();
        }
        return $this;
    }

    /**
     * @return Mage_Core_Model_Store
     */
    public function getStore()
    {
        return $this->_store;
    }
}
