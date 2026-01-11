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
 * Feed Generator
 *
 * Orchestrates feed generation with streaming/batching support for large catalogs
 */
class Maho_FeedManager_Model_Generator
{
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

        // Create log entry
        $this->_log = Mage::getModel('feedmanager/log');
        $this->_log->setFeedId($feed->getId())
            ->setStatus(Maho_FeedManager_Model_Log::STATUS_RUNNING)
            ->setStartedAt(Mage_Core_Model_Locale::now())
            ->save();

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

            // Validate the generated feed
            $validator = new Maho_FeedManager_Model_Validator();
            if (!$validator->validate($this->_tempPath, $feed->getFileFormat())) {
                $this->_errors = array_merge($this->_errors, $validator->getErrors());
                throw new RuntimeException('Feed validation failed: ' . implode(', ', $validator->getErrors()));
            }

            // Add any validation warnings
            foreach ($validator->getWarnings() as $warning) {
                $this->_errors[] = "[Validation Warning] {$warning}";
            }

            // Atomic move: rename temp file to final path (preserves existing file until success)
            if (!rename($this->_tempPath, $outputPath)) {
                throw new RuntimeException("Failed to move temp file to final path: {$outputPath}");
            }
            $this->_tempPath = null; // Clear temp path after successful move

            // Apply gzip compression if enabled
            $finalPath = $outputPath;
            if (Mage::helper('feedmanager')->isGzipCompressionEnabled()) {
                $finalPath = $this->_compressFile($outputPath);
            }

            // Update log with success
            $fileSize = file_exists($finalPath) ? filesize($finalPath) : 0;
            $this->_log->setStatus(Maho_FeedManager_Model_Log::STATUS_COMPLETED)
                ->setCompletedAt(Mage_Core_Model_Locale::now())
                ->setProductCount($this->_productCount)
                ->setFileSize($fileSize);

