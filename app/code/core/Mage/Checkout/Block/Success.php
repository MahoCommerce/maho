<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

/**
 * Class Mage_Checkout_Block_Success
 *
 * @package    Mage_Checkout
 *
 * @method int getLastOrderId()
 */
class Mage_Checkout_Block_Success extends Mage_Core_Block_Template
{
    /**
     * @return string
     */
    public function getRealOrderId()
    {
        $order = Mage::getModel('sales/order')->load($this->getLastOrderId());
        return $order->getIncrementId();
    }
}
