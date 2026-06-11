<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Order_Pdf_Items_Invoice_Grouped extends Mage_Sales_Model_Order_Pdf_Items_Invoice_Default
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/invoice/items/grouped.phtml');
    }

    /**
     * Get children items for grouped product
     *
     * @return array
     */
    public function getChildrenItems()
    {
        $orderItem = $this->getOrderItem();
        if (!$orderItem) {
            return [];
        }

        $children = [];
        foreach ($orderItem->getChildrenItems() as $child) {
            if ($child->getParentItem()) {
                $children[] = $child;
            }
        }

        return $children;
    }
}