            // Update feed
            $feed->setLastGeneratedAt(Mage_Core_Model_Locale::now())
                ->setLastProductCount($this->_productCount)
                ->setLastFileSize($fileSize)
                ->save();

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
        }

        // Save errors to log
        if (!empty($this->_errors)) {
            foreach ($this->_errors as $error) {
                $this->_log->addError($error);
            }
        }

        $this->_log->save();

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

            foreach ($collection as $product) {
                try {
                    // Validate against Rule conditions (legacy Mage_Rule system)
                    if (!$this->_validateProductConditions($product)) {
                        $processed++;
                        continue;
                    }

                    // Validate against condition groups (new AND/OR system)
                    if (!$this->_validateConditionGroups($product)) {
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

                    // Stop if too many errors
                    if ($this->_errorCount > 100) {
                        throw new RuntimeException('Too many errors during generation. Aborting.');
                    }
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
        $this->_applyProductTypeFilter($collection);

        // Only visible products by default
        $collection->addAttributeToFilter('visibility', [
            'in' => [
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            ],
        ]);

        // Collect validated attributes from conditions for efficient loading
        $conditions = $this->_feed->getConditions();
        if (method_exists($conditions, 'collectValidatedAttributes')) {
            $conditions->collectValidatedAttributes($collection);
        }

        // Apply custom filters from feed configuration (legacy support)
        $this->_applyCustomFilters($collection);

        // Apply condition groups as SQL filters (much more efficient than PHP filtering)
        $this->_applyConditionGroupsToCollection($collection);

        return $collection;
    }

    /**
     * Apply condition groups to collection as SQL WHERE clauses
     *
     * This is much more efficient than loading all products and filtering in PHP.
     * Logic: All groups must pass (AND), within each group ANY condition can pass (OR)
     */
    protected function _applyConditionGroupsToCollection(Mage_Catalog_Model_Resource_Product_Collection $collection): void
    {
        $groups = $this->_feed->getConditionGroupsArray();

        if (empty($groups)) {
            return;
        }

        foreach ($groups as $group) {
            $conditions = $group['conditions'] ?? [];
            if (empty($conditions)) {
                continue;
            }

            // Build OR conditions for this group
            $orConditions = [];
            $stockConditions = [];
            $categoryConditions = [];
            $typeConditions = [];

            foreach ($conditions as $condition) {
                $attribute = $condition['attribute'] ?? '';
                $operator = $condition['operator'] ?? 'eq';
                $value = $condition['value'] ?? '';

                if (empty($attribute)) {
                    continue;
                }

                // Handle special attributes that need different treatment
                if ($attribute === 'qty' || $attribute === 'is_in_stock') {
                    $stockConditions[] = $this->_buildStockCondition($attribute, $operator, $value, $condition);
                } elseif ($attribute === 'category_ids') {
                    $categoryConditions[] = $this->_buildCategoryCondition($operator, $value, $condition);
                } elseif ($attribute === 'type_id') {
                    $typeConditions[] = $this->_buildTypeCondition($operator, $value, $condition);
                } else {
                    // Standard EAV attribute
                    $sqlCondition = $this->_buildSqlCondition($attribute, $operator, $value);
                    if ($sqlCondition !== null) {
                        $orConditions[] = $sqlCondition;
                    }
                }
            }

            // Apply standard attribute conditions (OR within group)
            if (!empty($orConditions)) {
                if (count($orConditions) === 1) {
                    $collection->addAttributeToFilter($orConditions[0]['attribute'], $orConditions[0]['condition']);
                } else {
                    // Multiple OR conditions
                    $collection->addAttributeToFilter($orConditions);
                }
            }

            // Apply stock conditions
            foreach ($stockConditions as $stockCond) {
                $this->_applyStockConditionToCollection($collection, $stockCond);
            }

            // Apply category conditions
            foreach ($categoryConditions as $catCond) {
                $this->_applyCategoryConditionToCollection($collection, $catCond);
            }

            // Apply type conditions
            foreach ($typeConditions as $typeCond) {
                $collection->addAttributeToFilter('type_id', $typeCond);
            }
        }
    }

    /**
     * Build SQL condition array for standard attributes
     */
    protected function _buildSqlCondition(string $attribute, string $operator, string $value): ?array
    {
        $condition = match ($operator) {
            'eq' => ['eq' => $value],
            'neq' => ['neq' => $value],
            'gt' => ['gt' => $value],
            'gteq' => ['gteq' => $value],
            'lt' => ['lt' => $value],
            'lteq' => ['lteq' => $value],
            'in' => ['in' => array_map('trim', explode(',', $value))],
            'nin' => ['nin' => array_map('trim', explode(',', $value))],
            'like' => ['like' => '%' . $value . '%'],
            'nlike' => ['nlike' => '%' . $value . '%'],
            'null' => ['null' => true],
            'notnull' => ['notnull' => true],
            default => ['eq' => $value],
        };

        return [
            'attribute' => $attribute,
            'condition' => $condition,
        ];
    }

    /**
     * Build stock condition info
     */
    protected function _buildStockCondition(string $attribute, string $operator, string $value, array $condition): array
    {
        return [
            'attribute' => $attribute,
            'operator' => $operator,
            'value' => $value,
            'stock_value' => $condition['stock_value'] ?? null,
        ];
    }

    /**
     * Apply stock condition to collection
     */
    protected function _applyStockConditionToCollection(Mage_Catalog_Model_Resource_Product_Collection $collection, array $stockCond): void
    {
        $attribute = $stockCond['attribute'];
        $operator = $stockCond['operator'];
        $value = $stockCond['value'];

        // Join stock table if not already joined
        $collection->joinField(
            'qty',
            'cataloginventory/stock_item',
            'qty',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left',
        );

        if ($attribute === 'qty') {
            $sqlCondition = $this->_buildSqlCondition('qty', $operator, $value);
            if ($sqlCondition) {
                $collection->addFieldToFilter('qty', $sqlCondition['condition']);
            }
        } elseif ($attribute === 'is_in_stock') {
            // Use stock_value from condition if available
            $stockValue = $stockCond['stock_value'] ?? $value;
            $collection->joinField(
                'is_in_stock',
                'cataloginventory/stock_item',
                'is_in_stock',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left',
            );
            $collection->addFieldToFilter('is_in_stock', ['eq' => (int) $stockValue]);
        }
    }

    /**
     * Build category condition info
     */
    protected function _buildCategoryCondition(string $operator, string $value, array $condition): array
    {
        return [
            'operator' => $operator,
            'value' => $value,
            'category_value' => $condition['category_value'] ?? null,
        ];
    }

    /**
     * Apply category condition to collection
     */
    protected function _applyCategoryConditionToCollection(Mage_Catalog_Model_Resource_Product_Collection $collection, array $catCond): void
    {
        $operator = $catCond['operator'];
        $categoryIds = $catCond['category_value'] ?? $catCond['value'];

        if (is_string($categoryIds)) {
            $categoryIds = array_map('trim', explode(',', $categoryIds));
        }
        $categoryIds = array_filter($categoryIds);

        if (empty($categoryIds)) {
            return;
        }

        // Join with category_product table to filter by category
        $categoryTable = $collection->getTable('catalog/category_product');

        if ($operator === 'in' || $operator === 'eq') {
            $collection->getSelect()->join(
                ['cat_filter' => $categoryTable],
                'cat_filter.product_id = e.entity_id',
                [],
            );
            $collection->getSelect()->where('cat_filter.category_id IN (?)', $categoryIds);
            $collection->getSelect()->distinct(true);
        } elseif ($operator === 'nin' || $operator === 'neq') {
            // For NOT IN, we need products that are NOT in any of the specified categories
            $subSelect = $collection->getConnection()->select()
                ->from($categoryTable, ['product_id'])
                ->where('category_id IN (?)', $categoryIds);
            $collection->getSelect()->where('e.entity_id NOT IN (?)', $subSelect);
        }
    }

    /**
     * Build type condition
     */
    protected function _buildTypeCondition(string $operator, string $value, array $condition): array
    {
        $typeValue = $condition['type_value'] ?? $value;

        return match ($operator) {
            'eq', 'in' => ['in' => is_array($typeValue) ? $typeValue : [$typeValue]],
            'neq', 'nin' => ['nin' => is_array($typeValue) ? $typeValue : [$typeValue]],
            default => ['eq' => $typeValue],
        };
    }

    /**
     * Apply product type filter
     */
    protected function _applyProductTypeFilter(Mage_Catalog_Model_Resource_Product_Collection $collection): void
    {
        $includeTypes = $this->_feed->getData('include_product_types');

        if (!empty($includeTypes)) {
            $types = array_map('trim', explode(',', $includeTypes));
            $collection->addAttributeToFilter('type_id', ['in' => $types]);
        }
    }

    /**
     * Validate product against Rule conditions (legacy Mage_Rule system)
     */
    protected function _validateProductConditions(Mage_Catalog_Model_Product $product): bool
    {
        $conditions = $this->_feed->getConditions();

        // If no conditions defined, all products pass
        if (!$conditions->getConditions()) {
            return true;
        }

        // Validate the product against conditions
        return $conditions->validate($product);
    }

    /**
     * Validate product against condition groups (new AND/OR system)
     *
     * Logic: All groups must pass (AND), within each group ANY condition can pass (OR)
     */
    protected function _validateConditionGroups(Mage_Catalog_Model_Product $product): bool
    {
        $groups = $this->_feed->getConditionGroupsArray();

        // If no groups defined, all products pass
        if (empty($groups)) {
            return true;
        }

        // All groups must pass (AND logic)
        foreach ($groups as $group) {
            $conditions = $group['conditions'] ?? [];
            if (empty($conditions)) {
                continue;
            }

            // Within a group, ANY condition can pass (OR logic)
            $groupPassed = false;
            foreach ($conditions as $condition) {
                if ($this->_evaluateCondition($product, $condition)) {
                    $groupPassed = true;
                    break; // One condition passed, group passes
                }
            }

            // If no condition in this group passed, product fails
            if (!$groupPassed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition against a product
     */
    protected function _evaluateCondition(Mage_Catalog_Model_Product $product, array $condition): bool
    {
        $attribute = $condition['attribute'] ?? '';
        $operator = $condition['operator'] ?? 'eq';
        $value = $condition['value'] ?? '';

        if (empty($attribute)) {
            return true; // Empty conditions always pass
        }

        // Get the product value for this attribute
        $productValue = $this->_getProductAttributeValue($product, $attribute);

        return match ($operator) {
            'eq' => $this->_compareEqual($productValue, $value),
            'neq' => !$this->_compareEqual($productValue, $value),
            'gt' => (float) $productValue > (float) $value,
            'gteq' => (float) $productValue >= (float) $value,
            'lt' => (float) $productValue < (float) $value,
            'lteq' => (float) $productValue <= (float) $value,
            'in' => $this->_compareIn($productValue, $value),
            'nin' => !$this->_compareIn($productValue, $value),
            'like' => $this->_compareLike($productValue, $value),
            'nlike' => !$this->_compareLike($productValue, $value),
            'null' => $productValue === null || $productValue === '',
            'notnull' => $productValue !== null && $productValue !== '',
            default => $this->_compareEqual($productValue, $value),
        };
    }

    /**
     * Get product attribute value, handling special attributes
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
     * Compare equality (handles select attribute option IDs)
     */
    protected function _compareEqual(mixed $productValue, string $value): bool
    {
        if (is_array($productValue)) {
            return in_array($value, $productValue);
        }
        return (string) $productValue === $value;
    }

    /**
     * Compare if value is in a comma-separated list
     */
    protected function _compareIn(mixed $productValue, string $value): bool
    {
        $values = array_map('trim', explode(',', $value));

        if (is_array($productValue)) {
            return !empty(array_intersect($productValue, $values));
        }

        // For category_ids which is stored as comma-separated
        if (str_contains((string) $productValue, ',')) {
            $productValues = array_map('trim', explode(',', (string) $productValue));
            return !empty(array_intersect($productValues, $values));
        }

        return in_array((string) $productValue, $values);
    }

    /**
     * Compare using LIKE (contains)
     */
    protected function _compareLike(mixed $productValue, string $value): bool
    {
        return str_contains(strtolower((string) $productValue), strtolower($value));
    }

    /**
     * Apply custom filters from feed configuration (for collection-level filtering)
     * Note: Complex OR conditions are handled in _validateConditionGroups during processing
     */
    protected function _applyCustomFilters(Mage_Catalog_Model_Resource_Product_Collection $collection): void
    {
        // The new AND/OR condition groups are validated per-product in _validateConditionGroups
        // We could add simple AND filters here for efficiency, but for now we rely on post-filtering
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
            default => throw new InvalidArgumentException("Unsupported format: {$format}"),
        };

        // Configure writer from feed if available
        if (method_exists($writer, 'configureFromFeed')) {
            $writer->configureFromFeed($this->_feed);
        }

        return $writer;
    }

    /**
     * Configure mapper from CSV/JSON builder definitions
     */
    protected function _configureMapperFromBuilder(): void
    {
        $format = $this->_feed->getFileFormat();

        if ($format === 'csv') {
            $csvColumns = $this->_feed->getCsvColumns();
            if ($csvColumns) {
                $columns = Mage::helper('core')->jsonDecode($csvColumns);
                if (is_array($columns) && !empty($columns)) {
                    $this->_mapper->setMappingsFromCsvColumns($columns);
                }
            }
        } elseif ($format === 'json') {
            $jsonStructure = $this->_feed->getJsonStructure();
            if ($jsonStructure) {
                $structure = Mage::helper('core')->jsonDecode($jsonStructure);
                if (is_array($structure) && !empty($structure)) {
                    $this->_mapper->setMappingsFromJsonStructure($structure);
                }
            }
        }
    }

    /**
     * Get output file path
     */
    protected function _getOutputPath(): string
    {
        $outputDir = Mage::helper('feedmanager')->getOutputDirectory();
        $filename = $this->_feed->getFilename();

        // Get extension from writer if available, otherwise from feed format
        if ($this->_writer) {
            $extension = $this->_writer->getFileExtension();
        } else {
            $extension = match ($this->_feed->getFileFormat()) {
                'xml' => 'xml',
                'csv' => 'csv',
                'json' => 'json',
                default => 'xml',
            };
        }

        return $outputDir . DS . $filename . '.' . $extension;
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

                foreach ($collection as $product) {
                    try {
                        // Validate against Rule conditions (legacy Mage_Rule system)
                        if (!$this->_validateProductConditions($product)) {
                            $processed++;
                            continue;
                        }

                        // Validate against condition groups (new AND/OR system)
                        if (!$this->_validateConditionGroups($product)) {
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

                        // Stop if too many errors
                        if ($this->_errorCount > 100) {
                            throw new RuntimeException('Too many errors during generation. Aborting.');
                        }
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

                foreach ($collection as $product) {
                    try {
                        // Validate against Rule conditions (legacy Mage_Rule system)
                        if (!$this->_validateProductConditions($product)) {
                            $processed++;
                            continue;
                        }

                        // Validate against condition groups (new AND/OR system)
                        if (!$this->_validateConditionGroups($product)) {
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

                        // Stop if too many errors
                        if ($this->_errorCount > 100) {
                            throw new RuntimeException('Too many errors during generation. Aborting.');
                        }
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
     * Compress file with gzip
     */
    protected function _compressFile(string $sourcePath): string
    {
        $gzPath = $sourcePath . '.gz';

        $source = fopen($sourcePath, 'rb');
        $dest = gzopen($gzPath, 'wb9');

        while (!feof($source)) {
            gzwrite($dest, fread($source, 1048576)); // 1MB chunks
        }

        fclose($source);
        gzclose($dest);

        // Remove uncompressed file
        unlink($sourcePath);

        return $gzPath;
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
        } elseif (!empty($xmlTemplate) && $feed->getFileFormat() === 'xml') {
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

            // Parse configuration attributes
            $config = $this->_parseFieldConfig($configStr);

            if (empty($config)) {
                continue;
            }

            // Get the value based on configuration
            $value = $this->_getValueFromConfig($product, $config, $feed);

            // Apply formatting/transformations
            $value = $this->_applyFormat($value, $config, $feed, $productData);

            // Apply length limit (backward compatibility - transformers handle this via truncate)
            if (!empty($config['length']) && empty($config['transformers'])) {
                $value = mb_substr($value, 0, (int) $config['length']);
            }

            // Handle optional fields - if empty and optional, leave empty
            if (empty($value) && ($config['optional'] ?? 'yes') === 'no') {
                // Required field is empty - could log warning
            }

            $template = str_replace($fullMatch, $value, $template);
        }

        // Also support simple {{placeholder}} syntax for backwards compatibility
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

        // Match attribute="value" patterns
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
        $useParent = ($config['parent'] ?? 'no') === 'yes';

        // If using parent and product has parent, try parent first if value is empty
        $parentProduct = null;
        if ($useParent && $product->getTypeId() !== 'simple') {
            // For configurable children, get parent
            $parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
            if (!empty($parentIds)) {
                $parentProduct = Mage::getModel('catalog/product')->load($parentIds[0]);
            }
        }

        $result = '';

        switch ($type) {
            case 'attribute':
                $result = $this->_getProductValueForTemplate($product, $value, $feed);
                // Try parent if empty and parent enabled
                if (empty($result) && $parentProduct) {
                    $result = $this->_getProductValueForTemplate($parentProduct, $value, $feed);
                }
                break;

            case 'text':
                $result = $value;
                break;

            case 'combined':
                // Process {{placeholder}} syntax in value
                $result = $this->_processCombinedTemplate($value, $product, $feed, $parentProduct);
                break;

            case 'category':
                $result = $this->_getProductCategoryPath($product);
                break;

            case 'images':
                $result = $this->_getAdditionalImage($product, $value, $feed);
                break;

            case 'custom_field':
                // Custom fields would need to be implemented based on your custom field system
                $result = $this->_getCustomFieldValue($product, $value, $feed);
                break;

            default:
                $result = $this->_getProductValueForTemplate($product, $value, $feed);
        }

        return $result;
    }

    /**
     * Process combined template with {{placeholder}} syntax
     */
    protected function _processCombinedTemplate(
        string $template,
        Mage_Catalog_Model_Product $product,
        Maho_FeedManager_Model_Feed $feed,
        ?Mage_Catalog_Model_Product $parentProduct = null,
    ): string {
        // Find all {{placeholder}} in the template
        preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);

        $result = $template;
        foreach ($matches[1] as $placeholder) {
            $value = $this->_getProductValueForTemplate($product, $placeholder, $feed);

            // Try parent if empty and parent is available
            if (empty($value) && $parentProduct) {
                $value = $this->_getProductValueForTemplate($parentProduct, $placeholder, $feed);
            }

            $result = str_replace('{{' . $placeholder . '}}', $value, $result);
        }

        return $result;
    }

    /**
     * Apply formatting/transformations to value
     *
     * @param array<string, mixed> $productData Full product data for transformer context
     */
    protected function _applyFormat(
        string $value,
        array $config,
        Maho_FeedManager_Model_Feed $feed,
        array $productData = [],
    ): string {
        // New transformer chain format takes precedence
        if (!empty($config['transformers'])) {
            $chain = Maho_FeedManager_Model_Transformer::parseChainString($config['transformers']);
            if (!empty($chain)) {
                // Add feed config to product data for transformers that need it
                $productData['_feed'] = [
                    'price_decimals' => $feed->getPriceDecimals() ?? 2,
                    'price_decimal_point' => $feed->getPriceDecimalPoint() ?? '.',
                    'price_thousands_sep' => $feed->getPriceThousandsSep() ?? '',
                    'price_currency' => $feed->getPriceCurrency() ?: 'AUD',
                ];

                return (string) Maho_FeedManager_Model_Transformer::pipeline($value, $chain, $productData);
            }
        }

        // Backward compatibility with old 'format' attribute
        $format = $config['format'] ?? 'as_is';

        return match ($format) {
            'html_escape' => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
            'strip_tags' => strip_tags($value),
            'price' => $this->_formatPrice((float) $value, $feed),
            'date' => $this->_formatDate($value),
            'lowercase' => strtolower($value),
            'integer' => (string) (int) $value,
            'base' => $this->_ensureBaseUrl($value),
            default => $value,
        };
    }

    /**
     * Get additional image by index
     */
    protected function _getAdditionalImage(Mage_Catalog_Model_Product $product, string $imageKey, Maho_FeedManager_Model_Feed $feed): string
    {
        // Parse image_1, image_2, etc.
        if (preg_match('/image_(\d+)/', $imageKey, $matches)) {
            $index = (int) $matches[1] - 1; // Convert to 0-based index

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
     * Get custom field value (placeholder for custom field implementation)
     */
    protected function _getCustomFieldValue(Mage_Catalog_Model_Product $product, string $fieldCode, Maho_FeedManager_Model_Feed $feed): string
    {
        // This would integrate with a custom fields system
        // For now, just return empty or try to get from product data
        return (string) $product->getData($fieldCode);
    }

    /**
     * Format date value
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
     * Ensure URL has base URL prefix
     */
    protected function _ensureBaseUrl(string $value): string
    {
        if (empty($value) || str_starts_with($value, 'http')) {
            return $value;
        }

        // If it's a relative path, prepend media URL
        if (str_starts_with($value, '/')) {
            return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . ltrim($value, '/');
        }

        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $value;
    }

    /**
     * Get product value for template placeholder
     */
    protected function _getProductValueForTemplate(Mage_Catalog_Model_Product $product, string $placeholder, Maho_FeedManager_Model_Feed $feed): string
    {
        $store = Mage::app()->getStore($feed->getStoreId());

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
            'stock_status' => ($stockItem = $product->getStockItem()) && $stockItem->getIsInStock() ? 'in stock' : 'out of stock',
            'qty' => (string) (int) (($stockItem = $product->getStockItem()) ? $stockItem->getQty() : 0),
            'category' => $this->_getProductCategoryPath($product),
            'brand' => (string) $product->getAttributeText('brand'),
            'manufacturer' => (string) $product->getAttributeText('manufacturer'),
            'weight' => (string) $product->getWeight(),
            'type_id' => (string) $product->getTypeId(),
            'google_product_category' => (string) $product->getData('google_product_category'),
            'gtin' => (string) ($product->getData('gtin') ?: $product->getData('upc') ?: $product->getData('ean')),
            'mpn' => (string) ($product->getData('mpn') ?: $product->getSku()),
            default => $this->_getGenericProductAttribute($product, $placeholder),
        };
    }

    /**
     * Get generic product attribute value
     */
    protected function _getGenericProductAttribute(Mage_Catalog_Model_Product $product, string $attributeCode): string
    {
        $value = $product->getData($attributeCode);

        // Try to get text value for select/multiselect attributes
        if ($value !== null) {
            $textValue = $product->getAttributeText($attributeCode);
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
    protected function _getProductImageUrl(Mage_Catalog_Model_Product $product, Maho_FeedManager_Model_Feed $feed, string $imageType = 'image'): string
    {
        $image = $product->getData($imageType);

        if (!$image || $image === 'no_selection') {
            return $feed->getNoImageUrl() ?: '';
        }

        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $image;
    }

    /**
     * Format price according to feed settings
     */
    protected function _formatPrice(float|string|null $price, Maho_FeedManager_Model_Feed $feed): string
    {
        $price = (float) ($price ?? 0);
        $decimals = (int) ($feed->getPriceDecimals() ?? 2);
        $decimalPoint = $feed->getPriceDecimalPoint() ?: '.';
        $thousandsSep = $feed->getPriceThousandsSep() ?? '';
        $currency = $feed->getPriceCurrency() ?: 'AUD';

        $formattedPrice = number_format($price, $decimals, $decimalPoint, $thousandsSep);

        return $formattedPrice . ' ' . $currency;
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

        // Get the first (or deepest) category
        $categoryId = end($categoryIds);
        $category = Mage::getModel('catalog/category')->load($categoryId);

        if (!$category->getId()) {
            return '';
        }

        // Build path from root
        $path = [];
        $pathIds = explode('/', $category->getPath());

        foreach ($pathIds as $pathId) {
            if ($pathId <= 2) {
                continue; // Skip root categories
            }
            $pathCategory = Mage::getModel('catalog/category')->load($pathId);
            if ($pathCategory->getId()) {
                $path[] = $pathCategory->getName();
            }
        }

        return implode(' > ', $path);
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

        // Only visible products
        $collection->addAttributeToFilter('visibility', [
            'in' => [
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            ],
        ]);

        // Apply product type filter if set
        $includeTypes = $this->_feed->getData('include_product_types');
        if (!empty($includeTypes)) {
            $types = array_map('trim', explode(',', $includeTypes));
            $collection->addAttributeToFilter('type_id', ['in' => $types]);
        }

        $collection->setPageSize($limit);
        $collection->setCurPage(1);

        return $collection;
    }
}
