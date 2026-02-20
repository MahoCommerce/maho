<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Header extends Mage_Adminhtml_Block_Sales_Order_Create_Abstract
{
    #[\Override]
    protected function _toHtml()
    {
        if ($this->_getSession()->getOrder()->getId()) {
            return '<h3>' . Mage::helper('sales')->__(
                'Edit Order #%s',
                $this->escapeHtml($this->_getSession()->getOrder()->getIncrementId()),
            ) . '</h3>';
        }

        $customerId = $this->getCustomerId();
        $storeId    = $this->getStoreId();
        $out = '';
        if ($customerId && $storeId) {
            $out .= Mage::helper('sales')->__('Create New Order for %s in %s - %s', $this->getCustomer()->getName(), $this->getStore()->getWebsite()->getName(), $this->getStore()->getName());
        } elseif (!is_null($customerId) && $storeId) {
            $out .= Mage::helper('sales')->__('Create New Order for New Customer in %s - %s', $this->getStore()->getWebsite()->getName(), $this->getStore()->getName());
        } elseif ($customerId) {
            $out .= Mage::helper('sales')->__('Create New Order for %s', $this->getCustomer()->getName());
        } elseif (!is_null($customerId)) {
            $out .= Mage::helper('sales')->__('Create New Order for New Customer');
        } else {
            $out .= Mage::helper('sales')->__('Create New Order');
        }
        $out = $this->escapeHtml($out);
        $out = '<h3>' . $out . '</h3>';
        return $out;
    }
}
