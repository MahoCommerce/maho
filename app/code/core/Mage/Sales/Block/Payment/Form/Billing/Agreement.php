<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Block_Payment_Form_Billing_Agreement extends Mage_Payment_Block_Form
{
    /**
     * Set custom template
     *
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/payment/form/billing/agreement.phtml');
        $this->setTransportName(Mage_Sales_Model_Payment_Method_Billing_AgreementAbstract::TRANSPORT_BILLING_AGREEMENT_ID);
    }

    /**
     * Retrieve available customer billing agreements
     *
     * @return array
     */
    public function getBillingAgreements()
    {
        $data = [];
        $quote = $this->getParentBlock()->getQuote();
        if (!$quote || !$quote->getCustomer()) {
            return $data;
        }
        $collection = Mage::getModel('sales/billing_agreement')->getAvailableCustomerBillingAgreements(
            $quote->getCustomer()->getId(),
        );

        foreach ($collection as $item) {
            $data[$item->getId()] = $item->getReferenceId();
        }
        return $data;
    }
}
