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
 * Feed Generator
 *
 * Orchestrates feed generation with streaming/batching support for large catalogs.
 *
 * Error Handling Pattern:
 * - generate(): Returns Log model with status, catches all exceptions internally
 * - Product processing: Errors are collected in Log, generation continues with remaining products
 * - File operations: Uses atomic writes (temp file → final), cleans up on failure
 * - Static getGenerationStatus(): Returns array with status info, never throws
 */
class Maho_FeedManager_Model_Generator
{
    use Maho_FeedManager_Model_Generator_ProductProcessorTrait;

    protected Maho_FeedManager_Model_Feed $_feed;
    protected Maho_FeedManager_Model_Mapper $_mapper;
    protected ?Maho_FeedManager_Model_Writer_WriterInterface $_writer = null;
    protected ?Maho_FeedManager_Model_Platform_AdapterInterface $_platform = null;
    protected ?Maho_FeedManager_Model_Log $_log = null;
    protected int $_batchSize = 1000;
    protected int $_productCount = 0;
    protected int $_errorCount = 0;
    protected array $_errors = [];
    protected ?string $_tempPath = null;

    /**
     * Generate a feed
     *
     * @return Maho_FeedManager_Model_Log Generation log
     */
    public function generate(Maho_FeedManager_Model_Feed $feed): Maho_FeedManager_Model_Log
    {
        $this->_feed = $feed;
        $this->_platform = Maho_FeedManager_Model_Platform::getAdapter($feed->getPlatform());
        $this->_batchSize = Mage::helper('feedmanager')->getBatchSize();
        $this->_productCount = 0;
        $this->_errorCount = 0;
        $this->_errors = [];

        // Check for existing running generation (race condition prevention)
        $existingLog = $this->_acquireGenerationLock(false);
        if ($existingLog) {
            return $existingLog;
        }

        try {
            // Initialize mapper
            $this->_mapper = new Maho_FeedManager_Model_Mapper($feed);

            // Configure mapper with builder definitions if available
            $this->_configureMapperFromBuilder();

            // Get output path and create temp path for atomic writes
            $outputPath = $this->_getOutputPath();
            $outputDir = dirname($outputPath);
            $this->_tempPath = $outputDir . DS . 'feed_' . $feed->getId() . '.tmp';

            // Check XML generation mode
            $xmlStructure = $feed->getXmlStructure();
            $xmlTemplate = $feed->getXmlItemTemplate();

            if (!empty($xmlStructure) && $feed->getFileFormat() === 'xml') {
                // New visual builder XML generation
                $this->_generateXmlStructureFile($this->_tempPath);
            } elseif (!empty($xmlTemplate) && $feed->getFileFormat() === 'xml') {
                // Legacy template-based XML generation
                $this->_generateXmlTemplateFile($this->_tempPath);
            } else {
                // Standard writer-based generation
                $this->_writer = $this->_createWriter($feed->getFileFormat());

                // Open writer
                $this->_writer->open($this->_tempPath, $this->_platform);

                // Process products in batches
                $this->_processProducts();

                // Close writer
                $this->_writer->close();
            }

            // Validate, move to output, and compress
            $finalPath = $this->_validateAndMoveToOutput($this->_tempPath, $this->_errors);
            $this->_tempPath = null; // Clear temp path after successful move

            // Finalize: update log, feed, and reset notifier
            $fileSize = file_exists($finalPath) ? filesize($finalPath) : 0;
            $this->_finalizeGenerationSuccess($this->_productCount, $fileSize, $this->_errors);

            Mage::log(
                "FeedManager: Generated feed '{$feed->getName()}' with {$this->_productCount} products",
                Mage::LOG_INFO,
            );

        } catch (Exception $e) {
            $this->_errors[] = $e->getMessage();
            $this->_log->setStatus(Maho_FeedManager_Model_Log::STATUS_FAILED);

            // Clean up temp file on failure (preserves existing feed file)
            if ($this->_tempPath && file_exists($this->_tempPath)) {
                @unlink($this->_tempPath);
            }

            Mage::logException($e);
            Mage::log(
                "FeedManager: Failed to generate feed '{$feed->getName()}': {$e->getMessage()}",
                Mage::LOG_ERROR,
            );

            // Send failure notification
            $notifier = new Maho_FeedManager_Model_Notifier();
            $notifier->notify($feed, $this->_errors, 'generation');

            $this->_saveErrorsToLog($this->_errors);
            $this->_log->save();
        }

        return $this->_log;
    }

