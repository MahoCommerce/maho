<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Creditmemo_Create_Adjustments extends Mage_Adminhtml_Block_Template
{
    /**
     * @var Mage_Sales_Model_Order_Creditmemo
     */
    protected $_source;

    /**
     * Initialize creditmemo adjustment totals
     *
     * @return $this
     */
    public function initTotals()
    {
        /** @var Mage_Adminhtml_Block_Sales_Order_Creditmemo_Totals $parent */
        $parent = $this->getParentBlock();
        $this->_source  = $parent->getSource();
        $total = new \Maho\DataObject([
            'code'      => 'agjustments',
            'block_name' => $this->getNameInLayout(),
        ]);
        $parent->removeTotal('shipping');
        $parent->removeTotal('adjustment_positive');
        $parent->removeTotal('adjustment_negative');
        $parent->addTotal($total);
        return $this;
    }

    /**
     * @return Mage_Sales_Model_Order_Creditmemo
     */
    public function getSource()
    {
        return $this->_source;
    }

    /**
     * Get credit memo shipping amount depend on configuration settings
     * @return float
     */
    public function getShippingAmount()
    {
        $config = Mage::getSingleton('tax/config');
        $source = $this->getSource();
        if ($config->displaySalesShippingInclTax($source->getOrder()->getStoreId())) {
            $shipping = $source->getBaseShippingInclTax();
        } else {
            $shipping = $source->getBaseShippingAmount();
        }
        return Mage::app()->getStore()->roundPrice($shipping) * 1;
    }

    /**
     * Get label for shipping total based on configuration settings
     * @return string
     */
    public function getShippingLabel()
    {
        $config = Mage::getSingleton('tax/config');
        $source = $this->getSource();
        if ($config->displaySalesShippingInclTax($source->getOrder()->getStoreId())) {
            $label = $this->helper('sales')->__('Refund Shipping (Incl. Tax)');
        } elseif ($config->displaySalesShippingBoth($source->getOrder()->getStoreId())) {
            $label = $this->helper('sales')->__('Refund Shipping (Excl. Tax)');
        } else {
            $label = $this->helper('sales')->__('Refund Shipping');
        }
        return $label;
    }
}
