<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

declare(strict_types=1);

class Mage_Wishlist_Block_Customer_Wishlist_Item_Column_Comment extends Mage_Wishlist_Block_Customer_Wishlist_Item_Column
{
    /**
     * Retrieve column javascript code
     *
     * @return string
     */
    #[\Override]
    public function getJs()
    {
        /** @var Mage_Wishlist_Helper_Data $helper */
        $helper = $this->helper('wishlist');

        return parent::getJs() . "
        function focusComment(obj) {
            if( obj.value == '" . $helper->defaultCommentString() . "' ) {
                obj.value = '';
            } else if( obj.value == '' ) {
                obj.value = '" . $helper->defaultCommentString() . "';
            }
        }
        ";
    }
}
