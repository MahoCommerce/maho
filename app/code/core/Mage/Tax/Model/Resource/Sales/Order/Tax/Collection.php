<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

/**
 * @method Mage_Tax_Model_Sales_Order_Tax[] getItems()
 */

class Mage_Tax_Model_Resource_Sales_Order_Tax_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('tax/sales_order_tax');
    }

    /**
     * Retrieve order tax collection by order identifier
     *
     * @param \Maho\DataObject $order
     * @return $this
     */
    public function loadByOrder($order)
    {
        $orderId = $order->getId();
        $this->getSelect()
            ->where('main_table.order_id = ?', (int) $orderId)
            ->order('process');
        return $this->load();
    }
}
