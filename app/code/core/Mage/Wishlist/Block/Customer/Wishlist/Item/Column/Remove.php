<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
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
