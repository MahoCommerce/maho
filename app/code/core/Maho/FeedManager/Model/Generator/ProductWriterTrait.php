<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

/**
 * Shared product processing functionality for feed generators
 *
 * This trait provides a unified output engine used by both synchronous (Generator)
 * and batch (Generator_Batch) feed generation classes.
 *
 * Requirements for using classes:
 * - Must have $_feed property of type Maho_FeedManager_Model_Feed
 * - Must have $_mapper property of type Maho_FeedManager_Model_Mapper
 * - Must have $_platform property of type ?Maho_FeedManager_Model_Platform_AdapterInterface
 * - Must have $_log property of type ?Maho_FeedManager_Model_Log
 * - Must have $_batchSize property of type int
 * - Must have $_errors property of type array
 */
trait Maho_FeedManager_Model_Generator_ProductWriterTrait
{
    /** Row key that holds the raw product value of a custom_field template field */
    protected const CUSTOM_FIELD_KEY = '_custom_field';

    // ──────────────────────────────────────────────────────────────────────
    // Output engine properties
    // ──────────────────────────────────────────────────────────────────────

    protected ?Maho_FeedManager_Model_Writer_WriterInterface $_writer = null;

    /** @var resource|null File handle for XML direct modes */
    protected $_outputHandle = null;

    protected string $_outputMode = 'writer';
    protected int $_productCount = 0;
    protected int $_processedCount = 0;
    protected int $_errorCount = 0;

    /** @var array<int|string, mixed>|null Parsed XML structure for xml_structure mode */
    protected ?array $_xmlStructureParsed = null;

    protected string $_xmlItemTag = 'item';
    protected string $_xmlItemTemplate = '';

    /** @var array<int|string, mixed>|null Parsed JSON structure for json format */
    protected ?array $_jsonStructureParsed = null;

    // ──────────────────────────────────────────────────────────────────────
    // Output engine: setup, open, resume, pause, close
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Determine output mode and prepare format-specific state
     *
     * Resolves whether to use a Writer or direct XML output, creates the
     * writer instance, and parses any structure/template definitions.
     * Does NOT open a file — call _openOutput() or _resumeOutput() after.
     */
    protected function _prepareOutputMode(): void
    {
        $format = $this->_feed->getFileFormat();

        if ($format === 'xml' && $this->_feed->getXmlStructure()) {
            $this->_outputMode = 'xml_structure';
            $this->_xmlStructureParsed = Mage::helper('core')->jsonDecode($this->_feed->getXmlStructure());
            $this->_xmlItemTag = trim($this->_feed->getXmlItemTag() ?: 'item');
        } elseif ($format === 'xml' && $this->_feed->getXmlItemTemplate()) {
            $this->_outputMode = 'xml_template';
            $this->_xmlItemTemplate = $this->_feed->getXmlItemTemplate();
            $this->_xmlItemTag = trim($this->_feed->getXmlItemTag() ?: '');
        } else {
            $this->_outputMode = 'writer';
            $this->_writer = $this->_createWriter($format);
        }

        // Cache parsed JSON structure for json format
        if ($format === 'json' && $this->_feed->getJsonStructure()) {
            $decoded = Mage::helper('core')->jsonDecode($this->_feed->getJsonStructure());
            if (is_array($decoded) && !empty($decoded)) {
                $this->_jsonStructureParsed = $decoded;
            }
        }
    }

    /**
     * Open output for a new file (writes headers/preamble)
     */
    protected function _openOutput(string $filePath): void
    {
        $this->_prepareOutputMode();

        switch ($this->_outputMode) {
            case 'xml_structure':
            case 'xml_template':
                $this->_outputHandle = fopen($filePath, 'w');
                if ($this->_outputHandle === false) {
                    throw new RuntimeException("Cannot open file for writing: {$filePath}");
                }
                $header = $this->_feed->getXmlHeader();
                if (!empty($header)) {
                    fwrite($this->_outputHandle, $this->_renderHeaderFooter($header, $this->_feed) . "\n");
                }
                break;

            case 'writer':
                $this->_writer->open($filePath, $this->_platform);
                break;
        }
    }

