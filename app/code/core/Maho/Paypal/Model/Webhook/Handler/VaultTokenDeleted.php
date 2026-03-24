<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Webhook_Handler_VaultTokenDeleted extends Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    #[\Override]
    public function handle(array $payload): void
    {
        $resource = $payload['resource'] ?? [];
        $tokenId = $resource['id'] ?? '';

        /** @var Maho_Paypal_Model_Resource_Vault_Token_Collection $collection */
        $collection = Mage::getResourceModel('maho_paypal/vault_token_collection');
        $collection->addFieldToFilter('paypal_token_id', $tokenId);

        /** @var Maho_Paypal_Model_Vault_Token $token */
        $token = $collection->getFirstItem();
        if ($token->getId()) {
            $token->setIsActive(0);
            $token->save();
            $this->_log("VaultTokenDeleted: deactivated token {$tokenId}");
        } else {
            $this->_log("VaultTokenDeleted: token {$tokenId} not found locally");
        }
    }
}
