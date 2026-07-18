<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Order_Pdf_Items_Creditmemo_Default extends Mage_Sales_Model_Order_Pdf_Items_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/creditmemo/items/default.phtml');
    }

    public function getSku(): string
    {
        $item = $this->getItem();
        return $item ? $item->getSku() : '';
    }

    /**
     * Get item total including tax and excluding discount
     *
     * @return float
     */
    public function getItemTotalInclTax()
    {
        $item = $this->getItem();
        if (!$item) {
            return 0.0;
        }

        return $item->getRowTotal() + $item->getTaxAmount() + $item->getHiddenTaxAmount() - $item->getDiscountAmount();
    }
}
