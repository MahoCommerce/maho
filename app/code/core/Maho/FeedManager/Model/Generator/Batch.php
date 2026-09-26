<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

/**
 * Batch Generator - Handles AJAX batch-by-batch feed generation
 *
 * This class manages stateful batch processing across multiple HTTP requests,
 * which can reach different nodes. The job keeps its files in a folder named
 * after the job ID on the feeds mount: a JSON state file, and one part file for
 * each request, because a bucket cannot append. Part 0 holds the header of the
 * feed. finalize() joins the parts in order, writes the footer, and puts the
 * feed on the media mount. Uses the shared output engine from ProductWriterTrait
 * for all format handling.
 */
class Maho_FeedManager_Model_Generator_Batch
{
    use Maho_FeedManager_Model_Generator_ProductWriterTrait;

    public const STATUS_INITIALIZING = 'initializing';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_FINALIZING = 'finalizing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const STATE_FILE = 'state.json';

    /** A job ID is feed_{feed ID}_{uniqid()}. It names a folder, so a request can give no other value. */
    public const JOB_ID_PATTERN = '/^feed_\d+_[0-9a-f]+$/';

    /** Seconds without progress after which a job of a feed is removed when a new job of the feed starts */
    public const STALE_JOB_SECONDS = 300;

    protected Maho_FeedManager_Model_Feed $_feed;
    protected ?Maho_FeedManager_Model_Log $_log = null;
    protected Maho_FeedManager_Model_Mapper $_mapper;
    protected ?Maho_FeedManager_Model_Platform_AdapterInterface $_platform = null;
    protected int $_batchSize;
    protected array $_state = [];
    protected string $_jobId;
    protected array $_errors = [];

    protected ?string $_lockName = null;

    /**
     * Initialize a new batch generation job
     *
     * @return array{job_id: string, log_id: int, total_products: int, batch_size: int, batches_total: int}
     */
    public function initBatch(Maho_FeedManager_Model_Feed $feed): array
    {
        $this->_feed = $feed;

        // Clean up any stale running jobs for this feed (older than 5 minutes with no recent progress)
        $this->_cleanupStaleJobs($feed->getId());
        $this->_batchSize = Mage::helper('feedmanager')->getBatchSize();
        $this->_platform = Maho_FeedManager_Model_Platform::getAdapter($feed->getPlatform());
        $this->_mapper = new Maho_FeedManager_Model_Mapper($feed);
        $this->_configureMapperFromBuilder();

        // Generate unique job ID
        $this->_jobId = 'feed_' . $feed->getId() . '_' . uniqid();

        // Check for existing running generation (race condition prevention)
        $this->_acquireGenerationLock(true);

        // Count total products
        $collection = $this->_getProductCollection();
        $totalProducts = $collection->getSize();

        // Calculate batches
        $batchesTotal = (int) ceil($totalProducts / $this->_batchSize);

        // Open output (writes header), then pause (releases file handle) and keep the header as part 0
        $partExtension = $feed->getFileFormat() ?: 'xml';
        $tempPath = Mage::helper('feedmanager')->createTempFile();
        try {
            $this->_openOutput($tempPath);
            $this->_checkMeasureUnits();
            $this->_pauseOutput();
            \Maho\Storage\Mount::copyLocalFile($tempPath, $this->getMount(), $this->_getPartPath(0, $partExtension));
        } finally {
            @unlink($tempPath);
        }

        // Save state
        $this->_state = [
            'job_id' => $this->_jobId,
            'feed_id' => $feed->getId(),
            'log_id' => $this->_log->getId(),
            'status' => self::STATUS_INITIALIZING,
            'total_products' => $totalProducts,
            'processed_count' => 0,
            'product_count' => 0,
            'current_page' => 0,
            'batch_size' => $this->_batchSize,
            'batches_total' => $batchesTotal,
            'batches_processed' => 0,
            'part_extension' => $partExtension,
            'writer_state' => $this->_getOutputState(),
            'errors' => $this->_errors,
            // A setup message is not a failed product, so it stays out of the threshold count.
            'error_count' => 0,
            'started_at' => Mage::app()->getLocale()->formatDateForDb('now'),
        ];
        $this->_saveState();

        // Update log with total
        $this->_log->setData('total_products', $totalProducts)->save();

        Mage::log(
            "FeedManager: Initialized batch generation for feed '{$feed->getName()}' with {$totalProducts} products",
            Mage::LOG_INFO,
        );

        return [
            'job_id' => $this->_jobId,
            'log_id' => $this->_log->getId(),
            'total_products' => $totalProducts,
            'batch_size' => $this->_batchSize,
            'batches_total' => $batchesTotal,
        ];
    }

