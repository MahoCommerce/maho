<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Feed Failure Notifier
 *
 * Sends email and/or admin inbox notifications when feed generation or upload fails.
 */
class Maho_FeedManager_Model_Notifier
{
    public const MODE_NONE = 'none';
    public const MODE_EMAIL = 'email';
    public const MODE_ADMIN = 'admin';
    public const MODE_BOTH = 'both';

    public const FREQUENCY_ALWAYS = 'always';
    public const FREQUENCY_ONCE_UNTIL_SUCCESS = 'once_until_success';

    /**
     * Send failure notification for a feed
     *
     * @param Maho_FeedManager_Model_Feed $feed
     * @param array $errors List of error messages
     * @param string $failureType Type of failure (generation, upload, timeout)
     * @return bool Whether notification was sent
     */
    public function notify(
        Maho_FeedManager_Model_Feed $feed,
        array $errors,
        string $failureType = 'generation'
    ): bool {
        $mode = $feed->getNotificationMode() ?: self::MODE_NONE;

        if ($mode === self::MODE_NONE) {
            return false;
        }

        // Check frequency - skip if already notified and using once_until_success
        $frequency = $feed->getNotificationFrequency() ?: self::FREQUENCY_ONCE_UNTIL_SUCCESS;
        if ($frequency === self::FREQUENCY_ONCE_UNTIL_SUCCESS && $feed->getNotificationSent()) {
            Mage::log(
                "FeedManager Notifier: Skipping notification for feed '{$feed->getName()}' - already notified",
                Mage::LOG_DEBUG,
            );
            return false;
        }

        $sent = false;

        try {
            if ($mode === self::MODE_EMAIL || $mode === self::MODE_BOTH) {
                $this->_sendEmail($feed, $errors, $failureType);
                $sent = true;
            }

            if ($mode === self::MODE_ADMIN || $mode === self::MODE_BOTH) {
                $this->_addAdminNotification($feed, $errors, $failureType);
                $sent = true;
            }

            // Mark notification as sent
            if ($sent) {
                $feed->setNotificationSent(1)->save();
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::log(
                "FeedManager Notifier: Failed to send notification for feed '{$feed->getName()}': {$e->getMessage()}",
                Mage::LOG_ERROR,
            );
        }

        return $sent;
    }

    /**
     * Reset notification sent flag (call on successful generation)
     */
    public function resetNotificationFlag(Maho_FeedManager_Model_Feed $feed): void
    {
        if ($feed->getNotificationSent()) {
            $feed->setNotificationSent(0)->save();
        }
    }

    /**
     * Send email notification
     */
    protected function _sendEmail(
        Maho_FeedManager_Model_Feed $feed,
        array $errors,
        string $failureType
    ): void {
        $storeId = (int) $feed->getStoreId();

        // Get recipient email
        $recipientEmail = $feed->getNotificationEmail();
        if (empty($recipientEmail)) {
            $recipientEmail = Mage::getStoreConfig('trans_email/ident_general/email', $storeId);
        }

        if (empty($recipientEmail)) {
            Mage::log(
                "FeedManager Notifier: No recipient email configured for feed '{$feed->getName()}'",
                Mage::LOG_WARNING,
            );
            return;
        }

        $recipientName = Mage::getStoreConfig('trans_email/ident_general/name', $storeId) ?: 'Store Owner';
        $storeName = Mage::getStoreConfig('general/store_information/name', $storeId)
            ?: Mage::app()->getStore($storeId)->getName();

        // Build admin log URL
        $logUrl = Mage::helper('adminhtml')->getUrl('adminhtml/feedmanager_log/index', [
            'feed_id' => $feed->getId(),
        ]);

        // Send email
        /** @var Mage_Core_Model_Email_Template $emailTemplate */
        $emailTemplate = Mage::getModel('core/email_template');
        $emailTemplate->setDesignConfig(['area' => 'adminhtml', 'store' => $storeId]);

        $emailTemplate->sendTransactional(
            'feedmanager_feed_failure',
            'general',
            $recipientEmail,
            $recipientName,
            [
                'feed_name' => $feed->getName(),
                'store_name' => $storeName,
                'failure_type' => ucfirst($failureType),
                'errors' => $errors,
                'error_list' => implode("\n", array_map(fn($e) => "• {$e}", $errors)),
                'timestamp' => Mage::helper('core')->formatDate(null, 'medium', true),
                'platform' => ucfirst($feed->getPlatform() ?: 'Custom'),
                'filename' => $feed->getFilename() . '.' . $feed->getFileFormat(),
                'admin_log_url' => $logUrl,
            ],
        );

        if ($emailTemplate->getSentSuccess()) {
            Mage::log(
                "FeedManager Notifier: Email sent to {$recipientEmail} for feed '{$feed->getName()}'",
                Mage::LOG_INFO,
            );
        }
    }

    /**
     * Add admin inbox notification
     */
    protected function _addAdminNotification(
        Maho_FeedManager_Model_Feed $feed,
        array $errors,
        string $failureType
    ): void {
        $title = Mage::helper('feedmanager')->__(
            'Feed Failed: %s',
            $feed->getName()
        );

        $description = Mage::helper('feedmanager')->__(
            '%s failed for feed "%s". Errors: %s',
            ucfirst($failureType),
            $feed->getName(),
            implode('; ', array_slice($errors, 0, 3))
        );

        if (count($errors) > 3) {
            $description .= Mage::helper('feedmanager')->__(
                ' ... and %d more errors.',
                count($errors) - 3
            );
        }

        $url = Mage::helper('adminhtml')->getUrl('adminhtml/feedmanager_log/index', [
            'feed_id' => $feed->getId(),
        ]);

        /** @var Mage_AdminNotification_Model_Inbox $inbox */
        $inbox = Mage::getModel('adminnotification/inbox');
        $inbox->addCritical($title, $description, $url);

        Mage::log(
            "FeedManager Notifier: Admin notification added for feed '{$feed->getName()}'",
            Mage::LOG_INFO,
        );
    }

    /**
     * Get available notification modes for admin dropdown
     */
    public static function getModeOptions(): array
    {
        $helper = Mage::helper('feedmanager');
        return [
            ['value' => self::MODE_NONE, 'label' => $helper->__('None')],
            ['value' => self::MODE_EMAIL, 'label' => $helper->__('Email Only')],
            ['value' => self::MODE_ADMIN, 'label' => $helper->__('Admin Inbox Only')],
            ['value' => self::MODE_BOTH, 'label' => $helper->__('Both Email and Admin Inbox')],
        ];
    }

    /**
     * Get available frequency options for admin dropdown
     */
    public static function getFrequencyOptions(): array
    {
        $helper = Mage::helper('feedmanager');
        return [
            ['value' => self::FREQUENCY_ONCE_UNTIL_SUCCESS, 'label' => $helper->__('Once Until Success')],
            ['value' => self::FREQUENCY_ALWAYS, 'label' => $helper->__('Every Failure')],
        ];
    }
}
