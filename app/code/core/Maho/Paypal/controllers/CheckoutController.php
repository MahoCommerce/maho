<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_CheckoutController extends Mage_Core_Controller_Front_Action
{
    /**
     * Parse JSON request body params into the request object
     */
    #[\Override]
    public function preDispatch(): static
    {
        parent::preDispatch();

        $contentType = $this->getRequest()->getHeader('Content-Type');
        if ($contentType && str_contains($contentType, 'application/json')) {
            $body = $this->getRequest()->getRawBody();
            if ($body) {
                $data = Mage::helper('core')->jsonDecode($body);
                if (is_array($data)) {
                    $this->getRequest()->setParams($data);
                }
            }
        }

        return $this;
    }

    public function clientTokenAction(): void
    {
        $result = ['success' => false];

        try {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $storeId = $quote->getStoreId() ? (int) $quote->getStoreId() : null;

            /** @var Maho_Paypal_Model_Api_Client $client */
            $client = Mage::getModel('maho_paypal/api_client', ['store_id' => $storeId]);
            $clientToken = $client->generateClientToken();

            $result['success'] = true;
            $result['client_token'] = $clientToken;
        } catch (\Throwable $e) {
            $result['message'] = $e->getMessage();
            Mage::logException($e);
        }

        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    public function createOrderAction(): void
    {
        $result = ['success' => false];

        try {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            if (!$quote->getId() || !$quote->getItemsCount()) {
                Mage::throwException(Mage::helper('maho_paypal')->__('Cart is empty.'));
            }

            $methodCode = $this->getRequest()->getParam('method', Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT);

            /** @var Maho_Paypal_Model_Config $config */
            $config = Mage::getModel('paypal/config');
            $intent = $config->getNewPaymentAction($methodCode);
            $paypalIntent = ($intent === Maho_Paypal_Model_Config::PAYMENT_ACTION_CAPTURE) ? 'CAPTURE' : 'AUTHORIZE';

            /** @var Maho_Paypal_Model_Api_OrderBuilder $builder */
            $builder = Mage::getModel('maho_paypal/api_orderBuilder');
            $orderRequest = $builder->buildFromQuote($quote, $paypalIntent);

            /** @var Maho_Paypal_Model_Api_Client $client */
            $client = Mage::getModel('maho_paypal/api_client', ['store_id' => (int) $quote->getStoreId()]);
            $paypalOrder = $client->createOrder(['body' => $orderRequest]);

            $paypalOrderId = $paypalOrder['id'] ?? null;
            if (!$paypalOrderId) {
                Mage::throwException(Mage::helper('maho_paypal')->__('Failed to create PayPal order.'));
            }

            // Store PayPal order ID on quote payment
            $quote->getPayment()->setAdditionalInformation('paypal_order_id', $paypalOrderId);
            $quote->getPayment()->setData('paypal_order_id', $paypalOrderId);
            $quote->getPayment()->save();

            $result['success'] = true;
            $result['paypal_order_id'] = $paypalOrderId;

            $billingAddress = $quote->getBillingAddress();
            if ($billingAddress && $billingAddress->getFirstname()) {
                $street = $billingAddress->getStreet();
                $result['billing_address'] = [
                    'addressLine1' => $street[0] ?? '',
                    'addressLine2' => $street[1] ?? '',
                    'adminArea1' => (string) $billingAddress->getRegionCode(),
                    'adminArea2' => (string) $billingAddress->getCity(),
                    'countryCode' => (string) $billingAddress->getCountryId(),
                    'postalCode' => (string) $billingAddress->getPostcode(),
                ];
            }
        } catch (\Throwable $e) {
            $result['message'] = $e->getMessage();
            Mage::logException($e);
        }

        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }

    /**
     * Approve (authorize or capture) a PayPal order and place the Mage order
     */
    public function approveOrderAction(): void
    {
        $result = ['success' => false];

        try {
            $paypalOrderId = $this->getRequest()->getParam('paypal_order_id');
            if (!$paypalOrderId) {
                Mage::throwException(Mage::helper('maho_paypal')->__('Missing PayPal order ID.'));
            }

            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $methodCode = $this->getRequest()->getParam('method', Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT);

            /** @var Maho_Paypal_Model_Config $config */
            $config = Mage::getModel('paypal/config');
            $intent = $config->getNewPaymentAction($methodCode);

            /** @var Maho_Paypal_Model_Api_Client $client */
            $client = Mage::getModel('maho_paypal/api_client', ['store_id' => (int) $quote->getStoreId()]);

            // Authorize or capture the PayPal order
            if ($intent === Maho_Paypal_Model_Config::PAYMENT_ACTION_CAPTURE) {
                $paypalResult = $client->captureOrder($paypalOrderId);
            } else {
                $paypalResult = $client->authorizeOrder($paypalOrderId);
            }

            $status = $paypalResult['status'] ?? '';
            if (!in_array($status, ['COMPLETED', 'APPROVED'])) {
                Mage::throwException(Mage::helper('maho_paypal')->__('PayPal order could not be approved. Status: %s', $status));
            }

            // Set payment method on quote
            $payment = $quote->getPayment();
            $payment->setMethod($methodCode);
            $payment->setAdditionalInformation('paypal_order_id', $paypalOrderId);
            $payment->setData('paypal_order_id', $paypalOrderId);

            // Import transaction IDs
            if ($intent === Maho_Paypal_Model_Config::PAYMENT_ACTION_CAPTURE) {
                $captureId = $paypalResult['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
                if ($captureId) {
                    $payment->setAdditionalInformation('paypal_capture_id', $captureId);
                }
            } else {
                $authId = $paypalResult['purchase_units'][0]['payments']['authorizations'][0]['id'] ?? null;
                if ($authId) {
                    $payment->setAdditionalInformation('paypal_authorization_id', $authId);
                }
            }

            // Import payer info
            $payer = $paypalResult['payer'] ?? [];
            if (!empty($payer['email_address'])) {
                $payment->setAdditionalInformation('payer_email', $payer['email_address']);
            }
            if (!empty($payer['payer_id'])) {
                $payment->setAdditionalInformation('payer_id', $payer['payer_id']);
            }

            // Place the Magento order
            $quote->collectTotals();

            /** @var Mage_Checkout_Model_Type_Onepage $onepage */
            $onepage = Mage::getSingleton('checkout/type_onepage');
            $onepage->saveOrder();

            // Deactivate quote and persist to DB
            $quote->setIsActive(0);
            $quote->save();

            $result['success'] = true;
            $result['redirect_url'] = Mage::getUrl('checkout/onepage/success', ['_secure' => true]);
        } catch (\Throwable $e) {
            $result['message'] = $e->getMessage();
            Mage::logException($e);
        }

        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
}
