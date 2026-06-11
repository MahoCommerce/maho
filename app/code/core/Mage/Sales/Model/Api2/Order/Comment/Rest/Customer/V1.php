<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Api2_Order_Comment_Rest_Customer_V1 extends Mage_Sales_Model_Api2_Order_Comment_Rest
{
    /**
     * Load order by id
     *
     * @param int $id
     * @throws Mage_Api2_Exception
     * @return Mage_Sales_Model_Order
     */
    #[\Override]
    protected function _loadOrderById($id)
    {
        $order = parent::_loadOrderById($id);

        // Check sales order's owner
        if ($this->getApiUser()->getUserId() !== $order->getCustomerId()) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        return $order;
    }

    /**
     * Retrieve collection instances
     *
     * @return Mage_Sales_Model_Resource_Order_Status_History_Collection
     */
    #[\Override]
    protected function _getCollectionForRetrieve()
    {
        $collection = parent::_getCollectionForRetrieve();
        $collection->addFieldToFilter('is_visible_on_front', 1);

        return $collection;
    }
}
