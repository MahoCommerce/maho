<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Wishlist
 */

class Mage_Wishlist_Block_Customer_Sidebar extends Mage_Wishlist_Block_Abstract
{
    /**
     * Retrieve block title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->__('My Wishlist <small>(%d)</small>', $this->getItemCount());
    }

    /**
     * Add sidebar conditions to collection
     *
     * @param Mage_Wishlist_Model_Resource_Item_Collection $collection
     * @return $this
     */
    #[\Override]
    protected function _prepareCollection($collection)
    {
        $collection->setCurPage(1)
            ->setPageSize(3)
            ->setInStockFilter(true)
            ->setOrder('added_at');

        return $this;
    }

    /**
     * Prepare before to html
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if ($this->getItemCount()) {
            return parent::_toHtml();
        }

        return '';
    }

    /**
     * Retrieve Wishlist Product Items collection
     *
     * @return Mage_Wishlist_Model_Resource_Item_Collection
     */
    #[\Override]
    public function getWishlistItems()
    {
        if (is_null($this->_collection)) {
            $this->_collection = clone $this->_createWishlistItemCollection();
            $this->_collection->clear();
            $this->_prepareCollection($this->_collection);
        }

        return $this->_collection;
    }

    /**
     * Return wishlist items count
     *
     * @return int
     */
    public function getItemCount()
    {
        return $this->_getHelper()->getItemCount();
    }

    /**
     * Check whether user has items in his wishlist
     *
     * @return bool
     */
    #[\Override]
    public function hasWishlistItems()
    {
        return $this->getItemCount() > 0;
    }

    /**
     * Retrieve cache tags
     *
     * @return array
     */
    #[\Override]
    public function getCacheTags()
    {
        if ($this->getItemCount()) {
            $this->addModelTags($this->_getHelper()->getWishlist());
        }
        return parent::getCacheTags();
    }
}
