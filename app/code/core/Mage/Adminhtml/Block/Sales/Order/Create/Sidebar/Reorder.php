<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Sidebar_Reorder extends Mage_Adminhtml_Block_Sales_Order_Create_Sidebar_Abstract
{
    /**
     * Storage action on selected item
     *
     * @var string
     */
    protected $_sidebarStorageAction = 'add_order_item';

    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setId('sales_order_create_sidebar_reorder');
        $this->setDataId('reorder');
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        return Mage::helper('sales')->__('Last Ordered Items');
    }

    /**
     * Retrieve last order on current website
     *
     * @return Mage_Sales_Model_Order|false
     */
    public function getLastOrder()
    {
        $storeIds = $this->getQuote()->getStore()->getWebsite()->getStoreIds();
        $collection = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('customer_id', $this->getCustomerId())
            ->addFieldToFilter('store_id', ['in' => $storeIds])
            ->setOrder('created_at', 'desc')
            ->setPageSize(1)
            ->load();
        foreach ($collection as $order) {
            return $order;
        }

        return false;
    }
    /**
     * Retrieve item collection
     *
     * @return array|false
     */
    #[\Override]
    public function getItemCollection()
    {
        if ($order = $this->getLastOrder()) {
            $items = [];
            foreach ($order->getItemsCollection() as $item) {
                if (!$item->getParentItem()) {
                    $items[] = $item;
                }
            }
            return $items;
        }
        return false;
    }

    /**
     * @return false
     */
    #[\Override]
    public function canDisplayItemQty()
    {
        return false;
    }

    /**
     * @return false
     */
    #[\Override]
    public function canRemoveItems()
    {
        return false;
    }

    /**
     * @return false
     */
    #[\Override]
    public function canDisplayPrice()
    {
        return false;
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
}
