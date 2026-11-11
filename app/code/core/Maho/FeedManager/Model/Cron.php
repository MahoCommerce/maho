<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

/**
 * Feed Manager Cron Model
 *
 * Handles scheduled feed generation via Maho cron system.
 *
 * Error Handling Pattern:
 * - generateScheduledFeeds(): Catches all exceptions per-feed, continues with remaining feeds
 * - _generateFeed(): Wraps the service call in try/catch, logs errors to system.log
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
    #[Maho\Config\CronJob('feedmanager_generate_scheduled', schedule: '0 * * * *')]
    public function generateScheduledFeeds(): void
    {
        // The service refuses a generation while the module is off. The cron returns first, so it logs no error each hour
        if (!Mage::helper('feedmanager')->isEnabled()) {
            return;
        }

        // First, clean up any hung feeds
        $this->_cleanupHungFeeds();

        $currentHour = (int) new DateTime()->format('G'); // 0-23

        // Get all enabled feeds
        $feeds = Mage::getResourceModel('feedmanager/feed_collection')
            ->addFieldToFilter('is_enabled', 1);

        foreach ($feeds as $feed) {
            if ($this->_shouldGenerateFeed($feed, $currentHour)) {
                $this->_generateFeed($feed);
            }
        }
    }

    /**
     * Reset the generations that run for longer than the timeout
     */
    protected function _cleanupHungFeeds(): void
    {
        $feedIds = Mage::getResourceModel('feedmanager/log_collection')
            ->addStatusFilter(Maho_FeedManager_Model_Log::STATUS_RUNNING)
            ->getColumnValues('feed_id');

        /** @var Maho_FeedManager_Service_Feed $service */
        $service = Mage::getService('feedmanager/feed');
        foreach (array_unique($feedIds) as $feedId) {
            $feed = Mage::getModel('feedmanager/feed')->load($feedId);
            if ($feed->getId()) {
                $service->reset($feed);
            }
        }
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
            default => in_array($currentHour, array_map(intval(...), explode(',', $schedule))),
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

            /** @var Maho_FeedManager_Service_Feed $service */
            $service = Mage::getService('feedmanager/feed');
            $log = $service->generate($feed);

            if ($log->getStatus() === Maho_FeedManager_Model_Log::STATUS_COMPLETED) {
                Mage::log(
                    "FeedManager Cron: Completed feed '{$feed->getName()}' with {$log->getProductCount()} products",
                    Mage::LOG_INFO,
                );
            } else {
                Mage::log(
                    "FeedManager Cron: Feed '{$feed->getName()}' generation failed",
                    Mage::LOG_ERROR,
                );
            }
        } catch (Mage_Core_Exception_Conflict) {
            Mage::log("FeedManager Cron: Skipping feed '{$feed->getName()}' - already running", Mage::LOG_INFO);
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::log(
                "FeedManager Cron: Exception generating feed '{$feed->getName()}': {$e->getMessage()}",
                Mage::LOG_ERROR,
            );
        }
    }

    /**
     * Clean up old logs based on retention setting
     *
     * Called by cron daily at 3:30 AM (configurable in config.xml)
     */
    #[Maho\Config\CronJob('feedmanager_cleanup_logs', schedule: '30 3 * * *')]
    public function cleanupOldLogs(): void
    {
        Maho_FeedManager_Model_Generator_Batch::cleanupOldJobs();

        $retentionDays = (int) Mage::getStoreConfig('feedmanager/general/log_retention_days');

        // If set to 0, cleanup is disabled
        if ($retentionDays <= 0) {
            return;
        }

        $cutoffDate = new DateTime()->modify("-{$retentionDays} days")->format('Y-m-d H:i:s');

        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');
        $tableName = $resource->getTableName('feedmanager/log');

        // Use direct SQL for efficient bulk deletion
        $deleted = $connection->delete($tableName, ['started_at < ?' => $cutoffDate]);

        if ($deleted > 0) {
            Mage::log("FeedManager: Cleaned {$deleted} log entries older than {$retentionDays} days", Mage::LOG_INFO);
        }
    }

    #[\Deprecated(message: 'Use Maho_FeedManager_Model_Generator::isGenerating() instead')]
    protected function _isFeedRunning(Maho_FeedManager_Model_Feed $feed): bool
    {
        return Maho_FeedManager_Model_Generator::isGenerating((int) $feed->getId());
    }

    #[\Deprecated(message: 'Use Maho_FeedManager_Service_Feed::reset() instead')]
    public function resetHungFeed(int $feedId): bool
    {
        $feed = Mage::getModel('feedmanager/feed')->load($feedId);
        return $feed->getId() && Mage::getService('feedmanager/feed')->reset($feed) > 0;
    }

    #[\Deprecated(message: 'Use Maho_FeedManager_Service_Feed::upload() instead')]
    protected function _uploadFeed(Maho_FeedManager_Model_Feed $feed, Maho_FeedManager_Model_Log $log, string $localPath): void
    {
        Mage::getService('feedmanager/feed')->upload($feed, $log, $localPath);
    }
}
