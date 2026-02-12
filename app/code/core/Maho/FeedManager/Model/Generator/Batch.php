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
 * Batch Generator - Handles AJAX batch-by-batch feed generation
 *
 * This class manages stateful batch processing across multiple HTTP requests.
 * State is persisted to a JSON file between requests.
 */
class Maho_FeedManager_Model_Generator_Batch
{
    use Maho_FeedManager_Model_Generator_ProductProcessorTrait;

    public const STATUS_INITIALIZING = 'initializing';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_FINALIZING = 'finalizing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected Maho_FeedManager_Model_Feed $_feed;
    protected ?Maho_FeedManager_Model_Log $_log = null;
    protected Maho_FeedManager_Model_Mapper $_mapper;
    protected ?Maho_FeedManager_Model_Platform_AdapterInterface $_platform = null;
    protected int $_batchSize;
    protected array $_state = [];
    protected string $_jobId;
    protected array $_errors = [];

    protected ?\Maho\Io\File $_lockFile = null;

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

        // Generate unique job ID
        $this->_jobId = 'feed_' . $feed->getId() . '_' . uniqid();

        // Check for existing running generation (race condition prevention)
        $this->_acquireGenerationLock(true);

        // Count total products
        $collection = $this->_getProductCollection();
        $totalProducts = $collection->getSize();

        // Calculate batches
        $batchesTotal = (int) ceil($totalProducts / $this->_batchSize);

        // Initialize temp file
        $tempPath = $this->_getTempFilePath();

        // Write header for XML structure/template mode
        $xmlStructure = $feed->getXmlStructure();
        $xmlTemplate = $feed->getXmlItemTemplate();
        $isXmlMode = $feed->getFileFormat() === 'xml' && (!empty($xmlStructure) || !empty($xmlTemplate));

