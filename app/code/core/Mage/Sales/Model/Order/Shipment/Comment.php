<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * @method Mage_Sales_Model_Resource_Order_Shipment_Comment _getResource()
 * @method Mage_Sales_Model_Resource_Order_Shipment_Comment getResource()
 * @method Mage_Sales_Model_Resource_Order_Shipment_Comment_Collection getCollection()
 */
class Mage_Sales_Model_Order_Shipment_Comment extends Mage_Sales_Model_Abstract
{
    /**
     * Shipment instance
     *
     * @var Mage_Sales_Model_Order_Shipment
     */
    protected $_shipment;

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_shipment_comment');
    }

    /**
     * Declare Shipment instance
     *
     * @return  $this
     */
    public function setShipment(Mage_Sales_Model_Order_Shipment $shipment)
    {
        $this->_shipment = $shipment;
        return $this;
    }

    /**
     * Retrieve Shipment instance
     *
     * @return Mage_Sales_Model_Order_Shipment
     */
    public function getShipment()
    {
        return $this->_shipment;
    }

    /**
     * Get store object
     *
     * @return Mage_Core_Model_Store
     */
    #[\Override]
    public function getStore()
    {
        if ($this->getShipment()) {
            return $this->getShipment()->getStore();
        }
        return Mage::app()->getStore();
    }

    /**
     * Before object save
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();

        if (!$this->getParentId() && $this->getShipment()) {
            $this->setParentId($this->getShipment()->getId());
        }

        return $this;
    }

    public function getComment(): ?string
    {
        $value = $this->getData('comment');
        return $value === null ? null : (string) $value;
    }

    public function setComment(?string $value): static
    {
        return $this->setData('comment', $value);
    }

    public function getIsCustomerNotified(): ?bool
    {
        $value = $this->getData('is_customer_notified');
        return $value === null ? null : (bool) $value;
    }

    public function setIsCustomerNotified(?bool $value = true): static
    {
        return $this->setData('is_customer_notified', $value);
    }

    public function getIsVisibleOnFront(): ?bool
    {
        $value = $this->getData('is_visible_on_front');
        return $value === null ? null : (bool) $value;
    }

    public function setIsVisibleOnFront(?bool $value = true): static
    {
        return $this->setData('is_visible_on_front', $value);
    }

    public function getParentId(): ?int
    {
        $value = $this->getData('parent_id');
        return $value === null ? null : (int) $value;
    }

    public function setParentId(?int $value): static
    {
        return $this->setData('parent_id', $value);
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

}