    /**
     * Process a single batch of products
     *
     * @return array{
     *     status: string,
     *     progress: int,
     *     total: int,
     *     batches_processed: int,
     *     batches_total: int,
     *     message: string,
     *     processed?: int,
     *     batch_products?: int,
     *     errors?: list<string>,
     * }
     */
    public function processBatch(string $jobId): array
    {
        $this->_jobId = $jobId;

        // Acquire exclusive lock to prevent concurrent batch processing
        if (!$this->_acquireStateLock()) {
            return [
                'status' => self::STATUS_PROCESSING,
                'progress' => 0,
                'total' => 0,
                'batches_processed' => 0,
                'batches_total' => 0,
                'message' => 'Another request is already processing this batch, please wait',
            ];
        }

        $tempPath = null;
        try {
            // Load state
            if (!$this->_loadState()) {
                return [
                    'status' => self::STATUS_FAILED,
                    'progress' => 0,
                    'total' => 0,
                    'batches_processed' => 0,
                    'batches_total' => 0,
                    'message' => 'Invalid or expired job ID',
                ];
            }

            // Check if already completed
            if ($this->_state['status'] === self::STATUS_COMPLETED) {
                return [
                    'status' => self::STATUS_COMPLETED,
                    'progress' => (int) $this->_state['product_count'],
                    'total' => (int) $this->_state['total_products'],
                    'batches_processed' => (int) $this->_state['batches_processed'],
                    'batches_total' => (int) $this->_state['batches_total'],
                    'message' => 'Generation already completed',
                ];
            }

            // Load feed and log
            $this->_feed = Mage::getModel('feedmanager/feed')->load($this->_state['feed_id']);
            if (!$this->_feed->getId()) {
                return $this->_failWithError('Feed no longer exists');
            }
            $this->_log = Mage::getModel('feedmanager/log')->load($this->_state['log_id']);

            // Initialize mapper and platform
            $this->_mapper = new Maho_FeedManager_Model_Mapper($this->_feed);
            $this->_configureMapperFromBuilder();
            $this->_platform = Maho_FeedManager_Model_Platform::getAdapter($this->_feed->getPlatform());
            $this->_batchSize = $this->_state['batch_size'];

            // Restore counters from state
            $this->_productCount = $this->_state['product_count'];
            $this->_processedCount = $this->_state['processed_count'];
            $this->_errorCount = (int) ($this->_state['error_count'] ?? count($this->_state['errors']));
            $this->_errors = $this->_state['errors'];

            // Resume output on a new local file (recreates writer/parses XML, opens in append mode)
            $tempPath = Mage::helper('feedmanager')->createTempFile();
            $this->_resumeOutput($tempPath, $this->_state['writer_state'] ?? []);

            // Update status and page
            $this->_state['status'] = self::STATUS_PROCESSING;
            $this->_state['current_page']++;

            // Process this batch using the shared engine
            $processedInBatch = $this->_processOneBatch($this->_state['current_page']);

            // Pause output (releases file handle) and keep the rows of this batch as the part of this page
            $this->_pauseOutput();
            \Maho\Storage\Mount::copyLocalFile($tempPath, $this->getMount(), $this->_getPartPath($this->_state['current_page']));

            // Save counters back to state
            $this->_state['writer_state'] = $this->_getOutputState();
            $this->_state['product_count'] = $this->_productCount;
            $this->_state['processed_count'] = $this->_processedCount;
            $this->_state['errors'] = $this->_errors;
            $this->_state['error_count'] = $this->_errorCount;
            $this->_state['batches_processed']++;
            $this->_saveState();

            // Update log
            $this->_log->setProductCount($this->_state['product_count'])->save();

            // Check if we've processed all products
            $isComplete = $this->_state['processed_count'] >= $this->_state['total_products'];

            return [
                'status' => $isComplete ? self::STATUS_FINALIZING : self::STATUS_PROCESSING,
                'progress' => (int) $this->_state['product_count'],
                'total' => (int) $this->_state['total_products'],
                'processed' => (int) $this->_state['processed_count'],
                'batches_processed' => (int) $this->_state['batches_processed'],
                'batches_total' => (int) $this->_state['batches_total'],
                'batch_products' => $processedInBatch,
                'message' => $isComplete
                    ? 'Ready to finalize'
                    : "Processed batch {$this->_state['batches_processed']}/{$this->_state['batches_total']}",
            ];
        } catch (\Throwable $e) {
            return $this->_failWithError($e->getMessage());
        } finally {
            if ($tempPath !== null) {
                @unlink($tempPath);
            }
            $this->_releaseStateLock();
        }
    }

