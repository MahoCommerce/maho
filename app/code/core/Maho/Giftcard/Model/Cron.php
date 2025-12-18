<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Model_Cron
{
    /**
     * Process scheduled gift card emails
     * Runs every 5 minutes
     *
     * @return void
     */
    public function processScheduledEmails(): void
    {
        try {
            // Get current time in store timezone
            $storeTimezone = Mage::getStoreConfig('general/locale/timezone');
            $currentTime = new DateTime('now', new DateTimeZone($storeTimezone));
            $currentTimeUtc = new DateTime('now', new DateTimeZone('UTC'));

            // Process scheduled emails

            // Get scheduled emails that are due (using store timezone)
            $collection = Mage::getResourceModel('maho_giftcard/scheduled_email_collection')
                ->addFieldToFilter('status', Maho_Giftcard_Model_Scheduled_Email::STATUS_PENDING)
                ->addFieldToFilter('scheduled_at', ['lteq' => $currentTimeUtc->format('Y-m-d H:i:s')])
                ->setPageSize(50); // Process max 50 per run

            $processed = 0;
            $failed = 0;

            foreach ($collection as $scheduledEmail) {
                try {
                    if ($scheduledEmail->process()) {
                        $processed++;
                        // Email sent successfully
                    } else {
                        $failed++;
                    }
                } catch (Exception $e) {
                    $failed++;
                    Mage::logException($e);
                }
            }

            if ($processed > 0 || $failed > 0) {
                // Processing complete
            }
        } catch (Exception $e) {
            Mage::logException($e);
            // Error logged via logException above
        }
    }

    /**
     * Mark expired gift cards
     * Runs daily at 1 AM
     *
     * @return void
     */
    public function markExpiredGiftcards(): void
    {
        try {
            // Get current time in store timezone
            $storeTimezone = Mage::getStoreConfig('general/locale/timezone');
            $currentTime = new DateTime('now', new DateTimeZone($storeTimezone));

            // Check for expired gift cards

            // Get active gift cards that have expired
            $collection = Mage::getResourceModel('maho_giftcard/giftcard_collection')
                ->addFieldToFilter('status', Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE)
                ->addFieldToFilter('expires_at', ['notnull' => true])
                ->addFieldToFilter('expires_at', ['lt' => $currentTime->format('Y-m-d H:i:s')]);

            $expired = 0;

            foreach ($collection as $giftcard) {
                try {
                    $giftcard->setStatus(Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED);
                    $giftcard->save();

                    // Add history entry
                    $history = Mage::getModel('maho_giftcard/history');
                    $history->setData([
                        'giftcard_id' => $giftcard->getId(),
                        'action' => Maho_Giftcard_Model_Giftcard::ACTION_EXPIRED,
                        'amount' => 0,
                        'balance_before' => (float) $giftcard->getBalance(),
                        'balance_after' => (float) $giftcard->getBalance(),
                        'comment' => 'Automatically expired by cron',
                        'created_at' => date('Y-m-d H:i:s'),
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
