<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Block for displaying missing guest orders link
 */
class Mage_Sales_Block_Order_Missing extends Mage_Core_Block_Template
{
    /**
     * Check if the missing orders link should be displayed
     */
    public function shouldShowMissingOrdersLink(): bool
    {
        return $this->_getSalesHelper()->isCustomerEligibleForGuestOrderAssociation();
    }

    /**
     * Get sales helper instance
     */
    protected function _getSalesHelper(): Mage_Sales_Helper_Data
    {
        return Mage::helper('sales');
    }


    /**
     * Get URL for associating guest orders
     */
    public function getAssociateUrl(): string
    {
        return $this->getUrl('sales/order/associate');
    }
}
