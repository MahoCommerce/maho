<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

class Maho_Revocation_Model_Email
{
    /**
     * Customer receipt acknowledgement. Wording-critical: the template must confirm
     * receipt of the declaration only, never acceptance of the revocation.
     */
    public function sendReceipt(Maho_Revocation_Model_Request $request): bool
    {
        $storeId = (int) $request->getStoreId();

        $mailTemplate = Mage::getModel('core/email_template');
        $mailTemplate->setDesignConfig(['area' => Mage_Core_Model_App_Area::AREA_FRONTEND, 'store' => $storeId])
            ->sendTransactional(
                Mage::getStoreConfig(Maho_Revocation_Helper_Data::XML_PATH_RECEIVED_TEMPLATE, $storeId),
                Mage::getStoreConfig(Maho_Revocation_Helper_Data::XML_PATH_EMAIL_IDENTITY, $storeId),
                $request->getEmail(),
                $request->getCustomerName(),
                $this->_getTemplateVars($request),
                $storeId,
            );

        return (bool) $mailTemplate->getSentSuccess();
    }

    public function sendMerchantNotification(Maho_Revocation_Model_Request $request): bool
    {
        $storeId = (int) $request->getStoreId();

        $recipient = trim((string) Mage::getStoreConfig(Maho_Revocation_Helper_Data::XML_PATH_NOTIFY_EMAIL, $storeId));
        if ($recipient === '') {
            $recipient = (string) Mage::getStoreConfig('trans_email/ident_general/email', $storeId);
        }
        if ($recipient === '') {
            return false;
        }

        // Reply-To is the submitter's address so the merchant can respond directly. On
        // the public path it is unverified, but it is isValidEmail()-validated upstream
        // (no header-injection risk); a spoofed address only means a reply may reach the
        // wrong inbox, which triage handles.
        $mailTemplate = Mage::getModel('core/email_template');
        $mailTemplate->setDesignConfig(['area' => Mage_Core_Model_App_Area::AREA_FRONTEND, 'store' => $storeId])
            ->setReplyTo($request->getEmail())
            ->sendTransactional(
                Mage::getStoreConfig(Maho_Revocation_Helper_Data::XML_PATH_MERCHANT_TEMPLATE, $storeId),
                Mage::getStoreConfig(Maho_Revocation_Helper_Data::XML_PATH_EMAIL_IDENTITY, $storeId),
                $recipient,
                null,
                $this->_getTemplateVars($request),
                $storeId,
            );

        return (bool) $mailTemplate->getSentSuccess();
    }

    /**
     * @return array<string, mixed>
     */
    protected function _getTemplateVars(Maho_Revocation_Model_Request $request): array
    {
        $helper = Mage::helper('revocation');
        $store = Mage::app()->getStore($request->getStoreId());
        $locale = Mage::app()->getLocale();

        $receivedStore = $locale->utcToStore($store, $request->getReceivedAt());
        $order = $request->getOrder();

        return [
            'request' => $request,
            'request_id' => $request->getId(),
            'customer_name' => $request->getCustomerName(),
            'customer_email' => $request->getEmail(),
            'order_reference' => $request->getOrderReference(),
            'reason' => $request->getReason(),
            'received_store' => $receivedStore->format(Mage_Core_Model_Locale::DATETIME_FORMAT)
                . ' (' . $receivedStore->getTimezone()->getName() . ')',
            'received_utc' => $request->getReceivedAt() . ' (UTC)',
            'verified_label' => $request->getVerified() ? $helper->__('Yes') : $helper->__('No'),
            'order_increment_id' => $order?->getIncrementId(),
            'suppressed' => $request->getSuppressedAt() ? '1' : '',
            'store' => $store,
        ];
    }
}
