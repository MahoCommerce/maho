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
 * Shared product processing functionality for feed generators
 *
 * This trait provides common methods used by both synchronous (Generator)
 * and batch (Generator_Batch) feed generation classes.
 *
 * Requirements for using classes:
 * - Must have $_feed property of type Maho_FeedManager_Model_Feed
 * - Must have $_mapper property of type Maho_FeedManager_Model_Mapper (for template methods)
 */
trait Maho_FeedManager_Model_Generator_ProductProcessorTrait
{
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

        // Collect validated attributes from conditions
        $conditions = $this->_feed->getConditions();
        if (method_exists($conditions, 'collectValidatedAttributes')) {
            $conditions->collectValidatedAttributes($collection);
        }

        // Apply condition groups as SQL filters
        $this->_applyConditionGroupsToCollection($collection);

        return $collection;
    }

    /**
     * Apply product type filter to collection
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
     * Apply condition groups to collection as SQL WHERE clauses
     */
    protected function _applyConditionGroupsToCollection(Mage_Catalog_Model_Resource_Product_Collection $collection): void
    {
        $groups = $this->_feed->getConditionGroupsArray();
        Mage::getSingleton('feedmanager/filter_condition')->applyConditionGroupsToCollection($collection, $groups);
    }

    /**
     * Validate product against Rule conditions (legacy Mage_Rule system)
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
     * Render header or footer template
     */
    protected function _renderHeaderFooter(string $template, Maho_FeedManager_Model_Feed $feed): string
    {
        $store = Mage::app()->getStore($feed->getStoreId());

        $replacements = [
            '{{store_name}}' => $store->getName(),
            '{{store_url}}' => $store->getBaseUrl(),
            '{{generation_date}}' => Mage_Core_Model_Locale::now(),
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
        $productData['_feed'] = [
            'price_decimals' => $feed->getPriceDecimals() ?? 2,
            'price_decimal_point' => $feed->getPriceDecimalPoint() ?? '.',
            'price_thousands_sep' => $feed->getPriceThousandsSep() ?? '',
            'price_currency' => $feed->getPriceCurrency() ?: Mage::app()->getStore($feed->getStoreId())->getBaseCurrencyCode(),
        ];

        // Find all field configurations in the template: {type="..." value="..." ...}
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

        // Support simple {{placeholder}} syntax for backwards compatibility
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
        $useParent = ($config['parent'] ?? 'no') === 'yes';

        // Get parent product if needed
        $parentProduct = null;
        if ($useParent && $product->getTypeId() === 'simple') {
            $parentIds = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
            if (!empty($parentIds)) {
                $parentProduct = Mage::getModel('catalog/product')->load($parentIds[0]);
            }
        }

        $result = '';

        switch ($type) {
            case 'attribute':
                $result = $this->_getProductValueForTemplate($product, $value, $feed);
                if (empty($result) && $parentProduct) {
                    $result = $this->_getProductValueForTemplate($parentProduct, $value, $feed);
                }
                break;

            case 'text':
            case 'static':
                $result = $value;
                break;

            case 'combined':
                $result = $this->_processCombinedTemplate($value, $product, $feed, $parentProduct);
                break;

            case 'category':
                $result = $this->_getProductCategoryPath($product);
                break;

            case 'images':
                $result = $this->_getAdditionalImage($product, $value);
                break;

            case 'custom_field':
                $result = (string) $product->getData($value);
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
        preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);

        $result = $template;
        foreach ($matches[1] as $placeholder) {
            $value = $this->_getProductValueForTemplate($product, $placeholder, $feed);

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

    /** @var array<int, string> Cached category names by ID */
    protected static array $_categoryNameCache = [];

    /** @var array<int, string> Cached category paths by ID */
    protected static array $_categoryPathCache = [];

    /**
     * Get product category path (optimized with caching)
     */
    protected function _getProductCategoryPath(Mage_Catalog_Model_Product $product): string
    {
        $categoryIds = $product->getCategoryIds();
        if (empty($categoryIds)) {
            return '';
        }

        $categoryId = (int) end($categoryIds);

        // Return cached path if available
        if (isset(self::$_categoryPathCache[$categoryId])) {
            return self::$_categoryPathCache[$categoryId];
        }

        // Get the category's path string (e.g., "1/2/5/12")
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $table = $resource->getTableName('catalog/category');

        $categoryPath = $adapter->fetchOne(
            $adapter->select()
                ->from($table, ['path'])
                ->where('entity_id = ?', $categoryId),
        );

        if (!$categoryPath) {
            self::$_categoryPathCache[$categoryId] = '';
            return '';
        }

        $pathIds = explode('/', $categoryPath);
        $pathIds = array_filter($pathIds, fn($id) => (int) $id > 2);

        if (empty($pathIds)) {
            self::$_categoryPathCache[$categoryId] = '';
            return '';
        }

        // Find which category names we need to load
        $missingIds = array_diff($pathIds, array_keys(self::$_categoryNameCache));

        if (!empty($missingIds)) {
            // Load missing category names in a single query
            $nameAttrId = Mage::getSingleton('eav/config')
                ->getAttribute('catalog_category', 'name')
                ->getAttributeId();

            $varcharTable = $resource->getTableName('catalog_category_entity_varchar');

            $select = $adapter->select()
                ->from($varcharTable, ['entity_id', 'value'])
                ->where('entity_id IN (?)', $missingIds)
                ->where('attribute_id = ?', $nameAttrId)
                ->where('store_id = 0');

            $names = $adapter->fetchPairs($select);

            foreach ($names as $id => $name) {
                self::$_categoryNameCache[(int) $id] = $name;
            }
        }

        // Build the path from cached names
        $pathNames = [];
        foreach ($pathIds as $pathId) {
            $pathId = (int) $pathId;
            if (isset(self::$_categoryNameCache[$pathId])) {
                $pathNames[] = self::$_categoryNameCache[$pathId];
            }
        }

        $result = implode(' > ', $pathNames);
        self::$_categoryPathCache[$categoryId] = $result;

        return $result;
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
            default => 'xml',
        };

        return $outputDir . DS . $filename . '.' . $extension;
    }
}
