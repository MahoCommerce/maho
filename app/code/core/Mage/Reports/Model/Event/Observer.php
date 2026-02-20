<?php

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Reports_Model_Event_Observer
{
    protected $_enabledReports = true;

    /**
     * Object initialization
     */
    public function __construct()
    {
        $this->_enabledReports = Mage::helper('reports')->isReportsEnabled();
    }

    /**
     * Abstract Event obeserver logic
     *
     * Save event
     *
     * @param int $eventTypeId
     * @param int $objectId
     * @param int $subjectId
     * @param int $subtype
     * @return $this
     */
    protected function _event($eventTypeId, $objectId, $subjectId = null, $subtype = 0)
    {
        if (is_null($subjectId)) {
            if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                $customer = Mage::getSingleton('customer/session')->getCustomer();
                $subjectId = $customer->getId();
            } else {
                $subjectId = Mage::getSingleton('log/visitor')->getId();
                $subtype = 1;
            }
        }

        $eventModel = Mage::getModel('reports/event');
        $storeId    = Mage::app()->getStore()->getId();
        $eventModel
            ->setEventTypeId($eventTypeId)
            ->setObjectId($objectId)
            ->setSubjectId($subjectId)
            ->setSubtype($subtype)
            ->setStoreId($storeId);
        $eventModel->save();

        return $this;
    }

    /**
     * Customer login action
     *
     * @return $this
     */
    public function customerLogin(\Maho\Event\Observer $observer)
    {
        if (!Mage::getSingleton('customer/session')->isLoggedIn() || !$this->_enabledReports) {
            return $this;
        }

        $visitorId  = Mage::getSingleton('log/visitor')->getId();
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();
        $eventModel = Mage::getModel('reports/event');
        $eventModel->updateCustomerType($visitorId, $customerId);

        Mage::getModel('reports/product_index_compared')
            ->updateCustomerFromVisitor()
            ->calculate();
        Mage::getModel('reports/product_index_viewed')
            ->updateCustomerFromVisitor()
            ->calculate();

        return $this;
    }

    /**
     * Customer logout processing
     *
     * @return $this
     */
    public function customerLogout(\Maho\Event\Observer $observer)
    {
        if ($this->_enabledReports) {
            Mage::getModel('reports/product_index_compared')
                ->purgeVisitorByCustomer()
                ->calculate();
            Mage::getModel('reports/product_index_viewed')
                ->purgeVisitorByCustomer()
                ->calculate();
        }

        return $this;
    }

    /**
     * View Catalog Product action
     *
     * @return $this
     */
    public function catalogProductView(\Maho\Event\Observer $observer)
    {
        if (!$this->_enabledReports || !Mage::helper('reports')->isRecentlyViewedEnabled()) {
            return $this;
        }

        $productId = $observer->getEvent()->getProduct()->getId();

        Mage::getModel('reports/product_index_viewed')
            ->setProductId($productId)
            ->save()
            ->calculate();

        return $this->_event(Mage_Reports_Model_Event::EVENT_PRODUCT_VIEW, $productId);
    }

    /**
     * Remove Product from Compare Products action
     *
     * Reset count of compared products cache
     *
     * @return $this
     */
    public function catalogProductCompareRemoveProduct(\Maho\Event\Observer $observer)
    {
        if ($this->_enabledReports) {
            Mage::getModel('reports/product_index_compared')->calculate();
        }

        return $this;
    }

    /**
     * Remove All Products from Compare Products
     *
     * Reset count of compared products cache
     *
     * @return $this
     */
    public function catalogProductCompareClear(\Maho\Event\Observer $observer)
    {
        if ($this->_enabledReports) {
            Mage::getModel('reports/product_index_compared')->calculate();
        }

        return $this;
    }

    /**
     * Add Product to Compare Products List action
     *
     * Reset count of compared products cache
     *
     * @return $this
     */
    public function catalogProductCompareAddProduct(\Maho\Event\Observer $observer)
    {
        if (!$this->_enabledReports || !Mage::helper('reports')->isProductCompareEnabled()) {
            return $this;
        }

        $productId = $observer->getEvent()->getProduct()->getId();

        Mage::getModel('reports/product_index_compared')
            ->setProductId($productId)
            ->save()
            ->calculate();

        return $this->_event(Mage_Reports_Model_Event::EVENT_PRODUCT_COMPARE, $productId);
    }

    /**
     * Add product to shopping cart action
     *
     * @return $this
     */
    public function checkoutCartAddProduct(\Maho\Event\Observer $observer)
    {
        if ($this->_enabledReports) {
            /** @var Mage_Sales_Model_Quote_Item $quoteItem */
            $quoteItem = $observer->getEvent()->getItem();
            if (!$quoteItem->getId() && !$quoteItem->getParentItem()) {
                $productId = $quoteItem->getProductId();
                $this->_event(Mage_Reports_Model_Event::EVENT_PRODUCT_TO_CART, $productId);
            }
        }

        return $this;
    }

    /**
     * Add product to wishlist action
     *
     * @return $this
     */
    public function wishlistAddProduct(\Maho\Event\Observer $observer)
    {
        if (!$this->_enabledReports) {
            return $this;
        }

        return $this->_event(
            Mage_Reports_Model_Event::EVENT_PRODUCT_TO_WISHLIST,
            $observer->getEvent()->getProduct()->getId(),
        );
    }

    /**
     * Share customer wishlist action
     *
     * @return $this
     */
    public function wishlistShare(\Maho\Event\Observer $observer)
    {
        if (!$this->_enabledReports) {
            return $this;
        }

        return $this->_event(
            Mage_Reports_Model_Event::EVENT_WISHLIST_SHARE,
            $observer->getEvent()->getWishlist()->getId(),
        );
    }

    /**
     * Clean events by old visitors
     *
     * @see Global Log Clean Settings
     *
     * @return $this
     */
    public function eventClean(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Reports_Model_Event $event */
        $event = Mage::getModel('reports/event');
        $event->clean();

        Mage::getModel('reports/product_index_compared')->clean();
        Mage::getModel('reports/product_index_viewed')->clean();

        return $this;
    }
}
