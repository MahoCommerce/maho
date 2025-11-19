<?php

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Paypal_Block_Express_Form extends Mage_Paypal_Block_Standard_Form
{
    /**
     * Payment method code
     * @var string
     */
    protected $_methodCode = Mage_Paypal_Model_Config::METHOD_WPP_EXPRESS;

    /**
     * Set template and redirect message
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setRedirectMessage(Mage::helper('paypal')->__('You will be redirected to the PayPal website.'));
    }

    /**
     * Set data to block
     *
     * @return Mage_Core_Block_Abstract
     */
    #[\Override]
    protected function _beforeToHtml()
    {
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();
        if (Mage::helper('paypal')->shouldAskToCreateBillingAgreement($this->_config, $customerId)
             && $this->canCreateBillingAgreement()
        ) {
            $this->setCreateBACode(Mage_Paypal_Model_Express_Checkout::PAYMENT_INFO_TRANSPORT_BILLING_AGREEMENT);
        }
        return parent::_beforeToHtml();
    }
}
