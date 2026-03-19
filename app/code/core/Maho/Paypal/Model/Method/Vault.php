<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Method_Vault extends Maho_Paypal_Model_Method_Abstract
{
    protected $_code = Maho_Paypal_Model_Config::METHOD_VAULT;

    protected $_formBlockType = 'maho_paypal/checkout_vault_form';

    protected $_canUseInternal = true;

    #[\Override]
    public function isAvailable($quote = null): bool
    {
        $customerId = $quote?->getCustomerId()
            ?: Mage::getSingleton('customer/session')->getCustomerId();

        if (!$customerId) {
            return false;
        }

        /** @var Maho_Paypal_Model_Resource_Vault_Token_Collection $tokens */
        $tokens = Mage::getResourceModel('maho_paypal/vault_token_collection');
        $tokens->addCustomerFilter((int) $customerId)->addActiveFilter();

        if ($tokens->getSize() === 0) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    #[\Override]
    public function assignData($data): self
    {
        if (!($data instanceof \Maho\DataObject)) {
            $data = new \Maho\DataObject($data);
        }

        $info = $this->getInfoInstance();
        $vaultTokenId = $data->getVaultTokenId();
        if ($vaultTokenId) {
            /** @var Maho_Paypal_Model_Vault_Token $token */
            $token = Mage::getModel('maho_paypal/vault_token')->load($vaultTokenId);

            $customerId = (int) (Mage::getSingleton('customer/session')->getCustomerId()
                ?: Mage::getSingleton('adminhtml/session_quote')->getCustomerId());

            if ($token->getId() && (int) $token->getCustomerId() === $customerId) {
                $info->setAdditionalInformation('vault_token_id', $vaultTokenId);
                $info->setAdditionalInformation('paypal_vault_token', $token->getPaypalTokenId());
                $info->setAdditionalInformation('vault_label', $token->getDisplayLabel());
            }
        }

        return $this;
    }
}
