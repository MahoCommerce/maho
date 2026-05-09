<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Resource_Vault_Token_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('paypal/vault_token');
    }

    public function addCustomerFilter(int $customerId): self
    {
        $this->addFieldToFilter('customer_id', $customerId);
        return $this;
    }

    public function addActiveFilter(): self
    {
        $this->addFieldToFilter('is_active', 1);
        return $this;
    }

    public function addPaypalTokenFilter(string $paypalTokenId): self
    {
        $this->addFieldToFilter('paypal_token_id_hash', Maho_Paypal_Model_Resource_Vault_Token::hashTokenId($paypalTokenId));
        return $this;
    }

    #[\Override]
    protected function _afterLoad(): self
    {
        parent::_afterLoad();
        $helper = Mage::helper('core');
        foreach ($this->_items as $item) {
            $encrypted = $item->getData('paypal_token_id');
            if ($encrypted !== null && $encrypted !== '') {
                $item->setData('paypal_token_id', $helper->decrypt($encrypted));
            }
        }
        return $this;
    }
}
