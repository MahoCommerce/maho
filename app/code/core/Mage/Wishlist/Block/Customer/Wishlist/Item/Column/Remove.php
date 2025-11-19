<?php

/**
 * Maho
 *
 * @package    Mage_Wishlist
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Wishlist_Block_Customer_Wishlist_Item_Column_Remove extends Mage_Wishlist_Block_Customer_Wishlist_Item_Column
{
    /**
     * Retrieve block javascript
     *
     * @return string
     */
    #[\Override]
    public function getJs()
    {
        return parent::getJs() . "
        function confirmRemoveWishlistItem() {
            return confirm('"
            . Mage::helper('core')->jsQuoteEscape(
                $this->__('Are you sure you want to remove this product from your wishlist?'),
            )
            . "');
        }
        ";
    }
}
