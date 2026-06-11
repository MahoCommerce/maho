<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Totals_Default extends Mage_Adminhtml_Block_Sales_Order_Create_Totals
{
    protected $_template = 'sales/order/create/totals/default.phtml';

    #[\Override]
    protected function _construct()
    {
        $this->setTemplate($this->_template);
    }

    /**
     * Retrieve quote session object
     *
     * @return Mage_Adminhtml_Model_Session_Quote
     */
    #[\Override]
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session_quote');
    }

    /**
     * Retrieve store model object
     *
     * @return Mage_Core_Model_Store
     */
    #[\Override]
    public function getStore()
    {
        return $this->_getSession()->getStore();
    }

    #[\Override]
    public function formatPrice($value)
    {
        return $this->getStore()->formatPrice($value);
    }
}