    /**
     * Process products in batches
     */
    protected function _processProducts(): void
    {
        $collection = $this->_getProductCollection();
        $totalProducts = $collection->getSize();

        // Store total for progress tracking
        $this->_log->setData('total_products', $totalProducts)->save();

        Mage::log(
            "FeedManager: Processing {$totalProducts} products for feed '{$this->_feed->getName()}'",
            Mage::LOG_INFO,
        );

        $page = 1;
        $processed = 0;

        while ($processed < $totalProducts) {
            $collection = $this->_getProductCollection();
            $collection->setPageSize($this->_batchSize);
            $collection->setCurPage($page);

            // Preload parent mappings for this batch to avoid N+1 queries
            $productIds = $collection->getAllIds();
            if (!empty($productIds)) {
                $this->_mapper->preloadParentMappings($productIds);
            }

            foreach ($collection as $product) {
                try {
                    // Validate against Rule conditions
                    if (!$this->_validateProductConditions($product)) {
                        $processed++;
                        continue;
                    }

                    $this->_processProduct($product);
                    $processed++;

                    // Update progress every 100 products
                    if ($processed % 100 === 0) {
                        $this->_log->setProductCount($this->_productCount)->save();
                    }
                } catch (Exception $e) {
                    $this->_errorCount++;
                    $this->_errors[] = "Product {$product->getSku()}: {$e->getMessage()}";
                    $processed++;

                    // Check error threshold (percentage-based)
                    $this->_checkErrorThreshold($this->_errorCount, $processed);
                }
            }

            $collection->clear();
            $page++;

            // Memory cleanup
            if ($page % 10 === 0) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Get current generation status for a feed
     *
     * @return array{status: string, progress: int, total: int, message: string, log_id?: int, started_at?: string, completed_at?: string|null, file_size?: int}
     */
    public static function getGenerationStatus(int $feedId): array
    {
        $log = Mage::getResourceModel('feedmanager/log_collection')
            ->addFeedFilter($feedId)
            ->setOrder('started_at', 'DESC')
            ->setPageSize(1)
            ->getFirstItem();

        if (!$log->getId()) {
            return [
                'status' => 'none',
                'progress' => 0,
                'total' => 0,
                'message' => 'No generation history',
            ];
        }

        $status = $log->getStatus();
        $progress = (int) $log->getProductCount();
        $total = (int) $log->getData('total_products') ?: $progress;

        $message = match ($status) {
            Maho_FeedManager_Model_Log::STATUS_RUNNING => "Processing products ({$progress}" . ($total ? "/{$total}" : '') . ')',
            Maho_FeedManager_Model_Log::STATUS_COMPLETED => "Completed with {$progress} products",
            Maho_FeedManager_Model_Log::STATUS_FAILED => 'Failed: ' . ($log->getErrorMessagesArray()[0] ?? 'Unknown error'),
            default => 'Unknown status',
        };

        return [
            'status' => $status,
            'progress' => $progress,
            'total' => $total,
            'message' => $message,
            'log_id' => $log->getId(),
            'started_at' => $log->getStartedAt(),
            'completed_at' => $log->getCompletedAt(),
            'file_size' => $log->getFileSize(),
        ];
    }

    /**
     * Check if feed is currently being generated
     * Returns false if the job has been running for more than 30 minutes (stuck)
     */
    public static function isGenerating(int $feedId): bool
    {
        $status = self::getGenerationStatus($feedId);

        if ($status['status'] !== Maho_FeedManager_Model_Log::STATUS_RUNNING) {
            return false;
        }

        // Check if the job is stuck (running for more than 30 minutes)
        if (!empty($status['started_at'])) {
            $startedAt = strtotime($status['started_at']);
            $maxRunTime = 30 * 60; // 30 minutes
            if (time() - $startedAt > $maxRunTime) {
                // Mark as failed due to timeout
                self::markStuckJobAsFailed($feedId);
                return false;
            }
        }

        return true;
    }

    /**
     * Mark a stuck job as failed
     */
    public static function markStuckJobAsFailed(int $feedId): void
    {
        /** @var Maho_FeedManager_Model_Resource_Log_Collection $collection */
        $collection = Mage::getResourceModel('feedmanager/log_collection')
            ->addFeedFilter($feedId)
            ->addFieldToFilter('status', Maho_FeedManager_Model_Log::STATUS_RUNNING)
            ->setOrder('started_at', 'DESC')
            ->setPageSize(1);

        foreach ($collection as $log) {
            /** @var Maho_FeedManager_Model_Log $log */
            $log->setStatus(Maho_FeedManager_Model_Log::STATUS_FAILED)
                ->addError('Generation timed out after 30 minutes')
                ->save();
            break;
        }
    }

    /**
     * Process a single product
     */
    protected function _processProduct(Mage_Catalog_Model_Product $product): void
    {
        // Load stock item
        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
        $product->setStockItem($stockItem);

        // Map product data - use JSON structure if available for JSON format
        $format = $this->_feed->getFileFormat();
        if ($format === 'json' && $this->_feed->getJsonStructure()) {
            $structure = Mage::helper('core')->jsonDecode($this->_feed->getJsonStructure());
            if (is_array($structure) && !empty($structure)) {
                $mappedData = $this->_mapper->mapProductToJsonStructure($product, $structure);
            } else {
                $mappedData = $this->_mapper->mapProduct($product);
            }
        } else {
            $mappedData = $this->_mapper->mapProduct($product);
        }

        // Validate if platform supports it
        if ($this->_platform) {
            $validationErrors = $this->_platform->validateProductData($mappedData);
            if (!empty($validationErrors)) {
                // Log validation errors but still include product if it has required fields
                foreach ($validationErrors as $error) {
                    $this->_errors[] = "Product {$product->getSku()}: {$error}";
                }
            }
        }

        // Write to output
        $this->_writer->writeProduct($mappedData);
        $this->_productCount++;
    }

    /**
     * Create appropriate writer for format
     */
    protected function _createWriter(string $format): Maho_FeedManager_Model_Writer_WriterInterface
    {
        $writer = match ($format) {
            'xml' => new Maho_FeedManager_Model_Writer_Xml(),
            'csv' => new Maho_FeedManager_Model_Writer_Csv(),
            'json' => new Maho_FeedManager_Model_Writer_Json(),
            'jsonl' => new Maho_FeedManager_Model_Writer_Jsonl(),
            default => throw new InvalidArgumentException("Unsupported format: {$format}"),
        };

        // Configure writer from feed if available
        if (method_exists($writer, 'configureFromFeed')) {
            $writer->configureFromFeed($this->_feed);
        }

        return $writer;
    }

    /**
     * Generate XML file using template-based approach
     */
    protected function _generateXmlTemplateFile(string $outputPath): void
    {
        $feed = $this->_feed;
        $handle = fopen($outputPath, 'w');

        if ($handle === false) {
            throw new RuntimeException("Cannot open file for writing: {$outputPath}");
        }

        try {
            // Write header
            $header = $feed->getXmlHeader();
            if (!empty($header)) {
                fwrite($handle, $this->_renderHeaderFooter($header, $feed) . "\n");
            }

            // Get product collection
            $collection = $this->_getProductCollection();
            $totalProducts = $collection->getSize();

            // Store total for progress tracking
            $this->_log->setData('total_products', $totalProducts)->save();

            Mage::log(
                "FeedManager: Processing {$totalProducts} products for feed '{$feed->getName()}' (template mode)",
                Mage::LOG_INFO,
            );

            $itemTemplate = $feed->getXmlItemTemplate();
            $itemTag = trim($feed->getXmlItemTag() ?: '');
            $page = 1;
            $processed = 0;

            while ($processed < $totalProducts) {
                $collection = $this->_getProductCollection();
                $collection->setPageSize($this->_batchSize);
                $collection->setCurPage($page);

                // Preload parent mappings for this batch to avoid N+1 queries
                $productIds = $collection->getAllIds();
                if (!empty($productIds)) {
                    $this->_mapper->preloadParentMappings($productIds);
                }

                foreach ($collection as $product) {
                    try {
                        // Validate against Rule conditions
                        if (!$this->_validateProductConditions($product)) {
                            $processed++;
                            continue;
                        }

                        // Load stock item
                        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
                        $product->setStockItem($stockItem);

                        // Render item content
                        $itemXml = $this->_renderItemTemplate($itemTemplate, $product, $feed);

                        // Wrap with item tag if configured
                        if ($itemTag !== '') {
                            fwrite($handle, "  <{$itemTag}>\n");
                            fwrite($handle, '    ' . str_replace("\n", "\n    ", trim($itemXml)) . "\n");
                            fwrite($handle, "  </{$itemTag}>\n");
                        } else {
                            fwrite($handle, '  ' . trim($itemXml) . "\n");
                        }
                        $this->_productCount++;

                        $processed++;

                        // Update progress every 100 products
                        if ($processed % 100 === 0) {
                            $this->_log->setProductCount($this->_productCount)->save();
                        }
                    } catch (Exception $e) {
                        $this->_errorCount++;
                        $this->_errors[] = "Product {$product->getSku()}: {$e->getMessage()}";
                        $processed++;

                        // Check error threshold (percentage-based)
                        $this->_checkErrorThreshold($this->_errorCount, $processed);
                    }
                }

                $collection->clear();
                $page++;

                // Memory cleanup
                if ($page % 10 === 0) {
                    gc_collect_cycles();
                }
            }

            // Write footer
            $footer = $feed->getXmlFooter();
            if (!empty($footer)) {
                fwrite($handle, $this->_renderHeaderFooter($footer, $feed) . "\n");
            }

        } finally {
            fclose($handle);
        }
    }

    /**
     * Generate XML file using visual builder structure
     */
    protected function _generateXmlStructureFile(string $outputPath): void
    {
        $feed = $this->_feed;
        $handle = fopen($outputPath, 'w');

        if ($handle === false) {
            throw new RuntimeException("Cannot open file for writing: {$outputPath}");
        }

        try {
            // Write header
            $header = $feed->getXmlHeader();
            if (!empty($header)) {
                fwrite($handle, $this->_renderHeaderFooter($header, $feed) . "\n");
            }

            // Get product collection
            $collection = $this->_getProductCollection();
            $totalProducts = $collection->getSize();

            // Store total for progress tracking
            $this->_log->setData('total_products', $totalProducts)->save();

            Mage::log(
                "FeedManager: Processing {$totalProducts} products for feed '{$feed->getName()}' (XML structure mode)",
                Mage::LOG_INFO,
            );

            // Parse the XML structure
            $structureJson = $feed->getXmlStructure();
            $structure = Mage::helper('core')->jsonDecode($structureJson);
            $itemTag = trim($feed->getXmlItemTag() ?: 'item');
            $page = 1;
            $processed = 0;

            while ($processed < $totalProducts) {
                $collection = $this->_getProductCollection();
                $collection->setPageSize($this->_batchSize);
                $collection->setCurPage($page);

                // Preload parent mappings for this batch to avoid N+1 queries
                $productIds = $collection->getAllIds();
                if (!empty($productIds)) {
                    $this->_mapper->preloadParentMappings($productIds);
                }

                foreach ($collection as $product) {
                    try {
                        // Validate against Rule conditions
                        if (!$this->_validateProductConditions($product)) {
                            $processed++;
                            continue;
                        }

                        // Load stock item
                        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
                        $product->setStockItem($stockItem);

                        // Generate XML using Mapper
                        $itemXml = $this->_mapper->mapProductToXmlStructure($product, $structure, $itemTag, 1);
                        fwrite($handle, $itemXml);

                        $this->_productCount++;
                        $processed++;

                        // Update progress every 100 products
                        if ($processed % 100 === 0) {
                            $this->_log->setProductCount($this->_productCount)->save();
                        }
                    } catch (Exception $e) {
                        $this->_errorCount++;
                        $this->_errors[] = "Product {$product->getSku()}: {$e->getMessage()}";
                        $processed++;

                        // Check error threshold (percentage-based)
                        $this->_checkErrorThreshold($this->_errorCount, $processed);
                    }
                }

                $collection->clear();
                $page++;

                // Memory cleanup
                if ($page % 10 === 0) {
                    gc_collect_cycles();
                }
            }

            // Write footer
            $footer = $feed->getXmlFooter();
            if (!empty($footer)) {
                fwrite($handle, $this->_renderHeaderFooter($footer, $feed) . "\n");
            }

        } finally {
            fclose($handle);
        }
    }

    /**
     * Get errors from last generation
     */
    public function getErrors(): array
    {
        return $this->_errors;
    }

    /**
     * Get product count from last generation
     */
    public function getProductCount(): int
    {
        return $this->_productCount;
    }

    /**
     * Generate a preview of the feed with limited products
     *
     * @param Maho_FeedManager_Model_Feed $feed Feed to preview
     * @param int $limit Number of products to include in preview
     * @return string Preview output
     */
    public function generatePreview(Maho_FeedManager_Model_Feed $feed, int $limit = 3): string
    {
        $this->_feed = $feed;
        $this->_platform = Maho_FeedManager_Model_Platform::getAdapter($feed->getPlatform());
        $this->_mapper = new Maho_FeedManager_Model_Mapper($feed);

        // Check XML preview mode
        $xmlStructure = $feed->getXmlStructure();
        $xmlTemplate = $feed->getXmlItemTemplate();
        if (!empty($xmlStructure) && $feed->getFileFormat() === 'xml') {
            return $this->_generateXmlStructurePreview($feed, $limit);
        }

        if (!empty($xmlTemplate) && $feed->getFileFormat() === 'xml') {
            return $this->_generateXmlTemplatePreview($feed, $limit);
        }

        // Use standard writer for preview
        $output = '';
        $this->_writer = $this->_createWriter($feed->getFileFormat() ?: 'xml');

        // Create temp file for preview
        $tempFile = sys_get_temp_dir() . '/feedmanager_preview_' . uniqid() . '.' . $this->_writer->getFileExtension();

        try {
            $this->_writer->open($tempFile, $this->_platform);

            // Get limited product collection
            $collection = $this->_getProductCollectionForPreview($limit);

            foreach ($collection as $product) {
                // Load stock item
                $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
                $product->setStockItem($stockItem);

                // Map product data
                $mappedData = $this->_mapper->mapProduct($product);

                // Write to output
                $this->_writer->writeProduct($mappedData);
            }

            $this->_writer->close();

            // Read the generated file
            if (file_exists($tempFile)) {
                $output = file_get_contents($tempFile);
                unlink($tempFile);
            }

        } catch (Exception $e) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            throw $e;
        }

        return $output;
    }

    /**
     * Generate XML preview using visual builder structure
     */
    protected function _generateXmlStructurePreview(Maho_FeedManager_Model_Feed $feed, int $limit): string
    {
        $output = '';

        // Render header
        $header = $feed->getXmlHeader();
        if (!empty($header)) {
            $output .= $this->_renderHeaderFooter($header, $feed) . "\n";
        }

        // Get limited product collection
        $collection = $this->_getProductCollectionForPreview($limit);

        // Parse the XML structure
        $structureJson = $feed->getXmlStructure();
        $structure = Mage::helper('core')->jsonDecode($structureJson);
        $itemTag = trim($feed->getXmlItemTag() ?: 'item');

        foreach ($collection as $product) {
            // Load stock item
            $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
            $product->setStockItem($stockItem);

            // Generate XML using Mapper
            $output .= $this->_mapper->mapProductToXmlStructure($product, $structure, $itemTag, 1);
        }

        // Render footer
        $footer = $feed->getXmlFooter();
        if (!empty($footer)) {
            $output .= $this->_renderHeaderFooter($footer, $feed);
        }

        return $output;
    }

    /**
     * Generate XML preview using legacy template
     */
    protected function _generateXmlTemplatePreview(Maho_FeedManager_Model_Feed $feed, int $limit): string
    {
        $output = '';

        // Render header
        $header = $feed->getXmlHeader();
        if (!empty($header)) {
            $output .= $this->_renderHeaderFooter($header, $feed) . "\n";
        }

        // Get limited product collection
        $collection = $this->_getProductCollectionForPreview($limit);

        // Render each product
        $itemTemplate = $feed->getXmlItemTemplate();
        $itemTag = trim($feed->getXmlItemTag() ?: '');

        foreach ($collection as $product) {
            // Load stock item
            $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
            $product->setStockItem($stockItem);

            $itemXml = $this->_renderItemTemplate($itemTemplate, $product, $feed);

            // Wrap with item tag if configured
            if ($itemTag !== '') {
                $output .= "  <{$itemTag}>\n";
                $output .= '    ' . str_replace("\n", "\n    ", trim($itemXml)) . "\n";
                $output .= "  </{$itemTag}>\n";
            } else {
                $output .= $itemXml . "\n";
            }
        }

        // Render footer
        $footer = $feed->getXmlFooter();
        if (!empty($footer)) {
            $output .= $this->_renderHeaderFooter($footer, $feed);
        }

        return $output;
    }

    /**
     * Get limited product collection for preview
     */
    protected function _getProductCollectionForPreview(int $limit): Mage_Catalog_Model_Resource_Product_Collection
    {
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->setStoreId($this->_feed->getStoreId() ?: 0);
        $collection->addAttributeToSelect('*');

        // Only enabled products
        $collection->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

        // Apply product type filter if set
        $includeTypes = $this->_feed->getData('include_product_types');
        if (!empty($includeTypes)) {
            $types = array_map('trim', explode(',', $includeTypes));
            $collection->addAttributeToFilter('type_id', ['in' => $types]);
        }

        // Always exclude products with zero final price (invalid for any feed)
        $collection->addPriceData();
        $collection->getSelect()->where('price_index.final_price > 0');

        $collection->setPageSize($limit);
        $collection->setCurPage(1);

        return $collection;
    }
}
