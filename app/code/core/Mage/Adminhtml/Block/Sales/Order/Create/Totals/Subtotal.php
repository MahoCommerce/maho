<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Totals_Subtotal extends Mage_Adminhtml_Block_Sales_Order_Create_Totals_Default
{
    /**
     * Template file path
     *
     * @var string
     */
    protected $_template = 'sales/order/create/totals/subtotal.phtml';

    /**
     * Check if we need display both sobtotals
     *
     * @return bool
     */
    public function displayBoth()
    {
        // Check without store parameter - we will get admin configuration value
        $displayBoth = Mage::getSingleton('tax/config')->displayCartSubtotalBoth();

        // If trying to display the subtotal with and without taxes, need to ensure the information is present
        if ($displayBoth) {
            // Verify that the value for 'subtotal including tax' (or excluding tax) exists
            $value = $this->getTotal()->getValueInclTax();
            $displayBoth = isset($value);
        }
        return $displayBoth;
    }
}
