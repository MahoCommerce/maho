<?php

/**
 * Maho
 *
 * @package    Mage_Wishlist
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
     * Can Display wishlist
     *
     * @deprecated after 1.6.2.0
     * @return bool
     */
    public function getCanDisplayWishlist()
    {
        return $this->_getCustomerSession()->isLoggedIn();
    }

    /**
     * Retrieve URL for removing item from wishlist
     *
     * @deprecated back compatibility alias for getItemRemoveUrl
     * @param  Mage_Wishlist_Model_Item $item
     * @return string
     */
    public function getRemoveItemUrl($item)
    {
        return $this->getItemRemoveUrl($item);
    }

    /**
     * Retrieve URL for adding product to shopping cart and remove item from wishlist
     *
     * @deprecated
     * @param  Mage_Catalog_Model_Product|Mage_Wishlist_Model_Item $product
     * @return string
     */
    public function getAddToCartItemUrl($product)
    {
        return $this->getItemAddToCartUrl($product);
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
