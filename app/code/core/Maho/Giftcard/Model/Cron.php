<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Model_Cron
{
    /**
     * Process scheduled gift card emails
     * Runs every 5 minutes
     */
    public function processScheduledEmails(): void
    {
        try {
            $currentTimeUtc = new DateTime('now', new DateTimeZone('UTC'));

            // Get gift cards with scheduled emails that are due
            $collection = Mage::getResourceModel('giftcard/giftcard_collection')
                ->addFieldToFilter('email_scheduled_at', ['notnull' => true])
                ->addFieldToFilter('email_scheduled_at', ['lteq' => $currentTimeUtc->format('Y-m-d H:i:s')])
                ->addFieldToFilter('email_sent_at', ['null' => true])
                ->addFieldToFilter('recipient_email', ['notnull' => true])
                ->setPageSize(50); // Process max 50 per run

            foreach ($collection as $giftcard) {
                try {
                    Mage::helper('giftcard')->sendGiftcardEmail($giftcard);
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Mark expired gift cards
     * Runs daily at 1 AM
     */
    public function markExpiredGiftcards(): void
    {
        try {
            // Get current time in UTC (expires_at is stored in UTC)
            $currentTimeUtc = Mage::app()->getLocale()->utcDate(null, null, true);

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
                        'created_at' => Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT),
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
