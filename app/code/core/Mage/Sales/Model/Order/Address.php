<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * @method Mage_Sales_Model_Resource_Order_Address _getResource()
 * @method Mage_Sales_Model_Resource_Order_Address getResource()
 * @method Mage_Sales_Model_Resource_Order_Address_Collection getCollection()
 */
class Mage_Sales_Model_Order_Address extends Mage_Customer_Model_Address_Abstract
{
    /**
     * @var Mage_Sales_Model_Order
     */
    protected $_order;

    #[\Override]
    protected $_eventPrefix = 'sales_order_address';
    #[\Override]
    protected $_eventObject = 'address';

    /**
     * Initialize resource
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_address');
    }

    /**
     * Init mapping array of short fields to its full names
     *
     * @return $this
     */
    #[\Override]
    protected function _initOldFieldsMap()
    {
        return $this;
    }

    /**
     * Set order
     *
     * @return $this
     */
    public function setOrder(Mage_Sales_Model_Order $order)
    {
        $this->_order = $order;
        return $this;
    }

    /**
     * Get order
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (!$this->_order) {
            $this->_order = Mage::getModel('sales/order')->load($this->getParentId());
        }
        return $this->_order;
    }

    /**
     * Before object save manipulations
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();

        if (!$this->getParentId() && $this->getOrder()) {
            $this->setParentId($this->getOrder()->getId());
        }

        // Init customer address id if customer address is assigned
        if ($this->getCustomerAddress()) {
            $this->setCustomerAddressId($this->getCustomerAddress()->getId());
        }

        return $this;
    }

    public function getAddressType(): ?string
    {
        $value = $this->getData('address_type');
        return $value === null ? null : (string) $value;
    }

    public function setAddressType(?string $value): static
    {
        return $this->setData('address_type', $value);
    }

    public function getCompany(): ?string
    {
        $value = $this->getData('company');
        return $value === null ? null : (string) $value;
    }

    public function setCompany(?string $value): static
    {
        return $this->setData('company', $value);
    }

    public function getCustomerAddress(): ?Mage_Customer_Model_Address
    {
        return $this->getData('customer_address');
    }

    public function setCustomerAddress(?Mage_Customer_Model_Address $value): static
    {
        return $this->setData('customer_address', $value);
    }

    public function getCustomerAddressId(): ?int
    {
        $value = $this->getData('customer_address_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerAddressId(?int $value): static
    {
        return $this->setData('customer_address_id', $value);
    }

    public function setCustomerId(?int $value): static
    {
        return $this->setData('customer_id', $value);
    }

    public function getEmail(): ?string
    {
        $value = $this->getData('email');
        return $value === null ? null : (string) $value;
    }

    public function setEmail(?string $value): static
    {
        return $this->setData('email', $value);
    }

    public function getFax(): ?string
    {
        $value = $this->getData('fax');
        return $value === null ? null : (string) $value;
    }

    public function setFax(?string $value): static
    {
        return $this->setData('fax', $value);
    }

    public function getQuoteAddressId(): ?int
    {
        $value = $this->getData('quote_address_id');
        return $value === null ? null : (int) $value;
    }

    public function setQuoteAddressId(?int $value): static
    {
        return $this->setData('quote_address_id', $value);
    }

    public function setRegionId(?int $value): static
    {
        return $this->setData('region_id', $value);
    }

    public function getSameAsBilling(): ?bool
    {
        $value = $this->getData('same_as_billing');
        return $value === null ? null : (bool) $value;
    }

    public function setSameAsBilling(?bool $value = true): static
    {
        return $this->setData('same_as_billing', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }
}
