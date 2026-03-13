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
        $tokenId = $resource['id'] ?? '';
        $customerId = $resource['customer']['id'] ?? $resource['metadata']['order_id'] ?? null;

        $this->_log("VaultTokenCreated: token {$tokenId} created" . ($customerId ? " for customer {$customerId}" : ''));
    }
}
