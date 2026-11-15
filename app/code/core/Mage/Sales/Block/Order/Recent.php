<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

class Mage_Sales_Block_Order_Recent extends Mage_Core_Block_Template
{
    public function __construct()
    {
        parent::__construct();

        $orders = Mage::getService('sales/order')->addVisibleToCustomerFilter(
            Mage::getResourceModel('sales/order_collection')->addFieldToSelect('*'),
            Mage::getSingleton('customer/session')->getCustomerId(),
        )
            ->setOrder('created_at', 'desc')
            ->setPageSize(5)
            ->load();

        $this->setOrders($orders);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    public function getViewUrl($order)
    {
        return $this->getUrl('sales/order/view', ['order_id' => $order->getId()]);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    public function getTrackUrl($order)
    {
        return $this->getUrl('sales/order/track', ['order_id' => $order->getId()]);
    }

    /**
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if ($this->getOrders()->getSize() > 0) {
            return parent::_toHtml();
        }
        return '';
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    public function getReorderUrl($order)
    {
        return $this->getUrl('sales/order/reorder', ['order_id' => $order->getId()]);
    }

    public function getOrders(): ?Mage_Sales_Model_Resource_Order_Collection
    {
        return $this->getData('orders');
    }

    public function setOrders(?Mage_Sales_Model_Resource_Order_Collection $value): static
    {
        return $this->setData('orders', $value);
    }
}
