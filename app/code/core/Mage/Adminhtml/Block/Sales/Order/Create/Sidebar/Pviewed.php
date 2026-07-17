<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Sidebar_Pviewed extends Mage_Adminhtml_Block_Sales_Order_Create_Sidebar_Abstract
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setId('sales_order_create_sidebar_pviewed');
        $this->setDataId('pviewed');
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        return Mage::helper('sales')->__('Recently Viewed Products');
    }

    /**
     * Retrieve item collection
     *
     * @return mixed
     */
    #[\Override]
    public function getItemCollection()
    {
        $productCollection = $this->getData('item_collection');
        if (is_null($productCollection)) {
            $stores = [];
            $website = Mage::app()->getStore($this->getStoreId())->getWebsite();
            foreach ($website->getStores() as $store) {
                $stores[] = $store->getId();
            }

            $collection = Mage::getModel('reports/event')
                ->getCollection()
                ->addStoreFilter($stores)
                ->addRecentlyFiler(Mage_Reports_Model_Event::EVENT_PRODUCT_VIEW, $this->getCustomerId(), 0);
            $productIds = [];
            foreach ($collection as $event) {
                $productIds[] = $event->getObjectId();
            }

            $productCollection = null;
            if ($productIds) {
                $productCollection = Mage::getModel('catalog/product')
                    ->getCollection()
                    ->setStoreId($this->getQuote()->getStoreId())
                    ->addStoreFilter($this->getQuote()->getStoreId())
                    ->addAttributeToSelect('name')
                    ->addAttributeToSelect('price')
                    ->addAttributeToSelect('small_image')
                    ->addIdFilter($productIds)
                    ->load();
            }
            $this->setData('item_collection', $productCollection);
        }
        return $productCollection;
    }

    /**
     * Retrieve availability removing items in block
     *
     * @return false
     */
    #[\Override]
    public function canRemoveItems()
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
