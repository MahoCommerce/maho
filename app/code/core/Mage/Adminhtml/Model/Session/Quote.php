<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

/**
 * Adminhtml quote session
 *
 * @package    Mage_Adminhtml
 *
 * @method bool hasCustomerId()
 */
class Mage_Adminhtml_Model_Session_Quote extends Mage_Core_Model_Session_Abstract
{
    public const XML_PATH_DEFAULT_CREATEACCOUNT_GROUP = 'customer/create_account/default_group';

    /**
     * Quote model object
     *
     * @var Mage_Sales_Model_Quote|null
     */
    protected $_quote   = null;

    /**
     * Customer mofrl object
     *
     * @var Mage_Customer_Model_Customer|null
     */
    protected $_customer = null;

    /**
     * Store model object
     *
     * @var Mage_Core_Model_Store|null
     */
    protected $_store   = null;

    /**
     * Order model object
     *
     * @var Mage_Sales_Model_Order|null
     */
    protected $_order   = null;

    public function __construct()
    {
        $this->init('adminhtml_quote');
        if (Mage::app()->isSingleStoreMode()) {
            $this->setStoreId(Mage::app()->getStore(true)->getId());
        }
    }

    /**
     * Retrieve quote model object
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        if (is_null($this->_quote)) {
            $this->_quote = Mage::getModel('sales/quote');
            if ($this->getStoreId() && $this->getQuoteId()) {
                $this->_quote->setStoreId($this->getStoreId())
                    ->load($this->getQuoteId());
            } elseif ($this->getStoreId() && $this->getCustomerIsGuest()) {
                $this->_quote->setStoreId($this->getStoreId())
                    ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID)
                    ->setCustomerIsGuest()
                    ->setIsActive(false)
                    ->save();
                $this->setQuoteId($this->_quote->getId());
            } elseif ($this->getStoreId() && $this->hasCustomerId()) {
                $this->_quote->setStoreId($this->getStoreId())
                    ->setCustomerGroupId(Mage::getStoreConfig(self::XML_PATH_DEFAULT_CREATEACCOUNT_GROUP))
                    ->assignCustomer($this->getCustomer())
                    ->setIsActive(false)
                    ->save();
                $this->setQuoteId($this->_quote->getId());
            }
            $this->_quote->setIgnoreOldQty();
            $this->_quote->setIsSuperMode();
        }
        return $this->_quote;
    }

    /**
     * Set customer model object
     * To enable quick switch of preconfigured customer
     * @return $this
     */
    public function setCustomer(Mage_Customer_Model_Customer $customer)
    {
        $this->_customer = $customer;
        return $this;
    }

    /**
     * Retrieve customer model object
     * @param bool $forceReload
     * @param bool $useSetStore
     * @return Mage_Customer_Model_Customer
     */
    public function getCustomer($forceReload = false, $useSetStore = false)
    {
        if (is_null($this->_customer) || $forceReload) {
            $this->_customer = Mage::getModel('customer/customer');
            if ($useSetStore && $this->getStore()->getId()) {
                $this->_customer->setStore($this->getStore());
            }
            if ($customerId = $this->getCustomerId()) {
                $this->_customer->load($customerId);
            }
            if ($this->getCustomerIsGuest()) {
                $this->_customer->setGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
            }
        }
        return $this->_customer;
    }

    /**
     * Retrieve store model object
     *
     * @return Mage_Core_Model_Store
     */
    public function getStore()
    {
        if (is_null($this->_store)) {
            $this->_store = Mage::app()->getStore($this->getStoreId());
            if ($currencyId = $this->getCurrencyId()) {
                // Belongs to the order, not to the operator: keep it out of the session.
                $this->_store->setRequestedCurrencyCode($currencyId);
            }
        }
        return $this->_store;
    }

    /**
     * Retrieve order model object
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (is_null($this->_order)) {
            $this->_order = Mage::getModel('sales/order');
            if ($this->getOrderId()) {
                $this->_order->load($this->getOrderId());
            }
        }
        return $this->_order;
    }

    public function getAllowQuoteItemsGiftMessage(bool $clear = false): ?array
    {
        return $this->getData('allow_quote_items_gift_message', $clear ?: null);
    }

    public function setAllowQuoteItemsGiftMessage(?array $value): static
    {
        return $this->setData('allow_quote_items_gift_message', $value);
    }

    public function getCurrencyId(bool $clear = false): ?string
    {
        $value = $this->getData('currency_id', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setCurrencyId(?string $value): static
    {
        return $this->setData('currency_id', $value);
    }

    public function getCustomerGroupId(bool $clear = false): ?int
    {
        $value = $this->getData('customer_group_id', $clear ?: null);
        return $value === null ? null : (int) $value;
    }

    public function setCustomerGroupId(?int $value): static
    {
        return $this->setData('customer_group_id', $value);
    }

    public function getCustomerId(bool $clear = false): ?int
    {
        $value = $this->getData('customer_id', $clear ?: null);
        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(?int $value): static
    {
        return $this->setData('customer_id', $value);
    }

    public function getCustomerIsGuest(bool $clear = false): ?bool
    {
        $value = $this->getData('customer_is_guest', $clear ?: null);
        return $value === null ? null : (bool) $value;
    }

    public function setCustomerIsGuest(?bool $value = true): static
    {
        return $this->setData('customer_is_guest', $value);
    }

    public function getOrderId(bool $clear = false): int|string|null
    {
        return $this->getData('order_id', $clear ?: null);
    }

    public function setOrderId(int|string|null $value): static
    {
        return $this->setData('order_id', $value);
    }

    public function getQuoteId(bool $clear = false): int|string|null
    {
        return $this->getData('quote_id', $clear ?: null);
    }

    public function setQuoteId(int|string|null $value): static
    {
        return $this->setData('quote_id', $value);
    }

    public function getReordered(bool $clear = false): int|string|null
    {
        return $this->getData('reordered', $clear ?: null);
    }

    public function setReordered(int|string|null $value): static
    {
        return $this->setData('reordered', $value);
    }

    public function getStoreId(bool $clear = false): ?int
    {
        $value = $this->getData('store_id', $clear ?: null);
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getUseOldShippingMethod(bool $clear = false): ?bool
    {
        $value = $this->getData('use_old_shipping_method', $clear ?: null);
        return $value === null ? null : (bool) $value;
    }

}
