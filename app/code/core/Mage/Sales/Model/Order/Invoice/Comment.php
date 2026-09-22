<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * @method Mage_Sales_Model_Resource_Order_Invoice_Comment _getResource()
 * @method Mage_Sales_Model_Resource_Order_Invoice_Comment getResource()
 */
class Mage_Sales_Model_Order_Invoice_Comment extends Mage_Sales_Model_Abstract
{
    /**
     * Invoice instance
     *
     * @var Mage_Sales_Model_Order_Invoice
     */
    protected $_invoice;

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_invoice_comment');
    }

    /**
     * Declare invoice instance
     *
     * @return  Mage_Sales_Model_Order_Invoice_Comment
     */
    public function setInvoice(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $this->_invoice = $invoice;
        return $this;
    }

    /**
     * Retrieve invoice instance
     *
     * @return Mage_Sales_Model_Order_Invoice
     */
    public function getInvoice()
    {
        return $this->_invoice;
    }

    /**
     * Get store object
     *
     * @return Mage_Core_Model_Store
     */
    #[\Override]
    public function getStore()
    {
        if ($this->getInvoice()) {
            return $this->getInvoice()->getStore();
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

        if (!$this->getParentId() && $this->getInvoice()) {
            $this->setParentId($this->getInvoice()->getId());
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
        return $this->getData('is_customer_notified');
    }

    public function setIsCustomerNotified(?bool $value): static
    {
        return $this->setData('is_customer_notified', $value);
    }

    public function getIsVisibleOnFront(): ?bool
    {
        $value = $this->getData('is_visible_on_front');
        return $value === null ? null : (bool) $value;
    }

    public function setIsVisibleOnFront(?bool $value): static
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
