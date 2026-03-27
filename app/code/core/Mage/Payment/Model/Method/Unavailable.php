<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Null-object payment method returned when the original payment method
 * is no longer installed or available. Ensures admin pages (order view,
 * invoice, credit memo) degrade gracefully instead of crashing.
 */
class Mage_Payment_Model_Method_Unavailable extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'unavailable';
    protected $_infoBlockType = 'payment/info';
    protected $_isGateway = false;
    protected $_canOrder = false;
    protected $_canAuthorize = false;
    protected $_canCapture = false;
    protected $_canCapturePartial = false;
    protected $_canCaptureOnce = false;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = false;
    protected $_canFetchTransactionInfo = false;
    protected $_canReviewPayment = false;
    protected $_canCreateBillingAgreement = false;
    protected $_canManageRecurringProfiles = false;

    protected string $_originalCode;

    public function __construct()
    {
        parent::__construct();
        $this->_originalCode = '';
    }

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
