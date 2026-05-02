<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_WebhookController extends Mage_Core_Controller_Front_Action
{
    public function indexAction(): void
    {
        if (!$this->getRequest()->isPost()) {
            $this->getResponse()->setHttpResponseCode(405);
            return;
        }

        $body = $this->getRequest()->getRawBody();
        $headers = $this->_getWebhookHeaders();

        try {
            /** @var Maho_Paypal_Model_Webhook_Verifier $verifier */
            $verifier = Mage::getModel('paypal/webhook_verifier');
            if (!$verifier->verify($headers, $body)) {
                Mage::log('PayPal webhook signature verification failed', Mage::LOG_WARNING, 'paypal.log');
                $this->getResponse()->setHttpResponseCode(401);
                return;
            }

            $payload = Mage::helper('core')->jsonDecode($body);

            /** @var Maho_Paypal_Model_Webhook_Processor $processor */
            $processor = Mage::getModel('paypal/webhook_processor');
            $processor->process($payload);

            $this->getResponse()->setHttpResponseCode(200);
        } catch (\Throwable $e) {
            Mage::logException($e);
            $this->getResponse()->setHttpResponseCode(500);
        }
    }

    protected function _getWebhookHeaders(): array
    {
        $headers = [];
        $headerKeys = [
            'PAYPAL-AUTH-ALGO',
            'PAYPAL-CERT-URL',
            'PAYPAL-TRANSMISSION-ID',
            'PAYPAL-TRANSMISSION-SIG',
            'PAYPAL-TRANSMISSION-TIME',
        ];

        foreach ($headerKeys as $key) {
            $headers[$key] = $this->getRequest()->getHeader($key) ?: '';
        }

        return $headers;
    }
}