        if ($isXmlMode) {
            $header = $feed->getXmlHeader();
            if (!empty($header)) {
                $renderedHeader = $this->_renderHeaderFooter($header, $feed);
                file_put_contents($tempPath, $renderedHeader . "\n");
            } else {
                file_put_contents($tempPath, '');
            }
        } else {
            // For non-XML modes, initialize with empty file
            file_put_contents($tempPath, '');
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
            'temp_path' => $tempPath,
            'errors' => [],
            'started_at' => Mage_Core_Model_Locale::now(),
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
     * @return array{status: string, progress: int, total: int, batches_processed: int, batches_total: int, message: string}
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
                    'progress' => $this->_state['product_count'],
                    'total' => $this->_state['total_products'],
                    'batches_processed' => $this->_state['batches_processed'],
                    'batches_total' => $this->_state['batches_total'],
                    'message' => 'Generation already completed',
                ];
            }

            // Load feed
            $this->_feed = Mage::getModel('feedmanager/feed')->load($this->_state['feed_id']);
            if (!$this->_feed->getId()) {
                return $this->_failWithError('Feed no longer exists');
            }

            // Load log
            $this->_log = Mage::getModel('feedmanager/log')->load($this->_state['log_id']);

            // Initialize mapper and platform
            $this->_mapper = new Maho_FeedManager_Model_Mapper($this->_feed);
            $this->_configureMapperFromBuilder();
            $this->_platform = Maho_FeedManager_Model_Platform::getAdapter($this->_feed->getPlatform());

            // Update status
            $this->_state['status'] = self::STATUS_PROCESSING;
            $this->_state['current_page']++;

            // Process this batch
            $processedInBatch = $this->_processBatchProducts();

            $this->_state['batches_processed']++;
            $this->_saveState();

            // Update log
            $this->_log->setProductCount($this->_state['product_count'])->save();

            // Check if we've processed all products
            $isComplete = $this->_state['processed_count'] >= $this->_state['total_products'];

            return [
                'status' => $isComplete ? self::STATUS_FINALIZING : self::STATUS_PROCESSING,
                'progress' => $this->_state['product_count'],
                'total' => $this->_state['total_products'],
                'processed' => $this->_state['processed_count'],
                'batches_processed' => $this->_state['batches_processed'],
                'batches_total' => $this->_state['batches_total'],
                'batch_products' => $processedInBatch,
                'message' => $isComplete
                    ? 'Ready to finalize'
                    : "Processed batch {$this->_state['batches_processed']}/{$this->_state['batches_total']}",
            ];
        } catch (\Throwable $e) {
            return $this->_failWithError($e->getMessage());
        } finally {
            $this->_releaseStateLock();
        }
    }

    /**
     * Finalize the generation (validation, compression, cleanup)
     *
     * @return array{status: string, file_url: string, product_count: int, file_size: int, message: string}
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

            $tempPath = $this->_state['temp_path'];

            // Write footer for XML structure/template mode
            $xmlStructure = $this->_feed->getXmlStructure();
            $xmlTemplate = $this->_feed->getXmlItemTemplate();
            $isXmlMode = $this->_feed->getFileFormat() === 'xml' && (!empty($xmlStructure) || !empty($xmlTemplate));

            if ($isXmlMode) {
                $footer = $this->_feed->getXmlFooter();
                if (!empty($footer)) {
                    $renderedFooter = $this->_renderHeaderFooter($footer, $this->_feed);
                    file_put_contents($tempPath, $renderedFooter . "\n", FILE_APPEND);
                }
            }

            // Close JSON array if needed
            if ($this->_feed->getFileFormat() === 'json' && ($this->_state['json_started'] ?? false)) {
                file_put_contents($tempPath, "\n]}", FILE_APPEND);
            }

            // Validate, move to output, and compress
            $finalPath = $this->_validateAndMoveToOutput($tempPath, $this->_state['errors']);

            // Finalize: update log, feed, and reset notifier
            $fileSize = file_exists($finalPath) ? filesize($finalPath) : 0;
            $this->_finalizeGenerationSuccess($this->_state['product_count'], $fileSize, $this->_state['errors']);

            // Handle upload if configured
            $uploadResult = $this->_handleUpload();

            // Update state
            $this->_state['status'] = self::STATUS_COMPLETED;
            $this->_saveState();

            // Cleanup state file after short delay (let final response be sent)
            // State file will be cleaned up by cron or next init

            Mage::log(
                "FeedManager: Completed batch generation for feed '{$this->_feed->getName()}' with {$this->_state['product_count']} products",
                Mage::LOG_INFO,
            );

            return [
                'status' => self::STATUS_COMPLETED,
                'file_url' => Mage::helper('feedmanager')->getFeedUrl($this->_feed),
                'product_count' => $this->_state['product_count'],
                'file_size' => $fileSize,
                'file_size_formatted' => Mage::helper('feedmanager')->formatFileSize($fileSize),
                'message' => "Feed generated successfully with {$this->_state['product_count']} products",
                'errors' => $this->_state['errors'],
                'upload_status' => $uploadResult['status'],
                'upload_message' => $uploadResult['message'],
            ];
        } catch (\Throwable $e) {
            return $this->_failWithError($e->getMessage());
        } finally {
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

            // Cleanup temp file
            if (file_exists($this->_state['temp_path'])) {
                unlink($this->_state['temp_path']);
            }

            // Remove state file
            $this->_deleteState();

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

    /**
     * Process products for current batch
     */
    protected function _processBatchProducts(): int
    {
        $collection = $this->_getProductCollection();
        $collection->setPageSize($this->_state['batch_size']);
        $collection->setCurPage($this->_state['current_page']);

        // Preload parent mappings for this batch to avoid N+1 queries
        $productIds = $collection->getAllIds();
        if (!empty($productIds)) {
            $this->_mapper->preloadParentMappings($productIds);
        }

        $tempPath = $this->_state['temp_path'];
        $processedInBatch = 0;
        $format = $this->_feed->getFileFormat();

        // Determine generation mode
        $xmlStructure = $this->_feed->getXmlStructure();
        $xmlTemplate = $this->_feed->getXmlItemTemplate();
        $isStructureMode = !empty($xmlStructure) && $format === 'xml';
        $isTemplateMode = !empty($xmlTemplate) && $format === 'xml' && !$isStructureMode;
        $isCsvMode = $format === 'csv';
        $isJsonMode = $format === 'json';
        $isJsonlMode = $format === 'jsonl';
        $itemTag = trim($this->_feed->getXmlItemTag() ?: 'item');

        // Parse structure once if using structure mode
        $structure = null;
        if ($isStructureMode) {
            try {
                $structure = Mage::helper('core')->jsonDecode($xmlStructure);
            } catch (\JsonException $e) {
                Mage::log("FeedManager: Invalid XML structure JSON for feed '{$this->_feed->getName()}': {$e->getMessage()}", Mage::LOG_ERROR);
                throw new \RuntimeException("Feed has invalid XML structure configuration: {$e->getMessage()}", 0, $e);
            }
        }

        // For CSV, get headers from CSV columns
        $csvHeaders = [];
        $csvDelimiter = ',';
        $csvEnclosure = '"';
        if ($isCsvMode) {
            $csvColumns = $this->_feed->getCsvColumns();
            if ($csvColumns) {
                try {
                    $columns = Mage::helper('core')->jsonDecode($csvColumns);
                } catch (\JsonException $e) {
                    Mage::log("FeedManager: Invalid CSV columns JSON for feed '{$this->_feed->getName()}': {$e->getMessage()}", Mage::LOG_ERROR);
                    throw new \RuntimeException("Feed has invalid CSV columns configuration: {$e->getMessage()}", 0, $e);
                }
                if (is_array($columns) && !empty($columns)) {
                    $csvHeaders = array_column($columns, 'name');
                }
            }
            $delimiter = $this->_feed->getCsvDelimiter();
            if ($delimiter !== null && $delimiter !== '') {
                $csvDelimiter = $delimiter === '&#9;' ? "\t" : $delimiter;
            }
            $enclosure = $this->_feed->getCsvEnclosure();
            if ($enclosure !== null) {
                $csvEnclosure = $enclosure === '&quot;' ? '"' : ($enclosure === '&#39;' ? "'" : $enclosure);
            }
        }

        // For JSON, get structure if available
        $jsonStructure = null;
        if ($isJsonMode) {
            $jsonStructureData = $this->_feed->getJsonStructure();
            if ($jsonStructureData) {
                try {
                    $jsonStructure = Mage::helper('core')->jsonDecode($jsonStructureData);
                } catch (\JsonException $e) {
                    Mage::log("FeedManager: Invalid JSON structure for feed '{$this->_feed->getName()}': {$e->getMessage()}", Mage::LOG_ERROR);
                    throw new \RuntimeException("Feed has invalid JSON structure configuration: {$e->getMessage()}", 0, $e);
                }
            }
        }

        foreach ($collection as $product) {
            try {
                // Validate against Rule conditions
                if (!$this->_validateProductConditions($product)) {
                    $this->_state['processed_count']++;
                    continue;
                }

                // Load stock item
                $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
                $product->setStockItem($stockItem);

                if ($isStructureMode) {
                    // New visual builder XML generation
                    $output = $this->_mapper->mapProductToXmlStructure($product, $structure, $itemTag, 1);
                    file_put_contents($tempPath, $output, FILE_APPEND);
                } elseif ($isTemplateMode) {
                    // Legacy template-based XML generation
                    $itemXml = $this->_renderItemTemplate($xmlTemplate, $product, $this->_feed);

                    if ($itemTag !== '') {
                        $output = "  <{$itemTag}>\n";
                        $output .= '    ' . str_replace("\n", "\n    ", trim($itemXml)) . "\n";
                        $output .= "  </{$itemTag}>\n";
                    } else {
                        $output = '  ' . trim($itemXml) . "\n";
                    }

                    file_put_contents($tempPath, $output, FILE_APPEND);
                } elseif ($isCsvMode) {
                    // CSV generation
                    $mappedData = $this->_mapper->mapProduct($product);

                    // Write header row on first product
                    if (!($this->_state['csv_header_written'] ?? false)) {
                        if (empty($csvHeaders)) {
                            $csvHeaders = array_keys($mappedData);
                        }
                        if ($this->_feed->getCsvIncludeHeader() !== false) {
                            $this->_writeCsvRow($tempPath, $csvHeaders, $csvDelimiter, $csvEnclosure);
                        }
                        $this->_state['csv_header_written'] = true;
                        $this->_state['csv_headers'] = $csvHeaders;
                    }

                    // Write data row in header order
                    $row = [];
                    foreach ($this->_state['csv_headers'] as $header) {
                        $value = $mappedData[$header] ?? '';
                        if (is_array($value)) {
                            $value = implode(',', $value);
                        }
                        $row[] = (string) $value;
                    }
                    $this->_writeCsvRow($tempPath, $row, $csvDelimiter, $csvEnclosure);
                } elseif ($isJsonMode) {
                    // JSON generation
                    if ($jsonStructure) {
                        $mappedData = $this->_mapper->mapProductToJsonStructure($product, $jsonStructure);
                    } else {
                        $mappedData = $this->_mapper->mapProduct($product);
                    }

                    // Handle JSON array structure
                    $isFirstProduct = !($this->_state['json_started'] ?? false);
                    if ($isFirstProduct) {
                        file_put_contents($tempPath, '{"products":[' . "\n", FILE_APPEND);
                        $this->_state['json_started'] = true;
                    } else {
                        file_put_contents($tempPath, ",\n", FILE_APPEND);
                    }
                    file_put_contents($tempPath, json_encode($mappedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), FILE_APPEND);
                } elseif ($isJsonlMode) {
                    // JSONL generation — one JSON object per line
                    $mappedData = $this->_mapper->mapProduct($product);
                    $json = json_encode($mappedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                    file_put_contents($tempPath, $json . "\n", FILE_APPEND);
                }

                $this->_state['product_count']++;
                $processedInBatch++;
                $this->_state['processed_count']++;

            } catch (\Throwable $e) {
                $this->_state['errors'][] = "Product {$product->getSku()}: {$e->getMessage()}";
                $this->_state['processed_count']++;

                // Check error threshold (percentage-based)
                $this->_checkErrorThreshold(count($this->_state['errors']), $this->_state['processed_count']);
            }
        }

        $collection->clear();
        gc_collect_cycles();

        return $processedInBatch;
    }

    /**
     * Write a CSV row to file
     */
    protected function _writeCsvRow(string $filePath, array $row, string $delimiter = ',', string $enclosure = '"'): void
    {
        $handle = fopen($filePath, 'a');
        if ($handle) {
            fputcsv($handle, $row, $delimiter, $enclosure);
            fclose($handle);
        }
    }

    /**
     * Get temp file path
     */
    protected function _getTempFilePath(): string
    {
        $tmpDir = Mage::getBaseDir('var') . DS . 'feedmanager';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        return $tmpDir . DS . $this->_jobId . '.tmp';
    }

    /**
     * Get state file path
     */
    protected function _getStatePath(): string
    {
        $tmpDir = Mage::getBaseDir('var') . DS . 'feedmanager';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        return $tmpDir . DS . $this->_jobId . '.state.json';
    }

    /**
     * Acquire an exclusive lock on the state file to prevent concurrent access.
     *
     * Returns false if a lock cannot be acquired (another request is already
     * processing this job). The lock is held until _releaseStateLock() is called.
     */
    protected function _acquireStateLock(): bool
    {
        $lockPath = $this->_getStatePath() . '.lock';
        $this->_lockFile = new \Maho\Io\File();
        try {
            $this->_lockFile->streamOpen($lockPath, 'c', 0666);
        } catch (\Exception) {
            $this->_lockFile = null;
            return false;
        }

        if (!$this->_lockFile->streamLock(true, false)) {
            $this->_lockFile->streamClose();
            $this->_lockFile = null;
            return false;
        }

        return true;
    }

    /**
     * Release the state file lock
     */
    protected function _releaseStateLock(): void
    {
        if ($this->_lockFile !== null) {
            $this->_lockFile->streamClose();
            $this->_lockFile = null;
        }
    }

    /**
     * Save state to file (uses LOCK_EX for atomic writes)
     */
    protected function _saveState(): void
    {
        file_put_contents($this->_getStatePath(), Mage::helper('core')->jsonEncode($this->_state), LOCK_EX);
    }

    /**
     * Load state from file
     */
    protected function _loadState(): bool
    {
        $path = $this->_getStatePath();
        if (!file_exists($path)) {
            return false;
        }

        $file = new \Maho\Io\File();
        try {
            $file->streamOpen($path, 'r');
            $file->streamLock(false);
            $content = '';
            while (($chunk = $file->streamRead(8192)) !== false) {
                $content .= $chunk;
            }
            $file->streamClose();
        } catch (\Exception) {
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
     * Delete state file and its lock file
     */
    protected function _deleteState(): void
    {
        $path = $this->_getStatePath();
        if (file_exists($path)) {
            unlink($path);
        }
        $lockPath = $path . '.lock';
        if (file_exists($lockPath)) {
            unlink($lockPath);
        }
    }

    /**
     * Fail with error message
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
            'progress' => $this->_state['product_count'] ?? 0,
            'total' => $this->_state['total_products'] ?? 0,
            'batches_processed' => $this->_state['batches_processed'] ?? 0,
            'batches_total' => $this->_state['batches_total'] ?? 0,
            'message' => $message,
            'errors' => $this->_state['errors'],
        ];
    }

    /**
     * Handle upload after successful generation
     *
     * @return array{status: string, message: string}
     */
    protected function _handleUpload(): array
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
            $filePath = $this->_feed->getOutputFilePath();
            $extension = $this->_feed->getFileFormat();
            if ($this->_feed->getGzipCompression()) {
                $extension .= '.gz';
            }
            $remoteName = $this->_feed->getFilename() . '.' . $extension;

            $success = $uploader->upload($filePath, $remoteName);

            // Update destination last upload info
            $destination->setLastUploadAt(Mage_Core_Model_Locale::now())
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

    /**
     * Clean up stale running jobs for a feed
     * Jobs are considered stale if they've been running for more than 5 minutes
     */
    protected function _cleanupStaleJobs(int $feedId): void
    {
        $staleTimeout = 5 * 60; // 5 minutes

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

        // Also clean up any stale state files for this feed
        $tmpDir = Mage::getBaseDir('var') . DS . 'feedmanager';
        if (is_dir($tmpDir)) {
            foreach (glob($tmpDir . "/feed_{$feedId}_*.state.json") as $stateFile) {
                if (filemtime($stateFile) < time() - $staleTimeout) {
                    // Also clean up the temp and lock files
                    $tmpFile = str_replace('.state.json', '.tmp', $stateFile);
                    if (file_exists($tmpFile)) {
                        unlink($tmpFile);
                    }
                    $lockFile = $stateFile . '.lock';
                    if (file_exists($lockFile)) {
                        unlink($lockFile);
                    }
                    unlink($stateFile);
                }
            }
        }
    }

    /**
     * Clean up old state and temp files
     */
    public static function cleanupOldJobs(int $maxAgeHours = 24): int
    {
        $tmpDir = Mage::getBaseDir('var') . DS . 'feedmanager';
        if (!is_dir($tmpDir)) {
            return 0;
        }

        $cleaned = 0;
        $maxAge = time() - ($maxAgeHours * 3600);

        foreach (glob($tmpDir . '/*') as $file) {
            if (filemtime($file) < $maxAge) {
                unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }
}