    /**
     * Resume output on an existing file (append mode, no header)
     *
     * Recreates writer/parsed state from feed config, then opens in append mode.
     */
    protected function _resumeOutput(string $filePath): void
    {
        $this->_prepareOutputMode();

        switch ($this->_outputMode) {
            case 'xml_structure':
            case 'xml_template':
                $this->_outputHandle = fopen($filePath, 'a');
                if ($this->_outputHandle === false) {
                    throw new RuntimeException("Cannot open file for appending: {$filePath}");
                }
                break;

            case 'writer':
                $this->_writer->resume($filePath, $this->_platform);
                break;
        }
    }

    /**
     * Pause output by closing the file handle without writing footer
     *
     * Used by batch generation to release handles between HTTP requests.
     */
    protected function _pauseOutput(): void
    {
        switch ($this->_outputMode) {
            case 'xml_structure':
            case 'xml_template':
                if (is_resource($this->_outputHandle)) {
                    fclose($this->_outputHandle);
                    $this->_outputHandle = null;
                }
                break;

            case 'writer':
                $this->_writer->pause();
                break;
        }
    }

    /**
     * Close output, writing footer/closing tags
     */
    protected function _closeOutput(): void
    {
        switch ($this->_outputMode) {
            case 'xml_structure':
            case 'xml_template':
                $footer = $this->_feed->getXmlFooter();
                if (!empty($footer) && is_resource($this->_outputHandle)) {
                    fwrite($this->_outputHandle, $this->_renderHeaderFooter($footer, $this->_feed) . "\n");
                }
                if (is_resource($this->_outputHandle)) {
                    fclose($this->_outputHandle);
                    $this->_outputHandle = null;
                }
                break;

            case 'writer':
                $this->_writer->close();
                break;
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // Core processing: batch loop and per-product writing
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Process one page of products
     *
     * Iterates through a single page of the product collection, validates
     * each product against conditions, and writes it to the output.
     * Updates progress counters and handles per-product errors.
     *
     * @return int Number of products successfully written in this batch
     */
    protected function _processOneBatch(int $page): int
    {
        $collection = $this->_getProductCollection();
        $collection->setPageSize($this->_batchSize)->setCurPage($page);

        $productIds = $collection->getAllIds();
        if (!empty($productIds)) {
            $this->_mapper->preloadParentMappings($productIds);
        }

        $processedInBatch = 0;

        foreach ($collection as $product) {
            try {
                if (!$this->_validateProductConditions($product)) {
                    $this->_processedCount++;
                    continue;
                }

                $this->_writeProduct($product);
                $this->_processedCount++;
                $processedInBatch++;

                if ($this->_processedCount % 100 === 0) {
                    $this->_log->setProductCount($this->_productCount)->save();
                }
            } catch (\Throwable $e) {
                $this->_errorCount++;
                $this->_errors[] = "Product {$product->getSku()}: {$e->getMessage()}";
                $this->_processedCount++;

                $this->_checkErrorThreshold($this->_errorCount, $this->_processedCount);
            }
        }

        $collection->clear();
        gc_collect_cycles();

        return $processedInBatch;
    }

    /**
     * Write a single product to the output using the current output mode
     */
    protected function _writeProduct(Mage_Catalog_Model_Product $product): void
    {
        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
        $product->setStockItem($stockItem);

        switch ($this->_outputMode) {
            case 'xml_structure':
                $xml = $this->_mapper->mapProductToXmlStructure(
                    $product,
                    $this->_xmlStructureParsed,
                    $this->_xmlItemTag,
                    1,
                );
                fwrite($this->_outputHandle, $xml);
                break;

            case 'xml_template':
                $itemXml = $this->_renderItemTemplate($this->_xmlItemTemplate, $product, $this->_feed);
                if ($this->_xmlItemTag !== '') {
                    fwrite($this->_outputHandle, "  <{$this->_xmlItemTag}>\n");
                    fwrite($this->_outputHandle, '    ' . str_replace("\n", "\n    ", trim($itemXml)) . "\n");
                    fwrite($this->_outputHandle, "  </{$this->_xmlItemTag}>\n");
                } else {
                    fwrite($this->_outputHandle, '  ' . trim($itemXml) . "\n");
                }
                break;

            case 'writer':
                if ($this->_jsonStructureParsed !== null) {
                    $mappedData = $this->_mapper->mapProductToJsonStructure($product, $this->_jsonStructureParsed);
                } else {
                    $mappedData = $this->_mapper->mapProduct($product);
                }

                if ($this->_platform) {
                    $validationErrors = $this->_platform->validateProductData($mappedData);
                    if (!empty($validationErrors)) {
                        foreach ($validationErrors as $error) {
                            $this->_errors[] = "Product {$product->getSku()}: {$error}";
                        }
                    }
                }

                $this->_writer->writeProduct($mappedData);
                break;
        }

        $this->_productCount++;
    }

    /**
     * Create appropriate writer for format and configure it from feed
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

        if (method_exists($writer, 'configureFromFeed')) {
            $writer->configureFromFeed($this->_feed);
        }

        return $writer;
    }

    // ──────────────────────────────────────────────────────────────────────
    // Product collection and filtering
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Get product collection with filters applied
     */
    protected function _getProductCollection(): Mage_Catalog_Model_Resource_Product_Collection
    {
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->setStoreId($this->_feed->getStoreId());

        // Filter to products assigned to this store's website
        $websiteId = Mage::app()->getStore($this->_feed->getStoreId())->getWebsiteId();
        $collection->addWebsiteFilter($websiteId);

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

        // Add price data
        $collection->addPriceData();

        if (!$this->_feed->getExcludeOutOfStock()) {
            // Convert price index INNER JOIN to LEFT JOIN so out-of-stock products
            // not in the price index are still included in the collection
            $select = $collection->getSelect();
            $fromPart = $select->getPart(\Maho\Db\Select::FROM);
            if (isset($fromPart['price_index'])) {
                $fromPart['price_index']['joinType'] = \Maho\Db\Select::LEFT_JOIN;
                $select->setPart(\Maho\Db\Select::FROM, $fromPart);
            }
            // Exclude zero-price products but allow NULL (out-of-stock products missing from price index)
            $collection->getSelect()->where('price_index.final_price > 0 OR price_index.final_price IS NULL');
        } else {
            // Exclude products with zero final price (invalid for any feed)
            $collection->getSelect()->where('price_index.final_price > 0');
        }

        // Collect validated attributes from Rule conditions
        $conditions = $this->_feed->getConditions();
        if (method_exists($conditions, 'collectValidatedAttributes')) {
            $conditions->collectValidatedAttributes($collection);
        }

        return $collection;
    }

    /**
     * Apply product type filter to collection
     */
    protected function _applyProductTypeFilter(Mage_Catalog_Model_Resource_Product_Collection $collection): void
    {
        $includeTypes = $this->_feed->getData('include_product_types');

        if (!empty($includeTypes)) {
            $types = array_map(trim(...), explode(',', $includeTypes));
            $collection->addAttributeToFilter('type_id', ['in' => $types]);
        }
    }

    /**
     * Validate product against Rule conditions
     */
    protected function _validateProductConditions(Mage_Catalog_Model_Product $product): bool
    {
        $conditions = $this->_feed->getConditions();

        if (!$conditions->getConditions()) {
            return true;
        }

        return $conditions->validate($product);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Template rendering
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Render header or footer template
     */
    protected function _renderHeaderFooter(string $template, Maho_FeedManager_Model_Feed $feed): string
    {
        $store = Mage::app()->getStore($feed->getStoreId());

        $replacements = [
            '{{store_name}}' => $store->getName(),
            '{{store_url}}' => $store->getBaseUrl(),
            '{{generation_date}}' => Mage::app()->getLocale()->nowUtc(),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Render item template for a product
     */
    protected function _renderItemTemplate(string $template, Mage_Catalog_Model_Product $product, Maho_FeedManager_Model_Feed $feed): string
    {
        // The gallery costs one query for each product. A template without "image" needs none.
        $this->_mapper->setExtractGallery(str_contains($template, 'image'));

        $productData = $this->_templateCompatRow($this->_mapper->extractProductData($product));
        $productData['_product'] = $product;

        // Find all field configurations in the template: {type="..." value="..." ...}
        preg_match_all('/\{(type="(?:[^"\\\\]|\\\\.)*"(?:\s+\w+="(?:[^"\\\\]|\\\\.)*")*)\}/', $template, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $fullMatch = $match[0];
            $configStr = $match[1];
            $config = $this->_parseFieldConfig($configStr);

            if (empty($config)) {
                continue;
            }

            $value = $this->_resolveTemplateField($config, $productData, $product);
            $value = $this->_applyFormat($value, $config, $feed);

            // Apply length limit (backward compatibility - transformers handle this via truncate)
            if (!empty($config['length']) && empty($config['transformers'])) {
                $value = mb_substr($value, 0, (int) $config['length']);
            }

            $template = str_replace($fullMatch, $value, $template);
        }

        // Support simple {{placeholder}} syntax for backwards compatibility
        preg_match_all('/\{\{([^}]+)\}\}/', $template, $simpleMatches);
        foreach ($simpleMatches[1] as $placeholder) {
            $value = $this->_resolveTemplateField(
                ['type' => 'attribute', 'value' => $placeholder],
                $productData,
                $product,
            );
            $template = str_replace('{{' . $placeholder . '}}', $value, $template);
        }

        return $template;
    }

    /**
     * Adapt the row keys that a template uses.
     *
     * The CSV, JSON, and XML engines share this row. The keys below have a different meaning
     * in a template, so this method adapts them here and not in the mapper.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function _templateCompatRow(array $row): array
    {
        // The parent_* keys need the same adaptation. Without it, parent="yes" returns a raw
        // value (description) or no value at all (category, image_url, gtin, mpn).
        foreach (['', 'parent_'] as $prefix) {
            if ($prefix !== '' && !array_key_exists($prefix . 'sku', $row)) {
                continue;
            }
            $row[$prefix . 'description'] = strip_tags((string) ($row[$prefix . 'description'] ?? ''));
            $row[$prefix . 'short_description'] = strip_tags((string) ($row[$prefix . 'short_description'] ?? ''));
            $row[$prefix . 'category'] = $row[$prefix . 'category_path'] ?? '';
            $row[$prefix . 'image_url'] = $row[$prefix . 'image'] ?? '';
            $row[$prefix . 'gtin'] = ($row[$prefix . 'gtin'] ?? '') ?: (($row[$prefix . 'upc'] ?? '') ?: ($row[$prefix . 'ean'] ?? ''));
            $row[$prefix . 'mpn'] = ($row[$prefix . 'mpn'] ?? '') ?: ($row[$prefix . 'sku'] ?? '');

            // In a template, image_1 is the first gallery image. In the row, image_1 is the
            // first additional image. The two counts differ by one position.
            $gallery = array_values((array) ($row[$prefix . 'gallery_images'] ?? []));
            for ($i = 1; $i <= 10; $i++) {
                $row[$prefix . 'image_' . $i] = $gallery[$i - 1] ?? null;
            }
        }

        // Only the child has stock data, so this method sets no parent_stock_status key.
        $row['stock_status'] = !empty($row['is_in_stock']) ? 'in stock' : 'out of stock';

        return $row;
    }

    /**
     * Resolve one template field through the shared mapper resolver
     *
     * @param array<string, mixed> $config Parsed template field configuration
     * @param array<string, mixed> $productData Compatibility row from _templateCompatRow()
     */
    protected function _resolveTemplateField(array $config, array $productData, Mage_Catalog_Model_Product $product): string
    {
        $mapperConfig = $this->_toMapperConfig($config);

        if (($config['type'] ?? '') === 'custom_field') {
            $productData = $this->_addCustomField($productData, $config, $product);
            $mapperConfig['source_value'] = self::CUSTOM_FIELD_KEY;
        }

        $value = $this->_mapper->resolveFieldValue(
            $mapperConfig,
            $productData,
            $product,
        );

        if (is_array($value)) {
            $value = implode(',', array_filter($value, fn($v) => $v !== null && $v !== ''));
        }

        return (string) ($value ?? '');
    }

    /**
     * Put the raw product value of a custom_field under a reserved row key.
     *
     * A custom_field is the value that the product stores. The shared row is different: it
     * holds the option label of a select attribute, and it has no computed column such as
     * min_price.
     *
     * @param array<string, mixed> $productData
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    protected function _addCustomField(array $productData, array $config, Mage_Catalog_Model_Product $product): array
    {
        $code = (string) ($config['value'] ?? '');
        $productData[self::CUSTOM_FIELD_KEY] = $product->getData($code);

        if (($config['parent'] ?? 'no') === 'yes') {
            $parent = $this->_mapper->getParentProduct($product);
            if ($parent !== null) {
                $productData['parent_' . self::CUSTOM_FIELD_KEY] = $parent->getData($code);
            }
        }

        return $productData;
    }

    /**
     * Translate a template field configuration into a mapper field configuration
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    protected function _toMapperConfig(array $config): array
    {
        $type = (string) ($config['type'] ?? 'attribute');
        $value = (string) ($config['value'] ?? '');

        $mapped = match ($type) {
            'text', 'static' => [
                'source_type' => Maho_FeedManager_Model_Mapper::SOURCE_TYPE_STATIC,
                'source_value' => $value,
            ],
            'combined' => [
                'source_type' => Maho_FeedManager_Model_Mapper::SOURCE_TYPE_COMBINED,
                'source_value' => $value,
            ],
            'category' => [
                'source_type' => Maho_FeedManager_Model_Mapper::SOURCE_TYPE_ATTRIBUTE,
                'source_value' => 'category_path',
            ],
            default => [
                'source_type' => Maho_FeedManager_Model_Mapper::SOURCE_TYPE_ATTRIBUTE,
                'source_value' => $value,
            ],
        };

        if (($config['parent'] ?? 'no') === 'yes') {
            $mapped['use_parent'] = 'if_empty';
        }

        if (!empty($config['transformers'])) {
            $mapped['transformers'] = $config['transformers'];
        }

        return $mapped;
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
     * Apply the legacy 'format' attribute to a value
     *
     * The shared resolver applies the transformer chains, so this method skips them.
     */
    protected function _applyFormat(string $value, array $config, Maho_FeedManager_Model_Feed $feed): string
    {
        if (!empty($config['transformers'])) {
            return $value;
        }

        $format = $config['format'] ?? 'as_is';

        return match ($format) {
            'html_escape' => htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
            'strip_tags' => strip_tags($value),
            // The resolver already formatted a price field. A formatted price is not numeric,
            // and a cast would truncate it at the first separator.
            'price' => is_numeric($value) ? $this->_formatPrice((float) $value, $feed) : $value,
            'date' => $this->_formatDate($value),
            'lowercase' => strtolower($value),
            'uppercase' => strtoupper($value),
            'integer' => (string) (int) $value,
            'base' => $this->_ensureBaseUrl($value),
            default => $value,
        };
    }

    // ──────────────────────────────────────────────────────────────────────
    // Formatting helpers
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Format price according to feed settings
     */
    protected function _formatPrice(float|string|null $price, Maho_FeedManager_Model_Feed $feed): string
    {
        $price = (float) ($price ?? 0);
        $decimals = (int) ($feed->getPriceDecimals() ?? 2);
        $decimalPoint = $feed->getPriceDecimalPoint() ?: '.';
        $thousandsSep = $feed->getPriceThousandsSep() ?? '';
        $currency = $feed->getPriceCurrency() ?: Mage::app()->getStore($feed->getStoreId())->getBaseCurrencyCode();

        $formattedPrice = number_format($price, $decimals, $decimalPoint, $thousandsSep);

        return $formattedPrice . ' ' . $currency;
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
        } catch (Exception) {
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

        if (str_starts_with($value, '/')) {
            return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . ltrim($value, '/');
        }

        return Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product' . $value;
    }

    // ──────────────────────────────────────────────────────────────────────
    // File operations
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Compress file with gzip
     */
    protected function _compressFile(string $sourcePath): string
    {
        $gzPath = $sourcePath . '.gz';

        $source = fopen($sourcePath, 'rb');
        if ($source === false) {
            throw new Mage_Core_Exception("Failed to open source file for compression: {$sourcePath}");
        }

        $dest = gzopen($gzPath, 'wb9');
        if ($dest === false) {
            fclose($source);
            throw new Mage_Core_Exception("Failed to create gzip file: {$gzPath}");
        }

        while (!feof($source)) {
            gzwrite($dest, fread($source, 1048576)); // 1MB chunks
        }

        fclose($source);
        gzclose($dest);

        unlink($sourcePath);

        return $gzPath;
    }

    /**
     * Get output file path
     */
    protected function _getOutputPath(): string
    {
        $outputDir = Mage::helper('feedmanager')->getOutputDirectory();
        $filename = $this->_feed->getFilename();

        $extension = match ($this->_feed->getFileFormat()) {
            'xml' => 'xml',
            'csv' => 'csv',
            'json' => 'json',
            'jsonl' => 'jsonl',
            default => 'xml',
        };

        return $outputDir . DS . $filename . '.' . $extension;
    }

    /**
     * Check if error threshold has been exceeded
     *
     * @param int $errorCount Number of errors so far
     * @param int $processed Number of products processed so far
     * @throws RuntimeException if threshold exceeded
     */
    protected function _checkErrorThreshold(int $errorCount, int $processed): void
    {
        $thresholdPercent = (int) Mage::getStoreConfig('feedmanager/general/error_threshold_percent');

        // Skip check if threshold is disabled (0) or not enough products processed yet
        if ($thresholdPercent === 0 || $processed < 10) {
            return;
        }

        $errorPercent = ($errorCount / $processed) * 100;

        if ($errorPercent > $thresholdPercent) {
            throw new RuntimeException(sprintf(
                'Error threshold exceeded: %.1f%% of products failed (threshold: %d%%). Aborting generation.',
                $errorPercent,
                $thresholdPercent,
            ));
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // Generation lock and finalization
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Acquire a generation lock using SELECT FOR UPDATE to prevent race conditions
     *
     * Creates a new log entry with STATUS_RUNNING. If a running log already exists:
     * - When $throwOnConflict is true: throws RuntimeException (unless the job is stale)
     * - When $throwOnConflict is false: returns the existing log (unless the job is stale)
     *
     * Stale jobs (running longer than HUNG_FEED_TIMEOUT_MINUTES) are automatically
     * marked as failed, allowing a new generation to proceed.
     *
     * Sets $this->_log on success and returns null.
     *
     * @throws RuntimeException if lock cannot be acquired and $throwOnConflict is true
     */
    protected function _acquireGenerationLock(bool $throwOnConflict = false): ?Maho_FeedManager_Model_Log
    {
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');
        $staleLogId = null;

        $connection->beginTransaction();
        try {
            $tableName = $resource->getTableName('feedmanager/log');
            $select = $connection->select()
                ->from($tableName, ['log_id', 'started_at'])
                ->where('feed_id = ?', $this->_feed->getId())
                ->where('status = ?', Maho_FeedManager_Model_Log::STATUS_RUNNING)
                ->forUpdate();

            $runningRow = $connection->fetchRow($select);

            if ($runningRow) {
                $hungTimeout = Maho_FeedManager_Model_Cron::HUNG_FEED_TIMEOUT_MINUTES * 60;
                $startedAt = strtotime($runningRow['started_at']);
                $isStale = (time() - $startedAt) > $hungTimeout;

                if ($isStale) {
                    // Mark the stale job as failed within the same transaction
                    $connection->update(
                        $tableName,
                        [
                            'status' => Maho_FeedManager_Model_Log::STATUS_FAILED,
                            'completed_at' => Mage::app()->getLocale()->formatDateForDb('now'),
                        ],
                        ['log_id = ?' => $runningRow['log_id']],
                    );

                    $staleLogId = $runningRow['log_id'];

                    Mage::log(
                        "FeedManager: Cleared stale generation lock for feed '{$this->_feed->getName()}' (Log ID: {$runningRow['log_id']}, running since {$runningRow['started_at']})",
                        Mage::LOG_WARNING,
                    );
                } else {
                    $connection->rollBack();

                    if ($throwOnConflict) {
                        throw new RuntimeException('Feed generation already in progress (Log ID: ' . $runningRow['log_id'] . ')');
                    }

                    $existingLog = Mage::getModel('feedmanager/log')->load($runningRow['log_id']);
                    Mage::log(
                        "FeedManager: Generation already running for feed '{$this->_feed->getName()}' (Log ID: {$runningRow['log_id']})",
                        Mage::LOG_WARNING,
                    );
                    return $existingLog;
                }
            }

            $this->_log = Mage::getModel('feedmanager/log');
            $this->_log->setFeedId((int) $this->_feed->getId())
                ->setStatus(Maho_FeedManager_Model_Log::STATUS_RUNNING)
                ->setStartedAt(Mage::app()->getLocale()->formatDateForDb('now'))
                ->save();

            $connection->commit();
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        // Record error on the stale log after commit, so the model load
        // sees the committed status and save() cannot overwrite it
        if ($staleLogId !== null) {
            $staleLog = Mage::getModel('feedmanager/log')->load($staleLogId);
            $staleLog->addError('Generation was interrupted or timed out (exceeded ' . Maho_FeedManager_Model_Cron::HUNG_FEED_TIMEOUT_MINUTES . ' minutes)')->save();
        }

        return null;
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
     * Validate generated feed, move temp file to output, and compress if configured
     *
     * @param string $tempPath Path to the temporary feed file
     * @param array $errors Errors array, validation errors/warnings are appended by reference
     * @return string Final file path (may differ from output path if compressed)
     * @throws RuntimeException if validation or file move fails
     */
    protected function _validateAndMoveToOutput(string $tempPath, array &$errors): string
    {
        $validator = new Maho_FeedManager_Model_Validator();
        if (!$validator->validate($tempPath, $this->_feed->getFileFormat())) {
            $errors = array_merge($errors, $validator->getErrors());
            throw new RuntimeException('Feed validation failed: ' . implode(', ', $validator->getErrors()));
        }

        foreach ($validator->getWarnings() as $warning) {
            $errors[] = "[Validation Warning] {$warning}";
        }

        $outputPath = $this->_getOutputPath();
        if (!rename($tempPath, $outputPath)) {
            throw new RuntimeException("Failed to move temp file to final path: {$outputPath}");
        }

        $finalPath = $outputPath;
        if ($this->_feed->getGzipCompression()) {
            $finalPath = $this->_compressFile($outputPath);
        }

        return $finalPath;
    }

    /**
     * Finalize a successful generation: update log, update feed, reset notifier
     *
     * @param int $productCount Number of products generated
     * @param int $fileSize Size of the generated file in bytes
     * @param array $errors Any errors/warnings to save to the log
     */
    protected function _finalizeGenerationSuccess(int $productCount, int $fileSize, array $errors): void
    {
        $this->_log->setStatus(Maho_FeedManager_Model_Log::STATUS_COMPLETED)
            ->setCompletedAt(Mage::app()->getLocale()->formatDateForDb('now'))
            ->setProductCount($productCount)
            ->setFileSize($fileSize);

        $this->_saveErrorsToLog($errors);
        $this->_log->save();

        $this->_feed->setLastGeneratedAt(Mage::app()->getLocale()->formatDateForDb('now'))
            ->setLastProductCount($productCount)
            ->setLastFileSize($fileSize)
            ->save();

        $notifier = new Maho_FeedManager_Model_Notifier();
        $notifier->resetNotificationFlag($this->_feed);
    }

    /**
     * Save an array of error messages to the log model
     */
    protected function _saveErrorsToLog(array $errors): void
    {
        foreach ($errors as $error) {
            $this->_log->addError($error);
        }
    }
}
