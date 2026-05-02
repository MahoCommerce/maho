<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Block_Payment_Info extends Mage_Payment_Block_Info
{
    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('maho/paypal/payment/info.phtml');
    }

    #[\Override]
    protected function _prepareSpecificInformation($transport = null): \Maho\DataObject
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $info = $this->getInfo();

        $data = [];

        $paypalOrderId = $info->getAdditionalInformation('paypal_order_id');
        if ($paypalOrderId) {
            $data[Mage::helper('paypal')->__('PayPal Order ID')] = $paypalOrderId;
        }

        $payerEmail = $info->getAdditionalInformation('payer_email');
        if ($payerEmail) {
            $data[Mage::helper('paypal')->__('Payer Email')] = $payerEmail;
        }

        $payerId = $info->getAdditionalInformation('payer_id');
        if ($payerId) {
            $data[Mage::helper('paypal')->__('Payer ID')] = $payerId;
        }

        $authId = $info->getAdditionalInformation('paypal_authorization_id');
        if ($authId) {
            $data[Mage::helper('paypal')->__('Authorization ID')] = $authId;
        }

        $captureId = $info->getAdditionalInformation('paypal_capture_id');
        if ($captureId) {
            $data[Mage::helper('paypal')->__('Capture ID')] = $captureId;
        }

        if (!$this->getIsSecureMode()) {
            $avs = $info->getAdditionalInformation('avs_code');
            if ($avs) {
                $data[Mage::helper('paypal')->__('AVS Code')] = $avs;
            }

            $cvv = $info->getAdditionalInformation('cvv_code');
            if ($cvv) {
                $data[Mage::helper('paypal')->__('CVV Code')] = $cvv;
            }

            $processorCode = $info->getAdditionalInformation('processor_response_code');
            if ($processorCode) {
                $data[Mage::helper('paypal')->__('Processor Response')] = $processorCode;
            }

            $threeDSecure = $info->getAdditionalInformation('three_d_secure');
            if ($threeDSecure) {
                $data[Mage::helper('paypal')->__('3D Secure')] = is_array($threeDSecure)
                    ? Mage::helper('core')->jsonEncode($threeDSecure)
                    : $threeDSecure;
            }
        }

        $vaultLabel = $info->getAdditionalInformation('vault_label');
        if ($vaultLabel) {
            $data[Mage::helper('paypal')->__('Saved Payment')] = $vaultLabel;
        }

        return $transport->addData($data);
    }
}
