<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Webhook_Handler_VaultTokenCreated extends Maho_Paypal_Model_Webhook_Handler_AbstractHandler
{
    #[\Override]
    public function handle(array $payload): void
    {
        $resource = $payload['resource'] ?? [];
        $paypalTokenId = $resource['id'] ?? '';
        $customerRef = $resource['customer']['id'] ?? null;

        if (!$paypalTokenId) {
            $this->_log('VaultTokenCreated: missing token ID');
            return;
        }

        if (!$customerRef) {
            $this->_log("VaultTokenCreated: token {$paypalTokenId} has no customer reference, skipping");
            return;
        }

        // PayPal sends the Maho customer ID we passed during vault setup as a string
        if (!ctype_digit((string) $customerRef)) {
            $this->_log("VaultTokenCreated: customer reference '{$customerRef}' is not a valid numeric ID, skipping");
            return;
        }

        $customerId = (int) $customerRef;
        $customer = Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            $this->_log("VaultTokenCreated: customer {$customerId} not found, skipping");
            return;
        }

        // Check for duplicate
        /** @var Maho_Paypal_Model_Resource_Vault_Token_Collection $existing */
        $existing = Mage::getResourceModel('paypal/vault_token_collection');
        $existing->addPaypalTokenFilter($paypalTokenId);
        if ($existing->getSize() > 0) {
            $this->_log("VaultTokenCreated: token {$paypalTokenId} already exists, skipping");
            return;
        }

        $paymentSource = $resource['payment_source'] ?? [];
        $sourceType = '';
        $cardLastFour = null;
        $cardBrand = null;
        $cardExpiry = null;
        $payerEmail = null;

        if (isset($paymentSource['card'])) {
            $sourceType = 'card';
            $card = $paymentSource['card'];
            $cardLastFour = $card['last_digits'] ?? null;
            $cardBrand = $card['brand'] ?? null;
            $cardExpiry = $card['expiry'] ?? null;
        } elseif (isset($paymentSource['paypal'])) {
            $sourceType = 'paypal';
            $payerEmail = $paymentSource['paypal']['email_address'] ?? null;
        } else {
            $sourceType = array_key_first($paymentSource) ?? 'unknown';
        }

        /** @var Maho_Paypal_Model_Vault_Token $token */
        $token = Mage::getModel('paypal/vault_token');
        $token->setData([
            'customer_id' => $customerId,
            'paypal_token_id' => $paypalTokenId,
            'payment_source_type' => $sourceType,
            'card_last_four' => $cardLastFour,
            'card_brand' => $cardBrand,
            'card_expiry' => $cardExpiry,
            'payer_email' => $payerEmail,
        ]);
        $token->save();

        $this->_log("VaultTokenCreated: saved token {$paypalTokenId} for customer {$customerId} ({$sourceType})");
    }
}
