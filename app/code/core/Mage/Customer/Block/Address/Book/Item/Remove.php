<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Customer_Block_Address_Book_Item_Remove extends Mage_Core_Block_Template
{
    public function getJs(): string
    {
        return "
        function confirmRemoveAddress() {
            return confirm('"
            . Mage::helper('core')->jsQuoteEscape(
                $this->__('Are you sure you want to delete this address?'),
            )
            . "');
        }
        ";
    }
}
