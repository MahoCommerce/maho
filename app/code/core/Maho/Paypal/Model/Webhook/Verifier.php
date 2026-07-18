<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
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
