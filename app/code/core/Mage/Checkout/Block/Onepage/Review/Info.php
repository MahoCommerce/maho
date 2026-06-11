<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

class Mage_Checkout_Block_Onepage_Review_Info extends Mage_Sales_Block_Items_Abstract
{
    /**
     * @return array
     */
    public function getItems()
    {
        return Mage::getSingleton('checkout/session')->getQuote()->getAllVisibleItems();
    }

    /**
     * @return array
     */
    public function getTotals()
    {
        return Mage::getSingleton('checkout/session')->getQuote()->getTotals();
    }
}
