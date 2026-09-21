<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * Quote payment information
 *
 * @method Mage_Sales_Model_Resource_Quote_Payment _getResource()
 * @method Mage_Sales_Model_Resource_Quote_Payment getResource()
 * @method Mage_Sales_Model_Resource_Quote_Payment_Collection getCollection()
 */
class Mage_Sales_Model_Quote_Payment extends Mage_Payment_Model_Info
{
    #[\Override]
    protected $_eventPrefix = 'sales_quote_payment';
    #[\Override]
    protected $_eventObject = 'payment';

    protected $_quote;

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/quote_payment');
    }

    /**
     * Declare quote model instance
     *
     * @return  $this
     */
    public function setQuote(Mage_Sales_Model_Quote $quote)
    {
        $this->_quote = $quote;
        if ($this->getQuoteId() != $quote->getId()) {
            $this->setQuoteId($quote->getId());
        }
        return $this;
    }

    /**
     * Retrieve quote model instance
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->_quote;
    }

    /**
     * Import data array to payment method object,
     * Method calls quote totals collect because payment method availability
     * can be related to quote totals
     *
     * @throws  Mage_Core_Exception
     * @return  $this
     */
    public function importData(array $data)
    {
        $data = new \Maho\DataObject($data);
        Mage::dispatchEvent(
            $this->_eventPrefix . '_import_data_before',
            [
                $this->_eventObject => $this,
                'input' => $data,
            ],
        );

        $this->setMethod($data->getMethod());
        $method = $this->getMethodInstance();

        /**
         * Payment availability related with quote totals.
         * We have to recollect quote totals before checking
         */
        $this->getQuote()->collectTotals();

        // Fail closed: a caller that passes no checks gets the full set for its scope
        $checks = $data->getChecks() ?? Mage_Payment_Model_Method_Abstract::checksForCurrentScope();
        if (!$method->isAvailable($this->getQuote())
            || !$method->isApplicableToQuote($this->getQuote(), $checks)
        ) {
            Mage::throwException(Mage::helper('sales')->__('The requested Payment Method is not available.'));
        }

        $method->assignData($data);
        /*
        * validating the payment data
        */
        $method->validate();
        return $this;
    }

    #[\Override]
    protected function _beforeSave()
    {
        if ($this->getQuote()) {
            $this->setQuoteId($this->getQuote()->getId());
        }
        try {
            $method = $this->getMethodInstance();
        } catch (Mage_Core_Exception) {
            return parent::_beforeSave();
        }
        $method->prepareSave();
        return parent::_beforeSave();
    }

    /**
     * Checkout redirect URL getter
     *
     * @return string
     */
    public function getCheckoutRedirectUrl()
    {
        $method = $this->getMethodInstance();
        if ($method) {
            return $method->getCheckoutRedirectUrl();
        }
        return '';
    }

    /**
     * Checkout order place redirect URL getter
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        $method = $this->getMethodInstance();
        if ($method) {
            return $method->getOrderPlaceRedirectUrl();
        }
        return '';
    }

    /**
     * Retrieve payment method model object
     *
     * @return Mage_Payment_Model_Method_Abstract
     */
    #[\Override]
    public function getMethodInstance()
    {
        $method = parent::getMethodInstance();
        return $method->setStore($this->getQuote()->getStore());
    }

    #[\Override]
    public function getAdditionalData(): ?string
    {
        $value = $this->getData('additional_data');
        return $value === null ? null : (string) $value;
    }

    #[\Override]
    public function setAdditionalData(?string $value): static
    {
        return $this->setData('additional_data', $value);
    }

    #[\Override]
    public function setCcCid(?string $value): static
    {
        return $this->setData('cc_cid', $value);
    }

    #[\Override]
    public function getCcCidEnc(): ?string
    {
        $value = $this->getData('cc_cid_enc');
        return $value === null ? null : (string) $value;
    }

    public function setCcCidEnc(?string $value): static
    {
        return $this->setData('cc_cid_enc', $value);
    }

    #[\Override]
    public function getCcExpMonth(): ?string
    {
        $value = $this->getData('cc_exp_month');
        return $value === null ? null : (string) $value;
    }

    #[\Override]
    public function setCcExpMonth(?string $value): static
    {
        return $this->setData('cc_exp_month', $value);
    }

    #[\Override]
    public function getCcExpYear(): ?string
    {
        $value = $this->getData('cc_exp_year');
        return $value === null ? null : (string) $value;
    }

    #[\Override]
    public function setCcExpYear(?string $value): static
    {
        return $this->setData('cc_exp_year', $value);
    }

    #[\Override]
    public function getCcLast4(): ?string
    {
        $value = $this->getData('cc_last4');
        return $value === null ? null : (string) $value;
    }

    #[\Override]
    public function setCcLast4(?string $value): static
    {
        return $this->setData('cc_last4', $value);
    }

    #[\Override]
    public function setCcNumber(?string $value): static
    {
        return $this->setData('cc_number', $value);
    }

    #[\Override]
    public function getCcNumberEnc(): ?string
    {
        $value = $this->getData('cc_number_enc');
        return $value === null ? null : (string) $value;
    }

    #[\Override]
    public function setCcNumberEnc(?string $value): static
    {
        return $this->setData('cc_number_enc', $value);
    }

    #[\Override]
    public function getCcOwner(): ?string
    {
        $value = $this->getData('cc_owner');
        return $value === null ? null : (string) $value;
    }

    #[\Override]
    public function setCcOwner(?string $value): static
    {
        return $this->setData('cc_owner', $value);
    }

    #[\Override]
    public function getCcSsIssue(): ?string
    {
        $value = $this->getData('cc_ss_issue');
        return $value === null ? null : (string) $value;
    }

    #[\Override]
    public function setCcSsIssue(?string $value): static
    {
        return $this->setData('cc_ss_issue', $value);
    }

    public function getCcSsOwner(): ?string
    {
        $value = $this->getData('cc_ss_owner');
        return $value === null ? null : (string) $value;
    }

    public function setCcSsOwner(?string $value): static
    {
        return $this->setData('cc_ss_owner', $value);
    }

    #[\Override]
    public function getCcSsStartMonth(): ?string
    {
        $value = $this->getData('cc_ss_start_month');
        return $value === null ? null : (string) $value;
    }

    #[\Override]
    public function setCcSsStartMonth(?string $value): static
    {
        return $this->setData('cc_ss_start_month', $value);
    }

    #[\Override]
    public function getCcSsStartYear(): ?string
    {
        $value = $this->getData('cc_ss_start_year');
        return $value === null ? null : (string) $value;
    }

    #[\Override]
    public function setCcSsStartYear(?string $value): static
    {
        return $this->setData('cc_ss_start_year', $value);
    }

    #[\Override]
    public function getCcType(): ?string
    {
        $value = $this->getData('cc_type');
        return $value === null ? null : (string) $value;
    }

    #[\Override]
    public function setCcType(?string $value): static
    {
        return $this->setData('cc_type', $value);
    }

    #[\Override]
    public function getCreatedAt(): ?string
    {
        $value = $this->getData('created_at');
        return $value === null ? null : (string) $value;
    }

    #[\Override]
    public function setCreatedAt(?string $value): static
    {
        return $this->setData('created_at', $value);
    }

    public function getCustomerPaymentId(): ?int
    {
        $value = $this->getData('customer_payment_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerPaymentId(?int $value): static
    {
        return $this->setData('customer_payment_id', $value);
    }

    public function getCybersourceToken(): ?string
    {
        $value = $this->getData('cybersource_token');
        return $value === null ? null : (string) $value;
    }

    public function setCybersourceToken(?string $value): static
    {
        return $this->setData('cybersource_token', $value);
    }

    public function getIdealIssuerId(): ?string
    {
        $value = $this->getData('ideal_issuer_id');
        return $value === null ? null : (string) $value;
    }

    public function setIdealIssuerId(?string $value): static
    {
        return $this->setData('ideal_issuer_id', $value);
    }

    public function getIdealIssuerList(): ?string
    {
        $value = $this->getData('ideal_issuer_list');
        return $value === null ? null : (string) $value;
    }

    public function setIdealIssuerList(?string $value): static
    {
        return $this->setData('ideal_issuer_list', $value);
    }

    #[\Override]
    public function getMethod(): ?string
    {
        $value = $this->getData('method');
        return $value === null ? null : (string) $value;
    }

    public function setMethod(?string $value): static
    {
        return $this->setData('method', $value);
    }

    public function getPaypalCorrelationId(): ?string
    {
        $value = $this->getData('paypal_correlation_id');
        return $value === null ? null : (string) $value;
    }

    public function setPaypalCorrelationId(?string $value): static
    {
        return $this->setData('paypal_correlation_id', $value);
    }

    public function getPaypalPayerId(): ?string
    {
        $value = $this->getData('paypal_payer_id');
        return $value === null ? null : (string) $value;
    }

    public function setPaypalPayerId(?string $value): static
    {
        return $this->setData('paypal_payer_id', $value);
    }

    public function getPaypalPayerStatus(): ?string
    {
        $value = $this->getData('paypal_payer_status');
        return $value === null ? null : (string) $value;
    }

    public function setPaypalPayerStatus(?string $value): static
    {
        return $this->setData('paypal_payer_status', $value);
    }

    #[\Override]
    public function getPoNumber(): ?string
    {
        $value = $this->getData('po_number');
        return $value === null ? null : (string) $value;
    }

    #[\Override]
    public function setPoNumber(?string $value): static
    {
        return $this->setData('po_number', $value);
    }

    public function getQuoteId(): ?int
    {
        $value = $this->getData('quote_id');
        return $value === null ? null : (int) $value;
    }

    public function setQuoteId(?int $value): static
    {
        return $this->setData('quote_id', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

    #[\Override]
    public function setUpdatedAt(?string $value): static
    {
        return $this->setData('updated_at', $value);
    }
}
