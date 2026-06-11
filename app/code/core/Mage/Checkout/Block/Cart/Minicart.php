<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

class Mage_Checkout_Block_Cart_Minicart extends Mage_Checkout_Block_Cart_Abstract
{
    /**
     * Get shopping cart items qty based on configuration (summary qty or items qty)
     *
     * @return int | float
     */
    public function getSummaryCount()
    {
        if ($this->getData('summary_qty')) {
            return $this->getData('summary_qty');
        }
        return Mage::getSingleton('checkout/cart')->getSummaryQty();
    }
}
