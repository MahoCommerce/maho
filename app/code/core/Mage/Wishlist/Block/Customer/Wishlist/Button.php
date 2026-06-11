<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

class Mage_Wishlist_Block_Customer_Wishlist_Button extends Mage_Core_Block_Template
{
    /**
     * Retrieve current wishlist
     *
     * @return Mage_Wishlist_Model_Wishlist
     */
    public function getWishlist()
    {
        return Mage::helper('wishlist')->getWishlist();
    }
}
