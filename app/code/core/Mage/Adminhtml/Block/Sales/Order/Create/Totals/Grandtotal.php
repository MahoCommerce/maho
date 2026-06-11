<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Totals_Grandtotal extends Mage_Adminhtml_Block_Sales_Order_Create_Totals_Default
{
    protected $_template = 'sales/order/create/totals/grandtotal.phtml';

    public function includeTax()
    {
        return Mage::getSingleton('tax/config')->displayCartTaxWithGrandTotal();
    }

    public function getTotalExclTax()
    {
        $excl = $this->getTotal()->getAddress()->getGrandTotal() - $this->getTotal()->getAddress()->getTaxAmount();
        $excl = max($excl, 0);
        return $excl;
    }
}
