<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Payment
 */

declare(strict_types=1);

/**
 * Null-object payment method returned when the original payment method
 * is no longer installed or available. Ensures admin pages (order view,
 * invoice, credit memo) degrade gracefully instead of crashing.
 */
class Mage_Payment_Model_Method_Unavailable extends Mage_Payment_Model_Method_Abstract
{
    #[\Override]
    protected $_code = 'unavailable';
    #[\Override]
    protected $_infoBlockType = 'payment/info';
    #[\Override]
    protected $_isGateway = false;
    #[\Override]
    protected $_canOrder = false;
    #[\Override]
    protected $_canAuthorize = false;
    #[\Override]
    protected $_canCapture = false;
    #[\Override]
    protected $_canCapturePartial = false;
    #[\Override]
    protected $_canCaptureOnce = false;
    #[\Override]
    protected $_canRefund = false;
    #[\Override]
    protected $_canRefundInvoicePartial = false;
    #[\Override]
    protected $_canVoid = false;
    #[\Override]
    protected $_canUseInternal = false;
    #[\Override]
    protected $_canUseCheckout = false;
    #[\Override]
    protected $_canFetchTransactionInfo = false;
    #[\Override]
    protected $_canReviewPayment = false;
    #[\Override]
    protected $_canCreateBillingAgreement = false;
    #[\Override]
    protected $_canManageRecurringProfiles = false;

    protected string $_originalCode = '';

    public function setOriginalCode(string $code): self
    {
        $this->_originalCode = $code;
        return $this;
    }

    #[\Override]
    public function getCode()
    {
        return $this->_originalCode ?: $this->_code;
    }

    #[\Override]
    public function getTitle()
    {
        $code = $this->_originalCode ?: $this->_code;
        return Mage::helper('payment')->__('%s (unavailable)', $code);
    }

    #[\Override]
    public function canEdit()
    {
        return false;
    }

    #[\Override]
    public function isAvailable($quote = null)
    {
        return false;
    }
}
