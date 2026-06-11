<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

abstract class Mage_Sales_Block_Order_Pdf_Abstract extends Mage_Core_Block_Pdf
{
    protected ?Mage_Sales_Model_Order $_order = null;

    /**
     * Get store from the order
     */
    #[\Override]
    public function getStore(): Mage_Core_Model_Store
    {
        return $this->_order ? $this->_order->getStore() : Mage::app()->getStore();
    }

    /**
     * Get store address from configuration
     */
    public function getStoreAddress(): string
    {
        $address = Mage::getStoreConfig('sales/identity/address', $this->getStore());
        return is_string($address) ? $address : '';
    }
}
