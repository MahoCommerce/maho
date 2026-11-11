<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

class Mage_Wishlist_Block_Share_Email_Items extends Mage_Wishlist_Block_Abstract
{
    /**
     * Initialize template
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('wishlist/email/items.phtml');
    }

    /**
     * Show the items of $wishlist, and not the wishlist of the customer session.
     */
    public function setWishlist(Mage_Wishlist_Model_Wishlist $wishlist): static
    {
        $this->_wishlist = $wishlist;
        return $this;
    }

    #[\Override]
    protected function _getWishlist()
    {
        return $this->_wishlist ?? parent::_getWishlist();
    }

    /**
     * Retrieve Product View URL
     *
     * @param Mage_Catalog_Model_Product $product
     * @param array $additional
     * @return string
     */
    #[\Override]
    public function getProductUrl($product, $additional = [])
    {
        $additional['_store_to_url'] = true;
        return parent::getProductUrl($product, $additional);
    }

    /**
     * Retrieve URL for add product to shopping cart
     *
     * @param Mage_Catalog_Model_Product $product
     * @param array $additional
     * @return string
     */
    #[\Override]
    public function getAddToCartUrl($product, $additional = [])
    {
        return $this->getAddToCartUrlCustom($product, $additional);
    }

    /**
     * Retrieve URL of the shared wishlist page, where the recipient can add the item to the cart.
     *
     * An email cannot carry a form key, so it links to the page instead of the add-to-cart action.
     *
     * @param Mage_Wishlist_Model_Item $item
     */
    public function getSharedWishlistUrl($item): string
    {
        if ($this->_wishlist) {
            return Mage::getUrl('wishlist/shared/index', ['code' => $this->_wishlist->getSharingCode()]);
        }
        return Mage::helper('wishlist')->getSharedWishlistUrl($item);
    }

    /**
     * Check whether wishlist item has description
     *
     * @param Mage_Wishlist_Model_Item $item
     * @return bool
     */
    #[\Override]
    public function hasDescription($item)
    {
        $hasDescription = parent::hasDescription($item);
        if ($hasDescription) {
            return ($item->getDescription() !== Mage::helper('wishlist')->defaultCommentString());
        }
        return $hasDescription;
    }

    /**
     * Retrieve URL for add product to shopping cart with or without Form Key
     *
     * @param Mage_Catalog_Model_Product $product
     * @param array $additional
     * @param bool $addFormKey
     * @return string
     */
    #[\Override]
    public function getAddToCartUrlCustom($product, $additional = [], $addFormKey = true)
    {
        $additional['nocookie'] = 1;
        $additional['_store_to_url'] = true;
        return parent::getAddToCartUrlCustom($product, $additional, $addFormKey);
    }
}
