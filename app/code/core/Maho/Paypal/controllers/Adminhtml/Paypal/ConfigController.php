<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Adminhtml_Paypal_ConfigController extends Mage_Adminhtml_Controller_Action
{
    public const ADMIN_RESOURCE = 'system/config/payment';

    public const WEBHOOK_EVENT_TYPES = [
        'CHECKOUT.ORDER.APPROVED',
        'CHECKOUT.ORDER.COMPLETED',
        'PAYMENT.AUTHORIZATION.CREATED',
        'PAYMENT.AUTHORIZATION.VOIDED',
        'PAYMENT.CAPTURE.COMPLETED',
        'PAYMENT.CAPTURE.PENDING',
        'PAYMENT.CAPTURE.DECLINED',
        'PAYMENT.CAPTURE.REFUNDED',
        'PAYMENT.CAPTURE.REVERSED',
        'CUSTOMER.DISPUTE.CREATED',
        'CUSTOMER.DISPUTE.UPDATED',
        'CUSTOMER.DISPUTE.RESOLVED',
        'VAULT.PAYMENT-TOKEN.CREATED',
        'VAULT.PAYMENT-TOKEN.DELETED',
    ];

    public function testConnectionAction(): void
    {
        $result = ['success' => false, 'message' => ''];

        try {
            $params = $this->_getPostedCredentials();
            $client = $this->_createClientFromParams($params);

            if ($client->testConnection()) {
                $result['success'] = true;
                $result['message'] = $this->__('Connection successful.');
            } else {
                $result['message'] = $this->__('Connection failed. Please check your credentials.');
            }
        } catch (\Throwable $e) {
            $result['message'] = $this->__('Connection failed: %s', $e->getMessage());
            Mage::logException($e);
        }

        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    public function registerWebhookAction(): void
    {
        $result = ['success' => false, 'message' => ''];

        try {
            $params = $this->_getPostedCredentials();
            $client = $this->_createClientFromParams($params);

            $webhookUrl = Mage::getUrl('paypal/webhook/index', [
                '_secure' => true,
                '_nosid' => true,
            ]);

            $response = $client->createWebhook($webhookUrl, self::WEBHOOK_EVENT_TYPES);

            if (!empty($response['id'])) {
                Mage::getModel('core/config')->saveConfig(
                    'maho_paypal/credentials/webhook_id',
                    $response['id'],
                );
                Mage::getConfig()->reinit();

                $result['success'] = true;
                $result['message'] = $this->__('Webhook registered successfully. ID: %s', $response['id']);
                $result['webhook_id'] = $response['id'];
            } else {
                $result['message'] = $this->__('Failed to register webhook.');
            }
        } catch (\Throwable $e) {
            $result['message'] = $this->__('Webhook registration failed: %s', $e->getMessage());
            Mage::logException($e);
        }

        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    protected function _getPostedCredentials(): array
    {
        return [
            'client_id' => (string) $this->getRequest()->getParam('client_id'),
            'client_secret' => (string) $this->getRequest()->getParam('client_secret'),
            'sandbox' => (string) $this->getRequest()->getParam('sandbox'),
        ];
    }

    protected function _createClientFromParams(array $params): Maho_Paypal_Model_Api_Client
    {
        $clientId = $params['client_id'] ?? '';
        $clientSecret = $params['client_secret'] ?? '';
        $sandbox = ($params['sandbox'] ?? '1') === '1';

        /** @var Maho_Paypal_Model_Api_Client $client */
        $client = Mage::getModel('maho_paypal/api_client');
        $client->setExplicitCredentials($clientId, $clientSecret, $sandbox);

        return $client;
    }
}
