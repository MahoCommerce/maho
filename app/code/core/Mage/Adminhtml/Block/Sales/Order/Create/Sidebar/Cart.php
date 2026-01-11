<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Sidebar_Cart extends Mage_Adminhtml_Block_Sales_Order_Create_Sidebar_Abstract
{
    /**
     * Storage action on selected item
     *
     * @var string
     */
    protected $_sidebarStorageAction = 'add_cart_item';

    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setId('sales_order_create_sidebar_cart');
        $this->setDataId('cart');
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        return Mage::helper('sales')->__('Shopping Cart');
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
            $collection = $this->getCreateOrderModel()->getCustomerCart()->getAllVisibleItems();
            $this->setData('item_collection', $collection);
        }
        return $collection;
    }

    /**
     * @return bool
     */
    #[\Override]
    public function canDisplayItemQty()
    {
        return true;
    }

    /**
     * Retrieve identifier of block item
     *
     * @param \Maho\DataObject $item
     * @return int
     */
    #[\Override]
    public function getIdentifierId($item)
    {
        return $item->getId();
    }

    /**
     * Retrieve product identifier linked with item
     *
     * @param   Mage_Sales_Model_Quote_Item $item
     * @return  int
     */
    #[\Override]
    public function getProductId($item)
    {
        return $item->getProduct()->getId();
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $deleteAllConfirmString = Mage::helper('core')->jsQuoteEscape(
            Mage::helper('sales')->__('Are you sure you want to delete all items from shopping cart?'),
        );
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')->setData([
            'label' => Mage::helper('sales')->__('Clear Shopping Cart'),
            'onclick' => 'order.clearShoppingCart(\'' . $deleteAllConfirmString . '\')',
            'style' => 'float: right;',
        ]);
        $this->setChild('empty_customer_cart_button', $button);

        return parent::_prepareLayout();
    }
}
