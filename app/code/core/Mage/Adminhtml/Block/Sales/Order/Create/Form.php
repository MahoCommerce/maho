<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Form extends Mage_Adminhtml_Block_Sales_Order_Create_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('sales_order_create_form');
    }

    /**
     * Retrieve url for loading blocks
     * @return string
     */
    public function getLoadBlockUrl()
    {
        return $this->getUrl('*/*/loadBlock');
    }

    /**
     * Retrieve url for form submiting
     * @return string
     */
    public function getSaveUrl()
    {
        return $this->getUrl('*/*/save');
    }

    /**
     * Return true when the order has a customer or is a guest order.
     */
    protected function _hasCustomer(): bool
    {
        return $this->getCustomerId() !== null || $this->_getSession()->getCustomerIsGuest();
    }

    public function getCustomerSelectorDisplay(): string
    {
        return $this->_hasCustomer() ? 'none' : 'block';
    }

    public function getStoreSelectorDisplay(): string
    {
        return $this->_hasCustomer() && !$this->getStoreId() ? 'block' : 'none';
    }

    public function getDataSelectorDisplay(): string
    {
        return $this->_hasCustomer() && $this->getStoreId() ? 'block' : 'none';
    }

    public function getOrderDataJson(): string
    {
        $data = ['is_guest' => (bool) $this->_getSession()->getCustomerIsGuest()];
        if (!is_null($this->getCustomerId())) {
            $data['customer_id'] = $this->getCustomerId();
            $data['addresses'] = [];

            /** @var Mage_Customer_Model_Form $addressForm */
            $addressForm = Mage::getModel('customer/form')
                ->setFormCode('adminhtml_customer_address')
                ->setStore($this->getStore());
            foreach ($this->getCustomer()->getAddresses() as $address) {
                $data['addresses'][$address->getId()] = $addressForm->setEntity($address)
                    ->outputData(Mage_Customer_Model_Attribute_Data::OUTPUT_FORMAT_JSON);
            }
        }
        if (!is_null($this->getStoreId())) {
            $data['store_id'] = $this->getStoreId();
            $currencyCode = $this->getStore()->getCurrentCurrencyCode();
            $data['currency_symbol'] = Mage::app()->getLocale()->getCurrencySymbol($currencyCode);
            $data['shipping_method_reseted'] = !(bool) $this->getQuote()->getShippingAddress()->getShippingMethod();
            $data['payment_method'] = $this->getQuote()->getPayment()->getMethod();
        }
        return Mage::helper('core')->jsonEncode($data);
    }
}
