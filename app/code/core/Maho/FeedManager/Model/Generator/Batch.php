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
 * Batch Generator - Handles AJAX batch-by-batch feed generation
 *
 * This class manages stateful batch processing across multiple HTTP requests.
 * State is persisted to a JSON file between requests.
 */
class Maho_FeedManager_Model_Generator_Batch
{
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

        // Create log entry
        $this->_log = Mage::getModel('feedmanager/log');
        $this->_log->setFeedId($feed->getId())
            ->setStatus(Maho_FeedManager_Model_Log::STATUS_RUNNING)
            ->setStartedAt(Mage_Core_Model_Locale::now())
            ->save();

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
        // Load state
        $this->_jobId = $jobId;
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
        $this->_platform = Maho_FeedManager_Model_Platform::getAdapter($this->_feed->getPlatform());

        // Update status
        $this->_state['status'] = self::STATUS_PROCESSING;
        $this->_state['current_page']++;

        try {
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
        } catch (Exception $e) {
            return $this->_failWithError($e->getMessage());
        }
    }

    /**
     * Finalize the generation (validation, compression, cleanup)
     *
     * @return array{status: string, file_url: string, product_count: int, file_size: int, message: string}
     */
    public function finalize(string $jobId): array
    {
        // Load state
        $this->_jobId = $jobId;
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

        try {
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

            // Validate the generated feed
            $validator = new Maho_FeedManager_Model_Validator();
            if (!$validator->validate($tempPath, $this->_feed->getFileFormat())) {
                $errors = $validator->getErrors();
                foreach ($errors as $error) {
                    $this->_state['errors'][] = $error;
                }
                throw new RuntimeException('Feed validation failed: ' . implode(', ', $errors));
            }

            // Add validation warnings to errors
            foreach ($validator->getWarnings() as $warning) {
                $this->_state['errors'][] = "[Validation Warning] {$warning}";
            }

            // Move to final location
            $finalPath = $this->_getOutputPath();
            if (!rename($tempPath, $finalPath)) {
                throw new RuntimeException('Failed to move generated file to output directory');
            }

            // Apply gzip compression if enabled
            if (Mage::helper('feedmanager')->isGzipCompressionEnabled()) {
                $finalPath = $this->_compressFile($finalPath);
            }

            // Get file size
            $fileSize = file_exists($finalPath) ? filesize($finalPath) : 0;

            // Update log with success
            $this->_log->setStatus(Maho_FeedManager_Model_Log::STATUS_COMPLETED)
                ->setCompletedAt(Mage_Core_Model_Locale::now())
                ->setProductCount($this->_state['product_count'])
                ->setFileSize($fileSize);

            // Save errors if any
            if (!empty($this->_state['errors'])) {
                foreach ($this->_state['errors'] as $error) {
                    $this->_log->addError($error);
                }
            }
            $this->_log->save();

            // Update feed
            $this->_feed->setLastGeneratedAt(Mage_Core_Model_Locale::now())
                ->setLastProductCount($this->_state['product_count'])
                ->setLastFileSize($fileSize)
                ->save();

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
        } catch (Exception $e) {
            return $this->_failWithError($e->getMessage());
        }
    }

    /**
     * Cancel a running batch job
     */
    public function cancel(string $jobId): array
    {
        $this->_jobId = $jobId;
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
        $itemTag = trim($this->_feed->getXmlItemTag() ?: 'item');

        // Parse structure once if using structure mode
        $structure = null;
        if ($isStructureMode) {
            $structure = Mage::helper('core')->jsonDecode($xmlStructure);
        }

        // For CSV, get headers from CSV columns or mapper
        $csvHeaders = [];
        $csvDelimiter = ',';
        $csvEnclosure = '"';
        if ($isCsvMode) {
            $csvColumns = $this->_feed->getCsvColumns();
            if ($csvColumns) {
                $columns = Mage::helper('core')->jsonDecode($csvColumns);
                if (is_array($columns) && !empty($columns)) {
                    $csvHeaders = array_column($columns, 'name');
                    $this->_mapper->setMappingsFromCsvColumns($columns);
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
                $jsonStructure = Mage::helper('core')->jsonDecode($jsonStructureData);
                if (is_array($jsonStructure) && !empty($jsonStructure)) {
                    $this->_mapper->setMappingsFromJsonStructure($jsonStructure);
                }
            }
        }

        foreach ($collection as $product) {
            try {
                // Validate against Rule conditions (legacy Mage_Rule system)
                if (!$this->_validateProductConditions($product)) {
                    $this->_state['processed_count']++;
                    continue;
                }

                // Validate against condition groups (new AND/OR system)
                if (!$this->_validateConditionGroups($product)) {
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
                    file_put_contents($tempPath, json_encode($mappedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), FILE_APPEND);
                }

                $this->_state['product_count']++;
                $processedInBatch++;
                $this->_state['processed_count']++;

            } catch (Exception $e) {
                $this->_state['errors'][] = "Product {$product->getSku()}: {$e->getMessage()}";
                $this->_state['processed_count']++;

                // Stop if too many errors
                if (count($this->_state['errors']) > 100) {
                    throw new RuntimeException('Too many errors during generation. Aborting.');
                }
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
     * Get product collection with filters applied
     */
    protected function _getProductCollection(): Mage_Catalog_Model_Resource_Product_Collection
    {
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->setStoreId($this->_feed->getStoreId());

        // Add required attributes
        $collection->addAttributeToSelect('*');

        // Apply exclude disabled products filter
        if ($this->_feed->getExcludeDisabled()) {
            $collection->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        }

        // Apply exclude out of stock products filter
        if ($this->_feed->getExcludeOutOfStock()) {
            /** @phpstan-ignore argument.type (Works with product collection despite PHPDoc) */
            Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);
        }

        // Apply product type filter
        $includeTypes = $this->_feed->getData('include_product_types');
        if (!empty($includeTypes)) {
            $types = array_map('trim', explode(',', $includeTypes));
            $collection->addAttributeToFilter('type_id', ['in' => $types]);
        }

        // Only visible products
        $collection->addAttributeToFilter('visibility', [
            'in' => [
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            ],
        ]);

        // Collect validated attributes from conditions
        $conditions = $this->_feed->getConditions();
        if (method_exists($conditions, 'collectValidatedAttributes')) {
            $conditions->collectValidatedAttributes($collection);
        }

        // Apply condition groups as SQL filters (much more efficient than PHP filtering)
        $this->_applyConditionGroupsToCollection($collection);

        return $collection;
    }

    /**
     * Apply condition groups to collection as SQL WHERE clauses
     *
     * Delegates to Filter_Condition model for efficient SQL-based filtering.
     */
    protected function _applyConditionGroupsToCollection(Mage_Catalog_Model_Resource_Product_Collection $collection): void
    {
        $groups = $this->_feed->getConditionGroupsArray();
        Mage::getSingleton('feedmanager/filter_condition')->applyConditionGroupsToCollection($collection, $groups);
    }

    /**
     * Validate product against Rule conditions (legacy - kept for complex rule scenarios)
     */
    protected function _validateProductConditions(Mage_Catalog_Model_Product $product): bool
    {
        $conditions = $this->_feed->getConditions();
        if (!$conditions->getConditions()) {
            return true;
        }
        return $conditions->validate($product);
    }

    /**
     * Validate product against condition groups - now only used as fallback
     * Primary filtering is done via SQL in _applyConditionGroupsToCollection
     */
    protected function _validateConditionGroups(Mage_Catalog_Model_Product $product): bool
    {
        // Since we now apply conditions at SQL level, this always returns true
        // The collection already has the filters applied
        return true;
    }

    /**
     * Legacy method - kept for reference but no longer used
     */
    protected function _validateConditionGroupsLegacy(Mage_Catalog_Model_Product $product): bool
    {
        $groups = $this->_feed->getConditionGroupsArray();
        if (empty($groups)) {
            return true;
        }

        foreach ($groups as $group) {
            $conditions = $group['conditions'] ?? [];
            if (empty($conditions)) {
                continue;
            }

            $groupPassed = false;
            foreach ($conditions as $condition) {
                if ($this->_evaluateCondition($product, $condition)) {
                    $groupPassed = true;
                    break;
                }
            }

            if (!$groupPassed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition
     */
    protected function _evaluateCondition(Mage_Catalog_Model_Product $product, array $condition): bool
    {
        $attribute = $condition['attribute'] ?? '';
        $operator = $condition['operator'] ?? 'eq';
        $value = $condition['value'] ?? '';

        if (empty($attribute)) {
            return true;
        }

        $productValue = $this->_getProductAttributeValue($product, $attribute);

        return match ($operator) {
            'eq' => (string) $productValue === $value,
            'neq' => (string) $productValue !== $value,
            'gt' => (float) $productValue > (float) $value,
            'gteq' => (float) $productValue >= (float) $value,
            'lt' => (float) $productValue < (float) $value,
            'lteq' => (float) $productValue <= (float) $value,
            'in' => in_array((string) $productValue, array_map('trim', explode(',', $value))),
            'nin' => !in_array((string) $productValue, array_map('trim', explode(',', $value))),
            'like' => str_contains(strtolower((string) $productValue), strtolower($value)),
            'nlike' => !str_contains(strtolower((string) $productValue), strtolower($value)),
            'null' => $productValue === null || $productValue === '',
            'notnull' => $productValue !== null && $productValue !== '',
            default => (string) $productValue === $value,
        };
    }

    /**
     * Get product attribute value
     */
    protected function _getProductAttributeValue(Mage_Catalog_Model_Product $product, string $attribute): mixed
    {
        $stockItem = $product->getStockItem();
        return match ($attribute) {
            'qty' => (float) ($stockItem ? $stockItem->getQty() : 0),
            'is_in_stock' => (int) ($stockItem ? $stockItem->getIsInStock() : 0),
            'category_ids' => implode(',', $product->getCategoryIds()),
            default => $product->getData($attribute),
        };
    }

    /**
     * Render header or footer template
     */
    protected function _renderHeaderFooter(string $template, Maho_FeedManager_Model_Feed $feed): string
    {
        $store = Mage::app()->getStore($feed->getStoreId());
        $replacements = [
            '{{store_name}}' => $store->getName(),
            '{{store_url}}' => $store->getBaseUrl(),
            '{{generation_date}}' => date('Y-m-d H:i:s'),
        ];
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Render item template for a product
     */
    protected function _renderItemTemplate(string $template, Mage_Catalog_Model_Product $product, Maho_FeedManager_Model_Feed $feed): string
    {
        // Build product data array for transformer context
        $productData = $product->getData();
        $productData['_product'] = $product;

        // Find all field configurations in the template: {type="..." value="..." ...}
        // Use a more robust regex that handles nested {{}} placeholders in transformer options
        preg_match_all('/\{(type="(?:[^"\\\\]|\\\\.)*"(?:\s+\w+="(?:[^"\\\\]|\\\\.)*")*)\}/', $template, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $fullMatch = $match[0];
            $configStr = $match[1];
            $config = $this->_parseFieldConfig($configStr);

            if (empty($config)) {
                continue;
            }

            $value = $this->_getValueFromConfig($product, $config, $feed);
            $value = $this->_applyFormat($value, $config, $feed, $productData);

            // Apply length limit (backward compatibility - transformers handle this via truncate)
            if (!empty($config['length']) && empty($config['transformers'])) {
                $value = mb_substr($value, 0, (int) $config['length']);
            }

            $template = str_replace($fullMatch, $value, $template);
        }

        // Support simple {{placeholder}} syntax
        preg_match_all('/\{\{([^}]+)\}\}/', $template, $simpleMatches);
        foreach ($simpleMatches[1] as $placeholder) {
            $value = $this->_getProductValueForTemplate($product, $placeholder, $feed);
            $template = str_replace('{{' . $placeholder . '}}', $value, $template);
        }

        return $template;
    }

    /**
     * Parse field configuration string
     */
    protected function _parseFieldConfig(string $configStr): array
    {
        $config = [];
        preg_match_all('/(\w+)="([^"]*)"/', $configStr, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $config[$match[1]] = $match[2];
        }
        return $config;
    }

    /**
     * Get value from field configuration
     */
    protected function _getValueFromConfig(Mage_Catalog_Model_Product $product, array $config, Maho_FeedManager_Model_Feed $feed): string
    {
        $type = $config['type'] ?? 'attribute';
        $value = $config['value'] ?? '';

        return match ($type) {
            'attribute' => $this->_getProductValueForTemplate($product, $value, $feed),
            'text' => $value,
            'combined' => $this->_processCombinedTemplate($value, $product, $feed),
            'category' => $this->_getProductCategoryPath($product),
            'images' => $this->_getAdditionalImage($product, $value),
            'custom_field' => (string) $product->getData($value),
            default => $this->_getProductValueForTemplate($product, $value, $feed),
        };
    }

    /**
     * Process combined template with {{placeholder}} syntax
     */
    protected function _processCombinedTemplate(
        string $template,
        Mage_Catalog_Model_Product $product,
        Maho_FeedManager_Model_Feed $feed,
    ): string {
        // Find all {{placeholder}} in the template
        preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);

        $result = $template;
        foreach ($matches[1] as $placeholder) {
            $value = $this->_getProductValueForTemplate($product, $placeholder, $feed);
            $result = str_replace('{{' . $placeholder . '}}', $value, $result);
        }

        return $result;
    }

    /**
     * Apply formatting to value
     *
     * @param array<string, mixed> $productData
     */
    protected function _applyFormat(string $value, array $config, Maho_FeedManager_Model_Feed $feed, array $productData = []): string
    {
        // Check for new transformer chain format first
        if (!empty($config['transformers'])) {
            $chain = Maho_FeedManager_Model_Transformer::parseChainString($config['transformers']);
            if (!empty($chain)) {
                return (string) Maho_FeedManager_Model_Transformer::pipeline($value, $chain, $productData);
            }
        }

        // Legacy format support
        $format = $config['format'] ?? 'as_is';

        return match ($format) {
            'html_escape' => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
            'strip_tags' => strip_tags($value),
            'price' => $this->_formatPrice((float) $value, $feed),
            'date' => $this->_formatDate($value),
            'lowercase' => strtolower($value),
            'uppercase' => strtoupper($value),
            'integer' => (string) (int) $value,
            'base' => $this->_ensureBaseUrl($value),
            default => $value,
        };
    }

    /**
     * Get product value for template placeholder
     */
    protected function _getProductValueForTemplate(Mage_Catalog_Model_Product $product, string $placeholder, Maho_FeedManager_Model_Feed $feed): string
    {
        return match ($placeholder) {
            'sku' => (string) $product->getSku(),
            'name' => (string) $product->getName(),
            'description' => strip_tags((string) $product->getDescription()),
            'short_description' => strip_tags((string) $product->getShortDescription()),
            'url' => $this->_getProductUrl($product, $feed),
            'image', 'image_url' => $this->_getProductImageUrl($product, $feed),
            'small_image' => $this->_getProductImageUrl($product, $feed, 'small_image'),
            'thumbnail' => $this->_getProductImageUrl($product, $feed, 'thumbnail'),
            'price' => $this->_formatPrice((float) $product->getPrice(), $feed),
            'special_price' => $product->getSpecialPrice() ? $this->_formatPrice((float) $product->getSpecialPrice(), $feed) : '',
            'final_price' => $this->_formatPrice((float) $product->getFinalPrice(), $feed),
            'stock_status' => ($si = $product->getStockItem()) && $si->getIsInStock() ? 'in stock' : 'out of stock',
            'qty' => (string) (int) (($si = $product->getStockItem()) ? $si->getQty() : 0),
            'category' => $this->_getProductCategoryPath($product),
            'brand' => (string) $product->getAttributeText('brand'),
            'manufacturer' => (string) $product->getAttributeText('manufacturer'),
            'weight' => (string) $product->getWeight(),
            'type_id' => (string) $product->getTypeId(),
            'gtin' => (string) ($product->getData('gtin') ?: $product->getData('upc') ?: $product->getData('ean')),
            'mpn' => (string) ($product->getData('mpn') ?: $product->getSku()),
            default => $this->_getGenericAttribute($product, $placeholder),
        };
    }

    /**
     * Get generic attribute value
     */
    protected function _getGenericAttribute(Mage_Catalog_Model_Product $product, string $code): string
    {
        $value = $product->getData($code);
        if ($value !== null) {
            $textValue = $product->getAttributeText($code);
            if ($textValue && !is_array($textValue)) {
                return (string) $textValue;
            }
            if (is_array($textValue)) {
                return implode(', ', $textValue);
            }
        }
        return (string) $value;
    }

    /**
     * Get product URL
     */
    protected function _getProductUrl(Mage_Catalog_Model_Product $product, Maho_FeedManager_Model_Feed $feed): string
    {
        $product->setStoreId($feed->getStoreId());
        if ($feed->getExcludeCategoryUrl()) {
            return $product->getUrlModel()->getUrl($product, ['_ignore_category' => true]);
        }
        return $product->getProductUrl();
    }

    /**
     * Get product image URL
     */
    protected function _getProductImageUrl(Mage_Catalog_Model_Product $product, Maho_FeedManager_Model_Feed $feed, string $type = 'image'): string
    {
        $image = $product->getData($type);
        if (!$image || $image === 'no_selection') {
            return $feed->getNoImageUrl() ?: '';
        }
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $image;
    }

    /**
     * Get additional image by index
     */
    protected function _getAdditionalImage(Mage_Catalog_Model_Product $product, string $imageKey): string
    {
        if (preg_match('/image_(\d+)/', $imageKey, $matches)) {
            $index = (int) $matches[1] - 1;
            $mediaGallery = $product->getMediaGalleryImages();
            if ($mediaGallery && $mediaGallery->getSize() > $index) {
                $items = $mediaGallery->getItems();
                $item = array_values($items)[$index] ?? null;
                if ($item) {
                    return $item->getUrl();
                }
            }
        }
        return '';
    }

    /**
     * Get product category path
     */
    protected function _getProductCategoryPath(Mage_Catalog_Model_Product $product): string
    {
        $categoryIds = $product->getCategoryIds();
        if (empty($categoryIds)) {
            return '';
        }

        $categoryId = end($categoryIds);
        $category = Mage::getModel('catalog/category')->load($categoryId);
        if (!$category->getId()) {
            return '';
        }

        $path = [];
        $pathIds = explode('/', $category->getPath());
        foreach ($pathIds as $pathId) {
            if ($pathId <= 2) {
                continue;
            }
            $pathCategory = Mage::getModel('catalog/category')->load($pathId);
            if ($pathCategory->getId()) {
                $path[] = $pathCategory->getName();
            }
        }

        return implode(' > ', $path);
    }

    /**
     * Format price
     */
    protected function _formatPrice(float $price, Maho_FeedManager_Model_Feed $feed): string
    {
        $decimals = (int) ($feed->getPriceDecimals() ?? 2);
        $decimalPoint = $feed->getPriceDecimalPoint() ?: '.';
        $thousandsSep = $feed->getPriceThousandsSep() ?? '';
        $currency = $feed->getPriceCurrency() ?: 'AUD';

        return number_format($price, $decimals, $decimalPoint, $thousandsSep) . ' ' . $currency;
    }

    /**
     * Format date
     */
    protected function _formatDate(string $value): string
    {
        if (empty($value)) {
            return '';
        }
        try {
            $date = new DateTime($value);
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            return $value;
        }
    }

    /**
     * Ensure URL has base URL
     */
    protected function _ensureBaseUrl(string $value): string
    {
        if (empty($value) || str_starts_with($value, 'http')) {
            return $value;
        }
        if (str_starts_with($value, '/')) {
            return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . ltrim($value, '/');
        }
        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $value;
    }

    /**
     * Compress file with gzip
     */
    protected function _compressFile(string $sourcePath): string
    {
        $gzPath = $sourcePath . '.gz';
        $source = fopen($sourcePath, 'rb');
        $dest = gzopen($gzPath, 'wb9');

        while (!feof($source)) {
            gzwrite($dest, fread($source, 1048576));
        }

        fclose($source);
        gzclose($dest);
        unlink($sourcePath);

        return $gzPath;
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
     * Get final output path
     */
    protected function _getOutputPath(): string
    {
        $outputDir = Mage::helper('feedmanager')->getOutputDirectory();
        $filename = $this->_feed->getFilename();
        $extension = $this->_feed->getFileFormat() ?: 'xml';
        return $outputDir . DS . $filename . '.' . $extension;
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
     * Save state to file
     */
    protected function _saveState(): void
    {
        file_put_contents($this->_getStatePath(), Mage::helper('core')->jsonEncode($this->_state));
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

        $content = file_get_contents($path);
        $this->_state = Mage::helper('core')->jsonDecode($content);

        return !empty($this->_state);
    }

    /**
     * Delete state file
     */
    protected function _deleteState(): void
    {
        $path = $this->_getStatePath();
        if (file_exists($path)) {
            unlink($path);
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
            foreach ($this->_state['errors'] as $error) {
                $this->_log->addError($error);
            }
            $this->_log->save();
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
            $remoteName = $this->_feed->getFilename() . '.' . $this->_feed->getFileFormat();

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
        } catch (Exception $e) {
            Mage::logException($e);
            $message = $e->getMessage();
            $this->_log->recordUploadFailure($destinationId, $message);
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
                    // Also clean up the temp file
                    $tmpFile = str_replace('.state.json', '.tmp', $stateFile);
                    if (file_exists($tmpFile)) {
                        unlink($tmpFile);
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
