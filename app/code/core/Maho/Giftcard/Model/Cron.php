<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

class Maho_Giftcard_Model_Cron
{
    #[Maho\Config\CronJob('giftcard_process_scheduled_emails', schedule: '*/5 * * * *')]
    public function processScheduledEmails(): void
    {
        /** @var Maho_Giftcard_Service_Giftcard $service */
        $service = Mage::getService('giftcard/giftcard');
        $service->sendScheduledEmails();
    }

    /**
     * Mark expired gift cards
     * Runs daily at 1 AM
     */
    #[Maho\Config\CronJob('giftcard_mark_expired', schedule: '0 1 * * *')]
    public function markExpiredGiftcards(): void
    {
        try {
            // Get current time in UTC (expires_at is stored in UTC)
            $currentTimeUtc = Mage::app()->getLocale()->storeToUtc();

            // Get active gift cards that have expired
            $collection = Mage::getResourceModel('giftcard/giftcard_collection')
                ->addFieldToFilter('status', Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
                ->addFieldToFilter('expires_at', ['notnull' => true])
                ->addFieldToFilter('expires_at', ['lt' => $currentTimeUtc->format(Mage_Core_Model_Locale::DATETIME_FORMAT)]);

            $expired = 0;

            foreach ($collection as $giftcard) {
                try {
                    $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED);
                    $giftcard->save();

                    // Add history entry
                    $history = Mage::getModel('giftcard/history');
                    $history->setData([
                        'giftcard_id' => $giftcard->getId(),
                        'action' => Maho_Giftcard_Model_Giftcard::ACTION_EXPIRED,
                        'base_amount' => 0,
                        'balance_before' => (float) $giftcard->getBalance(),
                        'balance_after' => (float) $giftcard->getBalance(),
                        'comment' => 'Automatically expired by cron',
                        'created_at' => Mage::app()->getLocale()->formatDateForDb('now'),
                    ]);
                    $history->save();

                    $expired++;
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }

            if ($expired > 0) {
                // Marked gift cards as expired
            }
        } catch (Exception $e) {
            Mage::logException($e);
            // Error logged via logException above
        }
    }
}
