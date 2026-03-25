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
        $storeId = $this->_resolveStoreId();
        $config = Mage::getModel('paypal/config');
        $clientId = $params['client_id'] ?? '';
        $clientSecret = $params['client_secret'] ?? '';
        $sandbox = ($params['sandbox'] ?? '1') === '1';

        if (preg_match('/^\*+$/', $clientId)) {
            $clientId = $config->getClientId($storeId);
        }
        if (preg_match('/^\*+$/', $clientSecret)) {
            $clientSecret = $config->getClientSecret($storeId);
        }

        /** @var Maho_Paypal_Model_Api_Client $client */
        $client = Mage::getModel('maho_paypal/api_client');
        $client->setExplicitCredentials($clientId, $clientSecret, $sandbox);

        return $client;
    }

    protected function _resolveStoreId(): ?int
    {
        $storeCode = $this->getRequest()->getParam('store');
        if ($storeCode) {
            return (int) Mage::app()->getStore($storeCode)->getId();
        }

        $websiteCode = $this->getRequest()->getParam('website');
        if ($websiteCode) {
            return (int) Mage::app()->getWebsite($websiteCode)->getDefaultStore()->getId();
        }

        return null;
    }
}
