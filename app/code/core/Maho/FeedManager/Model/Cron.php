<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Feed Manager Cron Model
 *
 * Handles scheduled feed generation via Maho cron system.
 *
 * Error Handling Pattern:
 * - generateScheduledFeeds(): Catches all exceptions per-feed, continues with remaining feeds
 * - _generateFeed(): Wraps Generator call in try/catch, logs errors to system.log
 * - _uploadFeed(): Catches upload exceptions, records failure in Log model
 * - _cleanupHungFeeds(): Marks stuck feeds as failed, never throws
 * - resetHungFeed(): Returns boolean success, never throws
 */
class Maho_FeedManager_Model_Cron
{
    /**
     * Max time in minutes before a "running" feed is considered hung
     */
    public const HUNG_FEED_TIMEOUT_MINUTES = 30;

    /**
     * Generate all scheduled feeds
     *
     * Called by cron every hour (configurable in config.xml)
     */
    public function generateScheduledFeeds(): void
    {
        if (!Mage::helper('feedmanager')->isEnabled()) {
            return;
        }

        // First, clean up any hung feeds
        $this->_cleanupHungFeeds();

        $currentHour = (int) (new DateTime())->format('G'); // 0-23

        // Get all enabled feeds
        $feeds = Mage::getResourceModel('feedmanager/feed_collection')
            ->addFieldToFilter('is_enabled', 1);

        foreach ($feeds as $feed) {
            if ($this->_shouldGenerateFeed($feed, $currentHour)) {
                // Check if feed is already running
                if ($this->_isFeedRunning($feed)) {
                    Mage::log(
                        "FeedManager Cron: Skipping feed '{$feed->getName()}' - already running",
                        Mage::LOG_INFO,
                    );
                    continue;
                }
                $this->_generateFeed($feed);
            }
        }
    }

    /**
     * Check if a feed is currently running
     */
    protected function _isFeedRunning(Maho_FeedManager_Model_Feed $feed): bool
    {
        $runningLog = Mage::getResourceModel('feedmanager/log_collection')
            ->addFeedFilter((int) $feed->getId())
            ->addStatusFilter(Maho_FeedManager_Model_Log::STATUS_RUNNING)
            ->getFirstItem();

        return (bool) $runningLog->getId();
    }

    /**
     * Clean up hung feeds (running for too long)
     */
    protected function _cleanupHungFeeds(): void
    {
        $cutoffTime = (new DateTime())->modify('-' . self::HUNG_FEED_TIMEOUT_MINUTES . ' minutes')->format('Y-m-d H:i:s');

        $hungLogs = Mage::getResourceModel('feedmanager/log_collection')
            ->addStatusFilter(Maho_FeedManager_Model_Log::STATUS_RUNNING)
            ->addFieldToFilter('started_at', ['lt' => $cutoffTime]);

        foreach ($hungLogs as $log) {
            $log->setStatus(Maho_FeedManager_Model_Log::STATUS_FAILED)
                ->setCompletedAt(Mage_Core_Model_Locale::now())
                ->addError('Feed generation timed out (exceeded ' . self::HUNG_FEED_TIMEOUT_MINUTES . ' minutes)')
                ->save();

            Mage::log(
                "FeedManager: Marked hung feed log #{$log->getId()} as failed",
                Mage::LOG_WARNING,
            );

            // Send failure notification for hung feed
            $feed = Mage::getModel('feedmanager/feed')->load($log->getFeedId());
            if ($feed->getId()) {
                $notifier = new Maho_FeedManager_Model_Notifier();
                $notifier->notify($feed, ['Feed generation timed out'], 'timeout');
            }
        }
    }

    /**
     * Check and reset hung feed by ID
     *
     * @return bool True if a hung feed was reset
     */
    public function resetHungFeed(int $feedId): bool
    {
        $runningLog = Mage::getResourceModel('feedmanager/log_collection')
            ->addFeedFilter($feedId)
            ->addStatusFilter(Maho_FeedManager_Model_Log::STATUS_RUNNING)
            ->setOrder('started_at', 'DESC')
            ->getFirstItem();

        /** @var Maho_FeedManager_Model_Log $runningLog */
        if (!$runningLog->getId()) {
            return false;
        }

        $startedAt = strtotime($runningLog->getStartedAt());
        $hungThreshold = strtotime('-' . self::HUNG_FEED_TIMEOUT_MINUTES . ' minutes');

        if ($startedAt < $hungThreshold) {
            $runningLog->setStatus(Maho_FeedManager_Model_Log::STATUS_FAILED)
                ->setCompletedAt(Mage_Core_Model_Locale::now())
                ->addError('Manually reset - feed was stuck')
                ->save();
            return true;
        }

        return false;
    }

