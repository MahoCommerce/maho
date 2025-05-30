<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method $this setCreatedAt(string $formatDate)
 * @method $this setCustomerEmail(string $value)
 * @method $this setCustomerUrl(string $value)
 * @method $this setReferenceId(string $value)
 * @method $this setStatus(string $value)
 * @method $this setUpdatedAt(string $value)
 */
class Mage_Sales_Block_Adminhtml_Billing_Agreement_View_Tab_Info extends Mage_Adminhtml_Block_Abstract implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Set custom template
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('sales/billing/agreement/view/tab/info.phtml');
    }

    /**
     * Return Tab label
     *
     * @return string
     */
    #[\Override]
    public function getTabLabel()
    {
        return $this->__('General Information');
    }

    /**
     * Return Tab title
     *
     * @return string
     */
    #[\Override]
    public function getTabTitle()
    {
        return $this->__('General Information');
    }

    /**
     * Can show tab in tabs
     *
     * @return true
     */
    #[\Override]
    public function canShowTab()
    {
        return true;
    }

    /**
     * Tab is hidden
     *
     * @return false
     */
    #[\Override]
    public function isHidden()
    {
        return false;
    }

    /**
     * Retrieve billing agreement model
     *
     * @return Mage_Sales_Model_Billing_Agreement
     */
    protected function _getBillingAgreement()
    {
        return Mage::registry('current_billing_agreement');
    }

    /**
     * Set data to block
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        $agreement = $this->_getBillingAgreement();
        $this->setReferenceId($agreement->getReferenceId());
        $customer = Mage::getModel('customer/customer')->load($agreement->getCustomerId());
        $this->setCustomerUrl(
            $this->getUrl('*/customer/edit', ['id' => $customer->getId()]),
        );
        $this->setCustomerEmail($customer->getEmail());
        $this->setStatus($agreement->getStatusLabel());
        $this->setCreatedAt($this->formatDate($agreement->getCreatedAt(), 'short', true));
        $this->setUpdatedAt(
            ($agreement->getUpdatedAt())
                ? $this->formatDate($agreement->getUpdatedAt(), 'short', true) : $this->__('N/A'),
        );

        return parent::_toHtml();
    }
}
