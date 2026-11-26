<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

class Maho_PrivacyCenter_Model_ExportMessageHandler
{
    /**
     * @throws Throwable when the export could not be written, so the queue tries the message again
     */
    #[Maho\Config\MessageHandler]
    public function export(Maho_PrivacyCenter_Model_ExportMessage $message): void
    {
        $service = Mage::getService('privacycenter/request');
        try {
            $request = $service->getById($message->requestId);
        } catch (Mage_Core_Exception_NoSuchEntity) {
            return;
        }
        if ($request->getStatus() !== Maho_PrivacyCenter_Model_Request::STATUS_PROCESSING) {
            return;
        }
        $service->export($request);
    }
}
