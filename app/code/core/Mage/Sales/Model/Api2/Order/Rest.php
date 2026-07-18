<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

abstract class Mage_Sales_Model_Api2_Order_Rest extends Mage_Sales_Model_Api2_Order
{
    /**
     * Retrieve information about specified order item
     *
     * @throws Mage_Api2_Exception
     * @return array
     */
    protected function _retrieve()
    {
        $orderId    = $this->getRequest()->getParam('id');
        $collection = $this->_getCollectionForSingleRetrieve($orderId);

        if ($this->_isPaymentMethodAllowed()) {
            $this->_addPaymentMethodInfo($collection);
        }
        if ($this->_isGiftMessageAllowed()) {
            $this->_addGiftMessageInfo($collection);
        }
        $this->_addTaxInfo($collection);

        $order = $collection->getItemById($orderId);

        if (!$order) {
            $this->_critical(self::RESOURCE_NOT_FOUND);
        }
        $orderData = $order->getData();
        $addresses = $this->_getAddresses([$orderId]);
        $items     = $this->_getItems([$orderId]);
        $comments  = $this->_getComments([$orderId]);

        if ($addresses) {
            $orderData['addresses'] = $addresses[$orderId];
        }
        if ($items) {
            $orderData['order_items'] = $items[$orderId];
        }
        if ($comments) {
            $orderData['order_comments'] = $comments[$orderId];
        }
        return $orderData;
    }
}
