<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Block_Customer_Vault extends Mage_Core_Block_Template
{
    public function getTokens(): Maho_Paypal_Model_Resource_Vault_Token_Collection
    {
        $customerId = (int) Mage::getSingleton('customer/session')->getCustomerId();

        /** @var Maho_Paypal_Model_Resource_Vault_Token_Collection $collection */
        $collection = Mage::getResourceModel('paypal/vault_token_collection');
        $collection->addCustomerFilter($customerId)->addActiveFilter();

        return $collection;
    }

    public function getDeleteUrl(int $tokenId): string
    {
        return Mage::getUrl('paypal/vault/delete', [
            'id' => $tokenId,
            '_secure' => true,
        ]);
    }
}