    /**
     * Finalize the generation (validation, compression, cleanup)
     *
     * Failure paths (lock-failed, invalid-job) return the basic file shape with empty values.
     * Failure via _failWithError() returns the processBatch failure shape with progress/total counters.
     *
     * @return array{
     *     status: string,
     *     message: string,
     *     file_url?: string,
     *     product_count?: int,
     *     file_size?: int,
     *     total_products?: int,
     *     file_size_formatted?: string,
     *     errors?: list<string>,
     *     has_destination?: bool,
     *     upload_status?: string,
     *     upload_message?: string,
     *     progress?: int,
     *     total?: int,
     *     batches_processed?: int,
     *     batches_total?: int,
     * }
     */
    public function finalize(string $jobId): array
    {
        $this->_jobId = $jobId;

        // Acquire exclusive lock to prevent concurrent finalization
        if (!$this->_acquireStateLock()) {
            return [
                'status' => self::STATUS_FAILED,
                'file_url' => '',
                'product_count' => 0,
                'file_size' => 0,
                'message' => 'Another request is already finalizing this job',
            ];
        }

        $tempPath = null;
        try {
            // Load state
            if (!$this->_loadState()) {
                return [
                    'status' => self::STATUS_FAILED,
                    'file_url' => '',
                    'product_count' => 0,
                    'file_size' => 0,
                    'message' => 'Invalid or expired job ID',
                ];
            }

            // Load feed and log
            $this->_feed = Mage::getModel('feedmanager/feed')->load($this->_state['feed_id']);
            $this->_log = Mage::getModel('feedmanager/log')->load($this->_state['log_id']);

            if (!$this->_feed->getId()) {
                return $this->_failWithError('Feed no longer exists');
            }

            // Initialize platform (needed for XML writer close)
            $this->_platform = Maho_FeedManager_Model_Platform::getAdapter($this->_feed->getPlatform());
            $this->_errors = $this->_state['errors'];

            // Join the parts in a local file, then resume and close output (writes footer/closing tags)
            $tempPath = Mage::helper('feedmanager')->createTempFile();
            $this->_joinParts($tempPath);
            $this->_resumeOutput($tempPath, $this->_state['writer_state'] ?? []);
            $this->_closeOutput();

            // Validate, compress, and put the feed on the media mount
            $tempPath = $this->_validateAndPublish($tempPath, $this->_errors);

            // Finalize: update log, feed, and reset notifier
            $fileSize = (int) filesize($tempPath);
            $this->_finalizeGenerationSuccess($this->_state['product_count'], $fileSize, $this->_errors);

            // Handle upload if configured
            $uploadResult = $this->_handleUpload($tempPath);

            // The job is done: remove its parts and its state
            $this->_state['status'] = self::STATUS_COMPLETED;
            $this->_deleteJob();

            Mage::log(
                "FeedManager: Completed batch generation for feed '{$this->_feed->getName()}' with {$this->_state['product_count']} products",
                Mage::LOG_INFO,
            );

            return [
                'status' => self::STATUS_COMPLETED,
                'file_url' => (string) Mage::helper('feedmanager')->getFeedUrl($this->_feed),
                'product_count' => (int) $this->_state['product_count'],
                'total_products' => (int) $this->_state['total_products'],
                'file_size' => (int) $fileSize,
                'file_size_formatted' => (string) Mage::helper('feedmanager')->formatFileSize($fileSize),
                'message' => "Feed generated successfully with {$this->_state['product_count']} products",
                'errors' => array_values(array_map(strval(...), $this->_errors)),
                'has_destination' => (bool) $this->_feed->getDestinationId(),
                'upload_status' => $uploadResult['status'],
                'upload_message' => $uploadResult['message'],
            ];
        } catch (\Throwable $e) {
            return $this->_failWithError($e->getMessage());
        } finally {
            if ($tempPath !== null && is_file($tempPath)) {
                @unlink($tempPath);
            }
            $this->_releaseStateLock();
        }
    }

