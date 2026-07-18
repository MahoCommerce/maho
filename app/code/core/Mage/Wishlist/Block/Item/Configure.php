<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

/**
 * Wishlist Item Configure block
 * Serves for configuring item on product view page
 *
 * @package    Mage_Wishlist
 * @module     Wishlist
 */
class Mage_Wishlist_Block_Item_Configure extends Mage_Core_Block_Template
{
    /**
     * Returns product being edited
     *
     * @return Mage_Catalog_Model_Product
     */
    protected function getProduct()
    {
        return Mage::registry('product');
    }

    /**
     * Returns wishlist item being configured
     *
     * @return Mage_Catalog_Model_Product|Mage_Wishlist_Model_Item
     */
    protected function getWishlistItem()
    {
        return Mage::registry('wishlist_item');
    }

    /**
     * Configure product view blocks
     */
    #[\Override]
    protected function _prepareLayout()
    {
        // Set custom add to cart url
        $block = $this->getLayout()->getBlock('product.info');
        if ($block) {
            $url = Mage::helper('wishlist')->getAddToCartUrl($this->getWishlistItem());
            $postUrl = Mage::helper('wishlist')->getAddToCartUrlCustom($this->getWishlistItem(), false);
            $block->setCustomAddToCartUrl($url);
            $block->setCustomAddToCartPostUrl($postUrl);
        }

        return parent::_prepareLayout();
    }
}
