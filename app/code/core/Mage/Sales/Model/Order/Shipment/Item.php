<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/**
 * @method Mage_Sales_Model_Resource_Order_Shipment_Item _getResource()
 * @method Mage_Sales_Model_Resource_Order_Shipment_Item getResource()
 * @method Mage_Sales_Model_Resource_Order_Shipment_Item_Collection getCollection()
 */
class Mage_Sales_Model_Order_Shipment_Item extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected $_eventPrefix = 'sales_shipment_item';
    #[\Override]
    protected $_eventObject = 'shipment_item';

    protected $_shipment = null;
    protected $_orderItem = null;

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_shipment_item');
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
     * Declare order item instance
     *
     * @return  $this
     */
    public function setOrderItem(Mage_Sales_Model_Order_Item $item)
    {
        $this->_orderItem = $item;
        if ($this->getOrderItemId() != $item->getId()) {
            $this->setOrderItemId($item->getId());
        }
        return $this;
    }

    /**
     * Retrieve order item instance
     *
     * @return Mage_Sales_Model_Order_Item
     */
    public function getOrderItem()
    {
        if (is_null($this->_orderItem)) {
            if ($this->getShipment()) {
                $this->_orderItem = $this->getShipment()->getOrder()->getItemById($this->getOrderItemId());
            } else {
                $this->_orderItem = Mage::getModel('sales/order_item')
                    ->load($this->getOrderItemId());
            }
        }
        return $this->_orderItem;
    }

    /**
     * Declare qty
     *
     * @param float $qty
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function setQty($qty)
    {
        if ($this->getOrderItem()->getIsQtyDecimal()) {
            $qty = (float) $qty;
        } else {
            $qty = (int) $qty;
        }
        $qty = $qty > 0 ? $qty : 0;
        /**
         * Check qty availability
         */
        if ($qty <= $this->getOrderItem()->getQtyToShip() || $this->getOrderItem()->isDummy(true)) {
            $this->setData('qty', $qty);
        } else {
            Mage::throwException(
                Mage::helper('sales')->__('Invalid qty to ship for item "%s"', $this->getName()),
            );
        }
        return $this;
    }

    /**
     * Applying qty to order item
     *
     * @return $this
     */
    public function register()
    {
        $this->getOrderItem()->setQtyShipped(
            $this->getOrderItem()->getQtyShipped() + $this->getQty(),
        );
        return $this;
    }

    /**
     * Reverting qty from order item
     *
     * @return $this
     */
    public function cancel(): self
    {
        $this->getOrderItem()->setQtyShipped(
            $this->getOrderItem()->getQtyShipped() - $this->getQty(),
        );
        return $this;
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

    public function getAdditionalData(): ?string
    {
        $value = $this->getData('additional_data');
        return $value === null ? null : (string) $value;
    }

    public function setAdditionalData(?string $value): static
    {
        return $this->setData('additional_data', $value);
    }

    public function getDescription(): ?string
    {
        $value = $this->getData('description');
        return $value === null ? null : (string) $value;
    }

    public function setDescription(?string $value): static
    {
        return $this->setData('description', $value);
    }

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    public function setName(?string $value): static
    {
        return $this->setData('name', $value);
    }

    public function getOrderItemId(): ?int
    {
        $value = $this->getData('order_item_id');
        return $value === null ? null : (int) $value;
    }

    public function setOrderItemId(?int $value): static
    {
        return $this->setData('order_item_id', $value);
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

    public function getPrice(): ?float
    {
        $value = $this->getData('price');
        return $value === null ? null : (float) $value;
    }

    public function setPrice(?float $value): static
    {
        return $this->setData('price', $value);
    }

    public function getProductId(): ?int
    {
        $value = $this->getData('product_id');
        return $value === null ? null : (int) $value;
    }

    public function setProductId(?int $value): static
    {
        return $this->setData('product_id', $value);
    }

    public function getQty(): ?float
    {
        $value = $this->getData('qty');
        return $value === null ? null : (float) $value;
    }

    public function getRowTotal(): ?float
    {
        $value = $this->getData('row_total');
        return $value === null ? null : (float) $value;
    }

    public function setRowTotal(?float $value): static
    {
        return $this->setData('row_total', $value);
    }

    public function getSku(): ?string
    {
        $value = $this->getData('sku');
        return $value === null ? null : (string) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function setSku(?string $value): static
    {
        return $this->setData('sku', $value);
    }

    public function getWeight(): ?float
    {
        $value = $this->getData('weight');
        return $value === null ? null : (float) $value;
    }

    public function setWeight(?float $value): static
    {
        return $this->setData('weight', $value);
    }
}
