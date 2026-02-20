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

class Mage_Adminhtml_Block_Sales_Order_View_Messages extends Mage_Adminhtml_Block_Messages
{
    protected function _getOrder()
    {
        return Mage::registry('sales_order');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        /**
         * Check customer existing (only for non-guest orders)
         */
        $customerId = $this->_getOrder()->getCustomerId();
        if ($customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
        }

        /**
         * Check Item products existing
         */
        $productIds = [];
        foreach ($this->_getOrder()->getAllItems() as $item) {
            $productIds[] = $item->getProductId();
        }

        return parent::_prepareLayout();
    }
}
