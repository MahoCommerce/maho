<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_Webhook_Verifier
{
    public function verify(array $headers, string $body): bool
    {
        /** @var Maho_Paypal_Model_Config $config */
        $config = Mage::getModel('paypal/config');
        $webhookId = $config->getWebhookId();

        if (!$webhookId) {
            Mage::log('No webhook ID configured for signature verification', Mage::LOG_WARNING, 'paypal.log');
            return false;
        }

        /** @var Maho_Paypal_Model_Api_Client $client */
        $client = Mage::getModel('paypal/api_client');
        return $client->verifyWebhookSignature($headers, $body, $webhookId);
    }
}
