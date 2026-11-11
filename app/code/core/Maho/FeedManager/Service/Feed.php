<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Service_Feed
{
    /**
     * Generate $feed in one run and return the log of the generation. The log tells if the generation failed.
     * A feed with auto upload on goes to its destination after the generation.
     *
     * @throws Mage_Core_Exception_Conflict when the Enable Module switch is off, or a generation of the feed is running
     */
    public function generate(Maho_FeedManager_Model_Feed $feed): Maho_FeedManager_Model_Log
    {
        $this->_checkEnabled();
        $this->_checkNotGenerating($feed);

        /** @var Maho_FeedManager_Model_Generator $generator */
        $generator = Mage::getModel('feedmanager/generator');
        return $generator->generate(
            $feed,
            function (string $localPath, Maho_FeedManager_Model_Log $log) use ($feed): void {
                $this->_autoUpload($feed, $log, $localPath);
            },
        );
    }

    /**
     * Start a generation of $feed in batches. With $force, reset a running generation of the feed first.
     *
     * @return array{job_id: string, log_id: int, total_products: int, batch_size: int, batches_total: int}
     * @throws Mage_Core_Exception_Conflict when the Enable Module switch is off, or a generation of the feed is
     *                                      running and $force is false
     */
    public function startBatch(Maho_FeedManager_Model_Feed $feed, bool $force = false): array
    {
        $this->_checkEnabled();
        if ($force) {
            $this->reset($feed, true);
        } else {
            $this->_checkNotGenerating($feed);
        }

        return $this->_getBatch()->initBatch($feed);
    }

    /**
     * @throws Mage_Core_Exception_Conflict when the Enable Module switch of the configuration is off
     */
    protected function _checkEnabled(): void
    {
        if (!Mage::helper('feedmanager')->isEnabled()) {
            throw new Mage_Core_Exception_Conflict(
                Mage::helper('feedmanager')->__('Feed Manager is disabled. Enable it in the configuration to generate a feed.'),
            );
        }
    }

    /**
     * Finish the batch generation $jobId. A feed with auto upload on goes to its destination.
     *
     * @return array<string, mixed> see Maho_FeedManager_Model_Generator_Batch::finalize()
     */
    public function finalizeBatch(string $jobId): array
    {
        return $this->_getBatch()->finalize(
            $jobId,
            fn(Maho_FeedManager_Model_Feed $feed, Maho_FeedManager_Model_Log $log, string $localPath): array
                => $this->_autoUpload($feed, $log, $localPath),
        );
    }

    /**
     * Upload the file of $feed to its destination, and record the result on $log.
     * Without $localPath, the file comes from the media mount, and the result goes on the newest log of the feed.
     *
     * @return array{status: string, message: string} the status is a Maho_FeedManager_Model_Log::UPLOAD_STATUS_*
     * @throws Mage_Core_Exception_Conflict when $localPath is null and the feed has no file
     */
    public function upload(
        Maho_FeedManager_Model_Feed $feed,
        ?Maho_FeedManager_Model_Log $log = null,
        ?string $localPath = null,
    ): array {
        $helper = Mage::helper('feedmanager');
        if ($localPath === null) {
            $path = $feed->getStoragePath();
            if ($path === null || !$helper->getOutputMount()->fileExists($path)) {
                throw new Mage_Core_Exception_Conflict($helper->__('Feed file not found. Please generate the feed first.'));
            }
            $log ??= $this->_getLatestLog($feed);
        }

        $destinationId = (int) $feed->getDestinationId();
        if (!$destinationId) {
            $message = $helper->__('No destination configured for this feed');
            $log?->recordUploadSkipped($message);
            return ['status' => Maho_FeedManager_Model_Log::UPLOAD_STATUS_SKIPPED, 'message' => $message];
        }

        try {
            /** @var Maho_FeedManager_Model_Destination $destination */
            $destination = Mage::getModel('feedmanager/destination')->load($destinationId);
            if (!$destination->getId() || !$destination->isEnabled()) {
                $message = $destination->getId() ? $helper->__('Destination is disabled') : $helper->__('Destination not found');
                Mage::log("FeedManager: {$message} for feed '{$feed->getName()}'", Mage::LOG_WARNING);
                $log?->recordUploadFailure($destinationId, $message);
                return ['status' => Maho_FeedManager_Model_Log::UPLOAD_STATUS_FAILED, 'message' => $message];
            }

            /** @var Maho_FeedManager_Model_Uploader $uploader */
            $uploader = Mage::getModel('feedmanager/uploader', $destination);
            $remoteName = $feed->getOutputFilename();
            $success = $localPath === null ? $uploader->uploadFeed($feed) : $uploader->upload($localPath, $remoteName);

            $destination->setLastUploadAt(Mage::app()->getLocale()->formatDateForDb('now'))
                ->setLastUploadStatus($success ? 'success' : 'failed')
                ->save();

            if (!$success) {
                $message = $helper->__('Upload failed');
                Mage::log("FeedManager: Failed to upload feed '{$feed->getName()}' to destination '{$destination->getName()}'", Mage::LOG_ERROR);
                $log?->recordUploadFailure($destinationId, $message);
                return ['status' => Maho_FeedManager_Model_Log::UPLOAD_STATUS_FAILED, 'message' => $message];
            }

            $message = $helper->__('Uploaded to %s as %s', $destination->getName(), $remoteName);
            Mage::log("FeedManager: Uploaded feed '{$feed->getName()}' to destination '{$destination->getName()}'", Mage::LOG_INFO);
            $log?->recordUploadSuccess($destinationId, $message);
            return ['status' => Maho_FeedManager_Model_Log::UPLOAD_STATUS_SUCCESS, 'message' => $message];
        } catch (\Throwable $e) {
            Mage::logException($e);
            $log?->recordUploadFailure($destinationId, $e->getMessage());
            $this->_getNotifier()->notify($feed, [$e->getMessage()], 'upload');
            return ['status' => Maho_FeedManager_Model_Log::UPLOAD_STATUS_FAILED, 'message' => $e->getMessage()];
        }
    }

    /**
     * Mark the running generations of $feed as failed, and return their number. $force resets every running
     * generation and deletes the batch jobs of the feed. Without $force, reset only a generation that runs for longer
     * than the timeout, and send the timeout notification.
     */
    public function reset(Maho_FeedManager_Model_Feed $feed, bool $force = false): int
    {
        $timeout = Maho_FeedManager_Model_Cron::HUNG_FEED_TIMEOUT_MINUTES;
        /** @var Maho_FeedManager_Model_Resource_Log_Collection $logs */
        $logs = Mage::getResourceModel('feedmanager/log_collection')
            ->addFeedFilter((int) $feed->getId())
            ->addStatusFilter(Maho_FeedManager_Model_Log::STATUS_RUNNING);
        if (!$force) {
            $logs->addFieldToFilter('started_at', [
                'lt' => Mage::app()->getLocale()->formatDateForDb("-{$timeout} minutes"),
            ]);
        }

        $message = $force
            ? Mage::helper('feedmanager')->__('Generation was reset')
            : Mage::helper('feedmanager')->__('Feed generation timed out (exceeded %d minutes)', $timeout);
        $count = 0;
        foreach ($logs as $log) {
            /** @var Maho_FeedManager_Model_Log $log */
            $log->setStatus(Maho_FeedManager_Model_Log::STATUS_FAILED)
                ->setCompletedAt(Mage::app()->getLocale()->formatDateForDb('now'))
                ->addError($message)
                ->save();
            Mage::log("FeedManager: Marked generation log #{$log->getId()} of feed '{$feed->getName()}' as failed", Mage::LOG_WARNING);
            $count++;
        }

        if ($force) {
            $this->_getBatch()->deleteFeedJobs((int) $feed->getId());
        } elseif ($count) {
            $this->_getNotifier()->notify($feed, [$message], 'timeout');
        }

        return $count;
    }

    /**
     * @return array{status: string, message: string}
     */
    protected function _autoUpload(
        Maho_FeedManager_Model_Feed $feed,
        Maho_FeedManager_Model_Log $log,
        string $localPath,
    ): array {
        if (!$feed->getAutoUpload()) {
            $message = Mage::helper('feedmanager')->__('Auto-upload disabled');
            $log->recordUploadSkipped($message);
            return ['status' => Maho_FeedManager_Model_Log::UPLOAD_STATUS_SKIPPED, 'message' => $message];
        }

        return $this->upload($feed, $log, $localPath);
    }

    /**
     * @throws Mage_Core_Exception_Conflict
     */
    protected function _checkNotGenerating(Maho_FeedManager_Model_Feed $feed): void
    {
        if (Maho_FeedManager_Model_Generator::isGenerating((int) $feed->getId())) {
            throw new Mage_Core_Exception_Conflict(
                Mage::helper('feedmanager')->__("Feed '%s' is already being generated", $feed->getName()),
            );
        }
    }

    protected function _getLatestLog(Maho_FeedManager_Model_Feed $feed): ?Maho_FeedManager_Model_Log
    {
        /** @var Maho_FeedManager_Model_Log $log */
        $log = Mage::getResourceModel('feedmanager/log_collection')
            ->addFeedFilter((int) $feed->getId())
            ->setOrder('started_at', 'DESC')
            ->setPageSize(1)
            ->getFirstItem();
        return $log->getId() ? $log : null;
    }

    protected function _getBatch(): Maho_FeedManager_Model_Generator_Batch
    {
        return Mage::getModel('feedmanager/generator_batch');
    }

    protected function _getNotifier(): Maho_FeedManager_Model_Notifier
    {
        return Mage::getModel('feedmanager/notifier');
    }
}