    /**
     * Cancel a running batch job
     */
    public function cancel(string $jobId): array
    {
        $this->_jobId = $jobId;

        // Acquire exclusive lock — block until any in-progress batch finishes
        if (!$this->_acquireStateLock()) {
            return ['status' => 'error', 'message' => 'Could not acquire lock, a batch may be processing'];
        }

        try {
            if (!$this->_loadState()) {
                return ['status' => 'error', 'message' => 'Job not found'];
            }

            // Load and update log
            $this->_log = Mage::getModel('feedmanager/log')->load($this->_state['log_id']);
            if ($this->_log->getId()) {
                $this->_log->setStatus(Maho_FeedManager_Model_Log::STATUS_FAILED)
                    ->addError('Generation cancelled by user')
                    ->save();
            }

            // Remove the parts and the state
            $this->_deleteJob();

            return ['status' => 'cancelled', 'message' => 'Generation cancelled'];
        } finally {
            $this->_releaseStateLock();
        }
    }

    /**
     * Get current job status
     */
    public function getStatus(string $jobId): array
    {
        $this->_jobId = $jobId;
        if (!$this->_loadState()) {
            return [
                'status' => 'not_found',
                'message' => 'Job not found or expired',
            ];
        }

        return [
            'status' => $this->_state['status'],
            'progress' => $this->_state['product_count'],
            'total' => $this->_state['total_products'],
            'processed' => $this->_state['processed_count'],
            'batches_processed' => $this->_state['batches_processed'],
            'batches_total' => $this->_state['batches_total'],
            'errors' => $this->_state['errors'],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // State and lock management
    // ──────────────────────────────────────────────────────────────────────

    /**
     * The mount that holds the parts and the state of the jobs
     */
    protected function getMount(): \Maho\Storage\Mount
    {
        return Mage::getStorage('feeds');
    }

    /**
     * Path of a part on the feeds mount. Part 0 holds the header, part N the rows of page N.
     */
    protected function _getPartPath(int $index, ?string $extension = null): string
    {
        $extension ??= (string) ($this->_state['part_extension'] ?? 'xml');
        return sprintf('%s/part-%06d.%s', $this->_jobId, $index, $extension);
    }

    /**
     * Get state file path on the feeds mount
     */
    protected function _getStatePath(): string
    {
        return $this->_jobId . '/' . self::STATE_FILE;
    }

    /**
     * Stream the parts of the job in order into the local file $localPath
     */
    protected function _joinParts(string $localPath): void
    {
        $target = fopen($localPath, 'wb');
        if ($target === false) {
            throw new RuntimeException("Cannot open file for writing: {$localPath}");
        }
        try {
            for ($index = 0; $index <= (int) $this->_state['current_page']; $index++) {
                $source = $this->getMount()->readStream($this->_getPartPath($index));
                try {
                    stream_copy_to_stream($source, $target);
                } finally {
                    fclose($source);
                }
            }
        } finally {
            fclose($target);
        }
    }

    /**
     * Acquire an exclusive lock on the job to prevent concurrent access from any node.
     *
     * Returns false if a lock cannot be acquired (another request is already
     * processing this job). The lock is held until _releaseStateLock() is called.
     */
    protected function _acquireStateLock(): bool
    {
        $name = 'feedmanager_batch_' . $this->_jobId;
        if (!Mage::getModel('core/lock')->acquire($name)) {
            return false;
        }
        $this->_lockName = $name;
        return true;
    }

    /**
     * Release the job lock
     */
    protected function _releaseStateLock(): void
    {
        if ($this->_lockName !== null) {
            Mage::getModel('core/lock')->release($this->_lockName);
            $this->_lockName = null;
        }
    }

    /**
     * Save state to the feeds mount in one step, so a reader never sees half a file
     */
    protected function _saveState(): void
    {
        $this->getMount()->moveAtomic($this->_getStatePath(), Mage::helper('core')->jsonEncode($this->_state));
    }

    /**
     * Load state from the feeds mount
     */
    protected function _loadState(): bool
    {
        if (!preg_match(self::JOB_ID_PATTERN, $this->_jobId)) {
            return false;
        }

        try {
            $content = $this->getMount()->read($this->_getStatePath());
        } catch (\League\Flysystem\FilesystemException) {
            return false;
        }

        if ($content === '') {
            return false;
        }

        try {
            $this->_state = Mage::helper('core')->jsonDecode($content);
        } catch (\JsonException $e) {
            Mage::log("FeedManager: Corrupt state file for job '{$this->_jobId}': {$e->getMessage()}", Mage::LOG_ERROR);
            return false;
        }

        return !empty($this->_state);
    }

    /**
     * Delete the folder of the job: its parts and its state
     */
    protected function _deleteJob(): void
    {
        if (preg_match(self::JOB_ID_PATTERN, $this->_jobId)) {
            $this->getMount()->deleteDirectory($this->_jobId);
        }
    }

    /**
     * Delete the jobs of a feed from the feeds mount, and return how many it deleted
     *
     * @param int $idleSeconds Delete only a job whose newest file is older than this, 0 for every job
     */
    public function deleteFeedJobs(int $feedId, int $idleSeconds = 0): int
    {
        $mount = $this->getMount();
        $deleted = 0;
        foreach ($mount->listContents('', false) as $item) {
            $jobId = $item->path();
            if (!$item->isDir() || !str_starts_with($jobId, "feed_{$feedId}_") || !preg_match(self::JOB_ID_PATTERN, $jobId)) {
                continue;
            }
            if ($idleSeconds > 0 && self::_getLastModified($mount, $jobId) >= time() - $idleSeconds) {
                continue;
            }
            $mount->deleteDirectory($jobId);
            $deleted++;
        }
        return $deleted;
    }

    /**
     * The newest modification time of the files in a job folder, 0 for an empty folder
     */
    protected static function _getLastModified(\Maho\Storage\Mount $mount, string $jobId): int
    {
        $newest = 0;
        foreach ($mount->listContents($jobId, true) as $item) {
            if ($item->isFile()) {
                $newest = max($newest, (int) $item->lastModified());
            }
        }
        return $newest;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Error handling and upload
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Fail with error message
     *
     * @return array{
     *     status: string,
     *     progress: int,
     *     total: int,
     *     batches_processed: int,
     *     batches_total: int,
     *     message: string,
     *     errors: list<string>,
     * }
     */
    protected function _failWithError(string $message): array
    {
        $this->_state['status'] = self::STATUS_FAILED;
        $this->_state['errors'][] = $message;
        $this->_saveState();

        // Update log if loaded
        if ($this->_log && $this->_log->getId()) {
            $this->_log->setStatus(Maho_FeedManager_Model_Log::STATUS_FAILED);
            $this->_saveErrorsToLog($this->_state['errors']);
            $this->_log->save();
        }

        // Send failure notification
        if ($this->_feed->getId()) {
            $notifier = new Maho_FeedManager_Model_Notifier();
            $notifier->notify($this->_feed, $this->_state['errors'], 'generation');
        }

        return [
            'status' => self::STATUS_FAILED,
            'progress' => (int) ($this->_state['product_count'] ?? 0),
            'total' => (int) ($this->_state['total_products'] ?? 0),
            'batches_processed' => (int) ($this->_state['batches_processed'] ?? 0),
            'batches_total' => (int) ($this->_state['batches_total'] ?? 0),
            'message' => $message,
            'errors' => array_values(array_map(strval(...), $this->_state['errors'])),
        ];
    }

    /**
     * Handle upload after successful generation
     *
     * @param string $localPath The local file that holds the published feed
     * @return array{status: string, message: string}
     */
    protected function _handleUpload(string $localPath): array
    {
        // Check if auto-upload is enabled
        if (!$this->_feed->getAutoUpload()) {
            $this->_log->recordUploadSkipped('Auto-upload disabled');
            return [
                'status' => Maho_FeedManager_Model_Log::UPLOAD_STATUS_SKIPPED,
                'message' => 'Auto-upload disabled',
            ];
        }

        // Check if destination is configured
        $destinationId = (int) $this->_feed->getDestinationId();
        if (!$destinationId) {
            $this->_log->recordUploadSkipped('No destination configured');
            return [
                'status' => Maho_FeedManager_Model_Log::UPLOAD_STATUS_SKIPPED,
                'message' => 'No destination configured',
            ];
        }

        try {
            $destination = Mage::getModel('feedmanager/destination')->load($destinationId);

            if (!$destination->getId()) {
                $message = 'Destination not found';
                $this->_log->recordUploadFailure($destinationId, $message);
                return [
                    'status' => Maho_FeedManager_Model_Log::UPLOAD_STATUS_FAILED,
                    'message' => $message,
                ];
            }

            if (!$destination->isEnabled()) {
                $message = 'Destination is disabled';
                $this->_log->recordUploadFailure($destinationId, $message);
                return [
                    'status' => Maho_FeedManager_Model_Log::UPLOAD_STATUS_FAILED,
                    'message' => $message,
                ];
            }

            // Perform upload
            $uploader = new Maho_FeedManager_Model_Uploader($destination);
            $remoteName = $this->_feed->getOutputFilename();

            $success = $uploader->upload($localPath, $remoteName);

            // Update destination last upload info
            $destination->setLastUploadAt(Mage::app()->getLocale()->formatDateForDb('now'))
                ->setLastUploadStatus($success ? 'success' : 'failed')
                ->save();

            if ($success) {
                $message = "Uploaded to {$destination->getName()} as {$remoteName}";
                $this->_log->recordUploadSuccess($destinationId, $message);
                Mage::log(
                    "FeedManager: Successfully uploaded feed '{$this->_feed->getName()}' to destination '{$destination->getName()}'",
                    Mage::LOG_INFO,
                );
                return [
                    'status' => Maho_FeedManager_Model_Log::UPLOAD_STATUS_SUCCESS,
                    'message' => $message,
                ];
            }

            $message = 'Upload failed';
            $this->_log->recordUploadFailure($destinationId, $message);
            Mage::log(
                "FeedManager: Failed to upload feed '{$this->_feed->getName()}' to destination '{$destination->getName()}'",
                Mage::LOG_ERROR,
            );
            return [
                'status' => Maho_FeedManager_Model_Log::UPLOAD_STATUS_FAILED,
                'message' => $message,
            ];
        } catch (\Throwable $e) {
            Mage::logException($e);
            $message = $e->getMessage();
            $this->_log->recordUploadFailure($destinationId, $message);

            // Send failure notification
            $notifier = new Maho_FeedManager_Model_Notifier();
            $notifier->notify($this->_feed, [$message], 'upload');

            return [
                'status' => Maho_FeedManager_Model_Log::UPLOAD_STATUS_FAILED,
                'message' => $message,
            ];
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // Cleanup
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Clean up stale running jobs for a feed
     * Jobs are considered stale if they've been running for more than 5 minutes
     */
    protected function _cleanupStaleJobs(int $feedId): void
    {
        $staleTimeout = self::STALE_JOB_SECONDS;

        /** @var Maho_FeedManager_Model_Resource_Log_Collection $collection */
        $collection = Mage::getResourceModel('feedmanager/log_collection')
            ->addFeedFilter($feedId)
            ->addFieldToFilter('status', Maho_FeedManager_Model_Log::STATUS_RUNNING);

        foreach ($collection as $log) {
            $startedAt = strtotime($log->getStartedAt());
            if (time() - $startedAt > $staleTimeout) {
                $log->setStatus(Maho_FeedManager_Model_Log::STATUS_FAILED)
                    ->addError('Generation was interrupted or timed out')
                    ->save();

                Mage::log(
                    "FeedManager: Cleaned up stale job for feed {$feedId}, log {$log->getId()}",
                    Mage::LOG_INFO,
                );
            }
        }

        // Also clean up the parts and the state of stale jobs for this feed
        $this->deleteFeedJobs($feedId, $staleTimeout);
    }

    /**
     * Clean up old jobs and old files on the feeds mount
     */
    public static function cleanupOldJobs(int $maxAgeHours = 24): int
    {
        $mount = Mage::getStorage('feeds');
        $maxAge = time() - ($maxAgeHours * 3600);
        $cleaned = 0;

        foreach ($mount->listContents('', false) as $item) {
            if ($item->isDir()) {
                if (self::_getLastModified($mount, $item->path()) < $maxAge) {
                    $mount->deleteDirectory($item->path());
                    $cleaned++;
                }
            } elseif ((int) $item->lastModified() < $maxAge) {
                $mount->delete($item->path());
                $cleaned++;
            }
        }

        return $cleaned;
    }
}
