<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Sidebar_Wishlist extends Mage_Adminhtml_Block_Sales_Order_Create_Sidebar_Abstract
{
    /**
     * Storage action on selected item
     *
     * @var string
     */
    protected $_sidebarStorageAction = 'add_wishlist_item';

    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setId('sales_order_create_sidebar_wishlist');
        $this->setDataId('wishlist');
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        return Mage::helper('sales')->__('Wishlist');
    }

    /**
     * Retrieve item collection
     *
     * @return mixed
     */
    #[\Override]
    public function getItemCollection()
    {
        $collection = $this->getData('item_collection');
        if (is_null($collection)) {
            $collection = $this->getCreateOrderModel()->getCustomerWishlist(true);
            if ($collection) {
                $collection = $collection->getItemsCollection()->load();
            }
            $this->setData('item_collection', $collection);
        }
        return $collection;
    }

    /**
     * Retrieve all items
     *
     * @return array
     */
    #[\Override]
    public function getItems()
    {
        $items = parent::getItems();
        foreach ($items as $item) {
            $product = $item->getProduct();
            $item->setName($product->getName());
            $item->setPrice($product->getFinalPrice(1));
            $item->setTypeId($product->getTypeId());
        }
        return $items;
    }

    /**
     * Retrieve product identifier linked with item
     *
     * @param   Mage_Wishlist_Model_Item $item
     * @return  int
     */
    #[\Override]
    public function getProductId($item)
    {
        return $item->getProduct()->getId();
    }

    /**
     * Retrieve identifier of block item
     *
     * @param \Maho\DataObject $item
     * @return  int
     */
    #[\Override]
    public function getIdentifierId($item)
    {
        return $item->getId();
    }

    /**
     * @return false|int
     */
    #[\Override]
    public function canDisplay()
    {
        if (!Mage::helper('wishlist')->isAllow()) {
            return false;
        }
        return parent::canDisplay();
    }

    /**
     * Retrieve possibility to display quantity column in grid of wishlist block
     *
     * @return bool
     */
    #[\Override]
    public function canDisplayItemQty()
    {
        return true;
    }
}
