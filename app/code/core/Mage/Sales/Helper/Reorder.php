<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Helper_Reorder extends Mage_Core_Helper_Data
{
    public const XML_PATH_SALES_REORDER_ALLOW = 'sales/reorder/allow';

    #[\Override]
    protected $_moduleName = 'Mage_Sales';

    /**
     * @return bool
     */
    public function isAllow()
    {
        return $this->isAllowed();
    }

    /**
     * Check if reorder is allowed for given store
     *
     * @param Mage_Core_Model_Store|int|null $store
     * @return bool
     */
    public function isAllowed($store = null)
    {
        if (Mage::getStoreConfig(self::XML_PATH_SALES_REORDER_ALLOW, $store)) {
            return true;
        }
        return false;
    }

    /**
     * @return bool
     * @see Mage_Sales_Service_Order::canReorder()
     */
    public function canReorder(Mage_Sales_Model_Order $order)
    {
        $actor = Mage::app()->getStore()->isAdmin() ? Mage_Checkout_Model_Actor::Staff : Mage_Checkout_Model_Actor::Customer;
        return Mage::getService('sales/order')->canReorder($order, $actor);
    }
}