    /**
     * Check if feed should be generated at current hour
     */
    protected function _shouldGenerateFeed(Maho_FeedManager_Model_Feed $feed, int $currentHour): bool
    {
        $schedule = $feed->getSchedule();

        // No schedule means manual generation only
        if (empty($schedule)) {
            return false;
        }

        // Schedule format: comma-separated hours (e.g., "0,6,12,18" for every 6 hours)
        // Or special values: "hourly", "daily", "twice_daily"
        return match ($schedule) {
            'hourly' => true,
            'daily' => $currentHour === 0,
            'twice_daily' => $currentHour === 0 || $currentHour === 12,
            default => in_array($currentHour, array_map('intval', explode(',', $schedule))),
        };
    }

    /**
     * Generate a single feed
     */
    protected function _generateFeed(Maho_FeedManager_Model_Feed $feed): void
    {
        try {
            Mage::log(
                "FeedManager Cron: Starting scheduled generation of feed '{$feed->getName()}'",
                Mage::LOG_INFO,
            );

            $generator = new Maho_FeedManager_Model_Generator();
            $log = $generator->generate($feed);

            if ($log->getStatus() === Maho_FeedManager_Model_Log::STATUS_COMPLETED) {
                Mage::log(
                    "FeedManager Cron: Completed feed '{$feed->getName()}' with {$log->getProductCount()} products",
                    Mage::LOG_INFO,
                );

                // Auto-upload if configured
                if ($feed->getAutoUpload() && $feed->getDestinationId()) {
                    $this->_uploadFeed($feed, $log);
                } else {
                    $log->recordUploadSkipped(
                        $feed->getAutoUpload() ? 'No destination configured' : 'Auto-upload disabled',
                    );
                }
            } else {
                Mage::log(
                    "FeedManager Cron: Feed '{$feed->getName()}' generation failed",
                    Mage::LOG_ERROR,
                );
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::log(
                "FeedManager Cron: Exception generating feed '{$feed->getName()}': {$e->getMessage()}",
                Mage::LOG_ERROR,
            );
        }
    }

    /**
     * Upload feed to configured destination
     */
    protected function _uploadFeed(Maho_FeedManager_Model_Feed $feed, ?Maho_FeedManager_Model_Log $log = null): void
    {
        $destinationId = (int) $feed->getDestinationId();

        try {
            $destination = Mage::getModel('feedmanager/destination')->load($destinationId);

            if (!$destination->getId() || !$destination->isEnabled()) {
                $message = 'Destination not found or disabled';
                Mage::log(
                    "FeedManager: {$message} for feed '{$feed->getName()}'",
                    Mage::LOG_WARNING,
                );
                $log?->recordUploadFailure($destinationId, $message);
                return;
            }

            $uploader = new Maho_FeedManager_Model_Uploader($destination);
            $filePath = $feed->getOutputFilePath();
            $extension = $feed->getFileFormat();
            if ($feed->getGzipCompression()) {
                $extension .= '.gz';
            }
            $remoteName = $feed->getFilename() . '.' . $extension;

            $success = $uploader->upload($filePath, $remoteName);

            $destination->setLastUploadAt(Mage_Core_Model_Locale::now())
                ->setLastUploadStatus($success ? 'success' : 'failed')
                ->save();

            if ($success) {
                $message = "Uploaded to {$destination->getName()} as {$remoteName}";
                Mage::log(
                    "FeedManager: Successfully uploaded feed '{$feed->getName()}' to destination '{$destination->getName()}'",
                    Mage::LOG_INFO,
                );
                $log?->recordUploadSuccess($destinationId, $message);
            } else {
                $message = 'Upload returned false';
                Mage::log(
                    "FeedManager: Failed to upload feed '{$feed->getName()}' to destination '{$destination->getName()}'",
                    Mage::LOG_ERROR,
                );
                $log?->recordUploadFailure($destinationId, $message);
            }
        } catch (Exception $e) {
            Mage::logException($e);
            $log?->recordUploadFailure($destinationId, $e->getMessage());

            // Send failure notification
            $notifier = new Maho_FeedManager_Model_Notifier();
            $notifier->notify($feed, [$e->getMessage()], 'upload');
        }
    }

    /**
     * Clean up old logs based on retention setting
     *
     * Called by cron daily at 3:30 AM (configurable in config.xml)
     */
    public function cleanupOldLogs(): void
    {
        $retentionDays = (int) Mage::getStoreConfig('feedmanager/general/log_retention_days');

        // If set to 0, cleanup is disabled
        if ($retentionDays <= 0) {
            return;
        }

        $cutoffDate = (new DateTime())->modify("-{$retentionDays} days")->format('Y-m-d H:i:s');

        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');
        $tableName = $resource->getTableName('feedmanager/log');

        // Use direct SQL for efficient bulk deletion
        $deleted = $connection->delete($tableName, ['started_at < ?' => $cutoffDate]);

        if ($deleted > 0) {
            Mage::log("FeedManager: Cleaned {$deleted} log entries older than {$retentionDays} days", Mage::LOG_INFO);
        }
    }
}
