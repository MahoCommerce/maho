<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Block_Checkout_Vault_Form extends Mage_Payment_Block_Form
{
    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('maho/paypal/checkout/vault/form.phtml');
    }

    public function getCustomerTokens(): Maho_Paypal_Model_Resource_Vault_Token_Collection
    {
        $customerId = (int) Mage::getSingleton('customer/session')->getCustomerId();

        /** @var Maho_Paypal_Model_Resource_Vault_Token_Collection $collection */
        $collection = Mage::getResourceModel('maho_paypal/vault_token_collection');
        $collection->addCustomerFilter($customerId)->addActiveFilter();

        return $collection;
    }

    public function getCreateOrderUrl(): string
    {
        return Mage::getUrl('paypal/checkout/createOrder', ['_secure' => true]);
    }

    public function getApproveOrderUrl(): string
    {
        return Mage::getUrl('paypal/checkout/approveOrder', ['_secure' => true]);
    }
}
