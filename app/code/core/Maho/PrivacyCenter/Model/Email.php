<?php

/**
 * Sends the emails of a privacy request to the email of the request.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Helper_Data as Helper;
use Maho_PrivacyCenter_Model_Request as Request;

class Maho_PrivacyCenter_Model_Email
{
    /**
     * Send the link that confirms $request. $token is the plain token of the link.
     */
    public function sendConfirmation(Request $request, #[\SensitiveParameter]
        string $token): bool
    {
        return $this->send($request, Helper::XML_PATH_CONFIRM_TEMPLATE, [
            'link_url' => $this->getUrl($request, 'privacy/request/confirmation', $token),
            'link_hours' => Mage::helper('privacycenter')->getConfirmHours($request->getStoreId()),
        ]);
    }

    /**
     * Send the link that downloads the export of $request. $token is the plain token of the link.
     */
    public function sendExportReady(Request $request, #[\SensitiveParameter]
        string $token): bool
    {
        return $this->send($request, Helper::XML_PATH_EXPORT_TEMPLATE, [
            'link_url' => $this->getUrl($request, 'privacy/request/download', $token),
            'link_hours' => Mage::helper('privacycenter')->getExportHours($request->getStoreId()),
        ]);
    }

    public function sendErasureScheduled(Request $request): bool
    {
        return $this->send($request, Helper::XML_PATH_ERASURE_SCHEDULED_TEMPLATE, [
            'scheduled_date' => $this->formatDate($request, (string) $request->getScheduledAt()),
            'needs_approval' => Mage::helper('privacycenter')->isErasureApprovalRequired($request->getStoreId()),
            'account_url' => $request->getCustomerId() ? $this->getUrl($request, 'privacy/account/deletion') : '',
        ]);
    }

    public function sendErasureCompleted(Request $request): bool
    {
        return $this->send($request, Helper::XML_PATH_ERASURE_COMPLETED_TEMPLATE, [
            'processed_date' => $this->formatDate($request, (string) $request->getProcessedAt()),
        ]);
    }

    /**
     * @param array<string, mixed> $vars
     */
    protected function send(Request $request, string $templatePath, array $vars): bool
    {
        $storeId = (int) $request->getStoreId();
        $mailTemplate = Mage::getModel('core/email_template');
        $mailTemplate->setDesignConfig(['area' => Mage_Core_Model_App_Area::AREA_FRONTEND, 'store' => $storeId])
            ->sendTransactional(
                Mage::getStoreConfig($templatePath, $storeId),
                Mage::getStoreConfig(Helper::XML_PATH_EMAIL_IDENTITY, $storeId),
                (string) $request->getEmail(),
                null,
                $vars + [
                    'request_id' => $request->getId(),
                    'request_type' => $request->getTypeLabel(),
                    'is_export' => $request->isExport(),
                    'is_erasure' => $request->isErasure(),
                    'email' => (string) $request->getEmail(),
                ],
                $storeId,
            );
        return (bool) $mailTemplate->getSentSuccess();
    }

    protected function getUrl(Request $request, string $route, #[\SensitiveParameter]
        ?string $token = null): string
    {
        $params = ['_secure' => true, '_nosid' => true];
        if ($token !== null) {
            $params['_query'] = ['token' => $token];
        }
        return Mage::app()->getStore($request->getStoreId())->getUrl($route, $params);
    }

    protected function formatDate(Request $request, string $utc): string
    {
        if ($utc === '') {
            return '';
        }
        $store = Mage::app()->getStore($request->getStoreId());
        return Mage::helper('core')->formatDate(Mage::app()->getLocale()->utcToStore($store, $utc), Mage_Core_Model_Locale::FORMAT_TYPE_LONG);
    }
}
