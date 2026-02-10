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
 * Attribute Mapper
 *
 * Maps product attributes to feed attributes with transformation support.
 *
 * Error Handling Pattern:
 * - mapProduct(): Returns array, catches exceptions and returns partial data with error info
 * - Attribute getters: Return empty string if attribute not found, never throw
 * - Category mapping: Returns empty string if no mapping found
 * - Transformers: Applied in try/catch, failures logged and original value returned
 */
class Maho_FeedManager_Model_Mapper
{
    public const SOURCE_TYPE_ATTRIBUTE = 'attribute';
    public const SOURCE_TYPE_STATIC = 'static';
    public const SOURCE_TYPE_RULE = 'rule';
    public const SOURCE_TYPE_COMBINED = 'combined';
    public const SOURCE_TYPE_TAXONOMY = 'taxonomy';

    /**
     * Known price fields that should be auto-formatted
     */
    protected const PRICE_FIELDS = [
        'price',
        'special_price',
        'regular_price',
        'final_price',
        'min_price',
        'max_price',
        'tier_price',
        'group_price',
        'msrp',
        'cost',
    ];

    protected Maho_FeedManager_Model_Feed $_feed;
    protected ?Maho_FeedManager_Model_Platform_AdapterInterface $_platform = null;
    protected array $_mappings = [];
    protected array $_categoryMappings = [];
    protected array $_taxonomyMappingsByPlatform = [];
    protected ?int $_storeId = null;

    /** @var array<int, int|null> Cache of child ID => parent ID mappings */
    protected array $_childParentMap = [];

    /** @var array<int, array|null> Cache of parent product data by parent ID */
    protected array $_parentDataCache = [];

    /** @var array<int, array{name: string, path: string}> Static cache of category data (id => [name, path]) */
    protected static array $_categoryCache = [];

    /** @var int|null Store ID for which category cache was loaded */
    protected static ?int $_categoriesCacheStoreId = null;

    /**
     * Price formatting settings from feed
     */
    protected int $_priceDecimals = 2;
    protected string $_priceDecimalPoint = '.';
    protected string $_priceThousandsSep = '';
    protected string $_priceCurrency = '';
    protected bool $_priceCurrencySuffix = true;

    /**
     * Initialize mapper with feed configuration
     */
    public function __construct(Maho_FeedManager_Model_Feed $feed)
    {
        $this->_feed = $feed;
        $this->_storeId = (int) $feed->getStoreId();
        $this->_platform = Maho_FeedManager_Model_Platform::getAdapter($feed->getPlatform());
        $this->_loadMappings();
        $this->_loadCategoryMappings();
        $this->_loadPriceSettings();
    }

    /**
     * Load price formatting settings from feed
     */
    protected function _loadPriceSettings(): void
    {
        $this->_priceDecimals = (int) ($this->_feed->getPriceDecimals() ?? 2);
        $this->_priceDecimalPoint = (string) ($this->_feed->getPriceDecimalPoint() ?: '.');
        $this->_priceThousandsSep = (string) ($this->_feed->getPriceThousandsSep() ?? '');
        $this->_priceCurrency = (string) ($this->_feed->getPriceCurrency() ?: '');
        $this->_priceCurrencySuffix = (bool) ($this->_feed->getPriceCurrencySuffix() ?? true);
    }

    /**
     * Load attribute mappings for the feed
     */
    protected function _loadMappings(): void
    {
        $this->_mappings = [];

        // Get mappings from database
        $mappingCollection = $this->_feed->getAttributeMappings();
        foreach ($mappingCollection as $mapping) {
            $this->_mappings[$mapping->getFeedAttribute()] = [
                'source_type' => $mapping->getSourceType(),
                'source_value' => $mapping->getSourceValue(),
                'transformers' => $mapping->getTransformersArray(),
                'conditions' => $mapping->getConditionsArray(),
            ];
        }

        // Apply default mappings from platform for unmapped attributes
        if ($this->_platform) {
            $defaults = $this->_platform->getDefaultMappings();
            foreach ($defaults as $feedAttr => $config) {
                if (!isset($this->_mappings[$feedAttr])) {
                    $this->_mappings[$feedAttr] = [
                        'source_type' => $config['source_type'],
                        'source_value' => $config['source_value'],
                        'transformers' => [],
                        'conditions' => [],
                    ];
                }
            }
        }
    }

    /**
     * Load category mappings
     */
    protected function _loadCategoryMappings(): void
    {
        $this->_categoryMappings = [];

        if (!$this->_platform || !$this->_platform->supportsCategoryMapping()) {
            return;
        }

        $collection = Mage::getResourceModel('feedmanager/categoryMapping_collection')
            ->addFieldToFilter('platform', $this->_feed->getPlatform());

        foreach ($collection as $mapping) {
            $this->_categoryMappings[$mapping->getCategoryId()] = $mapping->getPlatformCategoryId();
        }
    }

    /**
     * Map a product to feed format
     *
     * @return array<string, mixed> Mapped product data
     */
    public function mapProduct(Mage_Catalog_Model_Product $product): array
    {
        $rawData = $this->_extractProductData($product);
        $mappedData = [];

        foreach ($this->_mappings as $feedAttribute => $config) {
            // Check conditions first
            if (!empty($config['conditions']) && !$this->_evaluateConditions($config['conditions'], $rawData)) {
                continue;
            }

            // Get source value
            $value = $this->_getSourceValue($config, $rawData, $product);
            $sourceValue = $config['source_value'] ?? '';

            // Apply transformers, or auto-format price fields
            $hasExplicitTransformers = !empty($config['transformers']);
            if ($hasExplicitTransformers) {
                $value = Maho_FeedManager_Model_Transformer::pipeline($value, $config['transformers'], $rawData);
            } elseif ($this->_isPriceField($sourceValue) && is_numeric($value)) {
                // Auto-format price fields using feed settings (only if no explicit transformer)
                $value = $this->_formatPrice($value);
            }

            $mappedData[$feedAttribute] = $value;
        }

        // Add mapped category if platform supports it
        if ($this->_platform && $this->_platform->supportsCategoryMapping()) {
            $categoryValue = $this->_getMappedCategory($product);
            if ($categoryValue) {
                $categoryAttr = $this->_getCategoryAttributeName();
                $mappedData[$categoryAttr] = $categoryValue;
            }
        }

        // Let platform adapter do final transformation
        if ($this->_platform) {
            $mappedData = $this->_platform->transformProductData($mappedData);
        }

        return $mappedData;
    }

    /**
     * Extract raw product data into array
     */
    protected function _extractProductData(Mage_Catalog_Model_Product $product): array
    {
        $data = [];

        // Basic attributes
        $data['entity_id'] = $product->getId();
        $data['sku'] = $product->getSku();
        $data['name'] = $product->getName();
        $data['description'] = $product->getDescription();
        $data['short_description'] = $product->getShortDescription();
        $data['price'] = $product->getFinalPrice();
        $data['regular_price'] = $product->getPrice();
        $data['special_price'] = $product->getSpecialPrice();
        $data['special_from_date'] = $product->getSpecialFromDate();
        $data['special_to_date'] = $product->getSpecialToDate();
        $data['type_id'] = $product->getTypeId();
        $data['visibility'] = $product->getVisibility();
        $data['status'] = $product->getStatus();

        // Store information (from config)
        $storeId = $this->_feed->getStoreId();
        $data['store_name'] = Mage::getStoreConfig('general/store_information/name', $storeId) ?: '';
        $data['store_url'] = Mage::app()->getStore($storeId)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $data['store_phone'] = Mage::getStoreConfig('general/store_information/phone', $storeId) ?: '';
        $data['store_email'] = Mage::getStoreConfig('trans_email/ident_general/email', $storeId) ?: '';
        $data['store_country'] = Mage::getStoreConfig('general/store_information/merchant_country', $storeId) ?: '';
        $data['store_currency'] = Mage::app()->getStore($storeId)->getCurrentCurrencyCode();

        // Stock information
        $stockItem = $product->getStockItem();
        if ($stockItem) {
            $data['qty'] = (int) $stockItem->getQty();
            $data['is_in_stock'] = (int) $stockItem->getIsInStock();
            $data['stock_status'] = $stockItem->getIsInStock() ? 1 : 0;
        } else {
            $data['qty'] = 0;
            $data['is_in_stock'] = 0;
            $data['stock_status'] = 0;
        }

        // Parent/variant relationship
        $parentId = $this->_getParentId($product);
        $data['parent_id'] = $parentId;
        $data['is_variant'] = $parentId !== null;
        $data['has_parent'] = $parentId !== null;

        // URLs - build SEO URL from url_key
        $data['url'] = $this->_getProductSeoUrl($product);

        // Images
        $data['image'] = $this->_getImageUrl($product, 'image');
        $data['small_image'] = $this->_getImageUrl($product, 'small_image');
        $data['thumbnail'] = $this->_getImageUrl($product, 'thumbnail');
        $additionalImages = $this->_getAdditionalImages($product);
        $data['additional_images'] = $additionalImages;
        $data['additional_images_csv'] = implode(',', $additionalImages);

        // Categories
        $data['category_ids'] = $product->getCategoryIds();
        $data['category_names'] = $this->_getCategoryNames($product);
        $data['category_path'] = $this->_getCategoryPath($product);

        // Custom attributes - load all attribute values
        foreach ($product->getAttributes() as $attribute) {
            $code = $attribute->getAttributeCode();
            if (!isset($data[$code])) {
                $value = $product->getData($code);
                // For select/multiselect, use option text as primary value
                if ($attribute->usesSource() && $value !== null && $value !== '') {
                    $textValue = $product->getAttributeText($code);
                    // Store text value as primary, ID as secondary
                    $data[$code] = $textValue ?: $value;
                    $data[$code . '_id'] = $value;
                } else {
                    $data[$code] = $value;
                }
            }
        }

        // Currency from store
        $data['currency'] = Mage::app()->getStore($this->_storeId)->getCurrentCurrencyCode();

        // Add parent product data for child products (simple products in configurable)
        if ($product->getTypeId() === 'simple') {
            $parentData = $this->_extractParentProductData($product);
            if ($parentData) {
                foreach ($parentData as $key => $value) {
                    $data['parent_' . $key] = $value;
                }
            }
        }

        // Add feed config for transformers (Formats & Regional Settings)
        $data['_feed'] = [
            'price_decimals' => $this->_priceDecimals,
            'price_decimal_point' => $this->_priceDecimalPoint,
            'price_thousands_sep' => $this->_priceThousandsSep,
            'price_currency' => $this->_priceCurrency,
        ];

        return $data;
    }

    /**
     * Preload child-to-parent ID mappings for a batch of product IDs
     *
     * This avoids N+1 queries when processing simple products that belong to configurables.
     *
     * @param array<int> $productIds Product IDs to preload parent mappings for
     */
    public function preloadParentMappings(array $productIds): void
    {
        if (empty($productIds)) {
            return;
        }

        // Query catalog_product_super_link to get all child->parent mappings
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $table = $resource->getTableName('catalog/product_super_link');

        $select = $adapter->select()
            ->from($table, ['product_id', 'parent_id'])
            ->where('product_id IN (?)', $productIds);

        $rows = $adapter->fetchPairs($select);

        foreach ($productIds as $productId) {
            // Mark all as checked (null = no parent, int = has parent)
            $this->_childParentMap[$productId] = isset($rows[$productId]) ? (int) $rows[$productId] : null;
        }
    }

    /**
     * Extract parent product data for child products
     *
     * Uses caching to avoid loading the same parent multiple times.
     *
     * @return array<string, mixed>|null Parent data or null if no parent
     */
    protected function _extractParentProductData(Mage_Catalog_Model_Product $product): ?array
    {
        $childId = (int) $product->getId();

        // Get parent ID - use cached mapping if available
        if (array_key_exists($childId, $this->_childParentMap)) {
            $parentId = $this->_childParentMap[$childId];
        } else {
            // Fall back to individual lookup if not preloaded
            $parentIds = Mage::getModel('catalog/product_type_configurable')
                ->getParentIdsByChild($childId);
            $parentId = empty($parentIds) ? null : (int) $parentIds[0];
            $this->_childParentMap[$childId] = $parentId;
        }

        if ($parentId === null) {
            return null;
        }

        // Return cached parent data if available
        if (array_key_exists($parentId, $this->_parentDataCache)) {
            return $this->_parentDataCache[$parentId];
        }

        // Load and cache parent product data
        $parent = Mage::getModel('catalog/product')
            ->setStoreId($this->_storeId)
            ->load($parentId);

        if (!$parent->getId()) {
            $this->_parentDataCache[$parentId] = null;
            return null;
        }

        // Extract parent data (subset of key attributes for fallback)
        $data = [];
        $data['entity_id'] = $parent->getId();
        $data['sku'] = $parent->getSku();
        $data['name'] = $parent->getName();
        $data['description'] = $parent->getDescription();
        $data['short_description'] = $parent->getShortDescription();
        $data['price'] = $parent->getFinalPrice();
        $data['regular_price'] = $parent->getPrice();
        $data['special_price'] = $parent->getSpecialPrice();
        $data['special_from_date'] = $parent->getSpecialFromDate();
        $data['special_to_date'] = $parent->getSpecialToDate();
        $data['url'] = $this->_getProductSeoUrl($parent);
        $data['image'] = $this->_getImageUrl($parent, 'image');
        $data['small_image'] = $this->_getImageUrl($parent, 'small_image');
        $data['thumbnail'] = $this->_getImageUrl($parent, 'thumbnail');

        // Add common custom attributes
        foreach ($parent->getAttributes() as $attribute) {
            $code = $attribute->getAttributeCode();
            if (!isset($data[$code])) {
                $value = $parent->getData($code);
                // For select/multiselect, use option text as primary value
                if ($attribute->usesSource() && $value !== null && $value !== '') {
                    $textValue = $parent->getAttributeText($code);
                    $data[$code] = $textValue ?: $value;
                    $data[$code . '_id'] = $value;
                } else {
                    $data[$code] = $value;
                }
            }
        }

        // Cache the extracted data
        $this->_parentDataCache[$parentId] = $data;

        return $data;
    }

    /**
     * Get source value based on mapping type
     */
    protected function _getSourceValue(array $config, array $rawData, Mage_Catalog_Model_Product $product): mixed
    {
        $sourceType = $config['source_type'];
        $sourceValue = $config['source_value'];
        $useParentMode = (string) ($config['use_parent'] ?? '');

        return match ($sourceType) {
            self::SOURCE_TYPE_ATTRIBUTE => self::getValueWithParentMode($sourceValue, $rawData, $useParentMode),
            self::SOURCE_TYPE_STATIC => $sourceValue,
            self::SOURCE_TYPE_RULE => $this->_evaluateRule($sourceValue, $rawData, $product),
            self::SOURCE_TYPE_COMBINED => $this->_evaluateCombined($sourceValue, $rawData),
            self::SOURCE_TYPE_TAXONOMY => $this->_getTaxonomyForProduct($sourceValue, $product),
            default => null,
        };
    }

    /**
     * Evaluate a dynamic rule-based source
     */
    protected function _evaluateRule(string $ruleCode, array $rawData, Mage_Catalog_Model_Product $product): mixed
    {
        // Load the rule by code
        $rule = Mage::getModel('feedmanager/dynamicRule')->loadByCode($ruleCode);

        if (!$rule->getId() || !$rule->getIsEnabled()) {
            return null;
        }

        // Create evaluator and evaluate
        $evaluator = new Maho_FeedManager_Model_DynamicRule_Evaluator($rule);
        return $evaluator->evaluate($rawData, $product);
    }

    /**
     * Evaluate a combined source (template-based)
     */
    protected function _evaluateCombined(string $template, array $rawData): string
    {
        // Use the combine_fields transformer
        return (string) Maho_FeedManager_Model_Transformer::apply(
            '',
            'combine_fields',
            ['template' => $template],
            $rawData,
        );
    }

    /**
     * Evaluate conditions for including an attribute
     */
    protected function _evaluateConditions(array $conditions, array $rawData): bool
    {
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'eq';
            $value = $condition['value'] ?? '';

            $fieldValue = $rawData[$field] ?? null;

            $result = match ($operator) {
                'eq' => (string) $fieldValue === $value,
                'neq' => (string) $fieldValue !== $value,
                'empty' => empty($fieldValue),
                'not_empty' => !empty($fieldValue),
                'gt' => is_numeric($fieldValue) && $fieldValue > $value,
                'lt' => is_numeric($fieldValue) && $fieldValue < $value,
                default => true,
            };

            if (!$result) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get product SEO URL using url_key
     */
    protected function _getProductSeoUrl(Mage_Catalog_Model_Product $product): string
    {
        $urlKey = $product->getUrlKey();
        if ($urlKey) {
            $suffix = Mage::getStoreConfig('catalog/seo/product_url_suffix', $this->_storeId);
            // Use web URL type to avoid index.php
            $baseUrl = Mage::app()->getStore($this->_storeId)->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
            return $baseUrl . $urlKey . $suffix;
        }

        // Fallback to standard URL if no url_key
        return $product->getProductUrl();
    }

    /**
     * Get image URL
     */
    protected function _getImageUrl(Mage_Catalog_Model_Product $product, string $imageType): string
    {
        try {
            $image = $product->getData($imageType);
            if ($image && $image !== 'no_selection') {
                return Mage::getBaseUrl('media') . 'catalog/product' . $image;
            }
        } catch (Exception $e) {
            // Silent fail
        }

        return '';
    }

    /**
     * Get additional product images
     */
    protected function _getAdditionalImages(Mage_Catalog_Model_Product $product): array
    {
        $images = [];

        try {
            $gallery = $product->getMediaGalleryImages();
            if ($gallery && $gallery->getSize() > 0) {
                foreach ($gallery as $image) {
                    $url = $image->getUrl();
                    // Ensure we have a string URL
                    if (is_string($url) && !empty($url)) {
                        $images[] = $url;
                    }
                }
            }
        } catch (Exception $e) {
            // Silent fail - gallery may not be loaded
        }

        // Fallback: try to build URLs from media_gallery attribute
        if (empty($images)) {
            $mediaGallery = $product->getData('media_gallery');
            if (is_array($mediaGallery) && isset($mediaGallery['images'])) {
                $baseUrl = Mage::getBaseUrl('media') . 'catalog/product';
                foreach ($mediaGallery['images'] as $img) {
                    if (isset($img['file']) && !isset($img['disabled'])) {
                        $images[] = $baseUrl . $img['file'];
                    }
                }
            }
        }

        return $images;
    }

    /**
     * Load all categories into static cache (one-time operation)
     *
     * This is called once per feed generation and caches all category data
     * to avoid loading individual category models.
     */
    protected function _ensureCategoryCache(): void
    {
        $storeId = $this->_storeId ?: 0;

        if (self::$_categoriesCacheStoreId === $storeId) {
            return;
        }

        // Clear cache when store context changes to avoid cross-store data leakage
        self::$_categoryCache = [];

        // Load all categories with a single query
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');

        // Get category entity_id, name (from varchar table), and path
        $categoryTable = $resource->getTableName('catalog/category');
        $varcharTable = $resource->getTableName('catalog_category_entity_varchar');
        $eavAttribute = Mage::getSingleton('eav/config')
            ->getAttribute(Mage_Catalog_Model_Category::ENTITY, 'name');
        $nameAttributeId = $eavAttribute->getId();

        // Query category data with name (prefer store-specific, fall back to default)
        $select = $adapter->select()
            ->from(['c' => $categoryTable], ['entity_id', 'path'])
            ->joinLeft(
                ['cv_store' => $varcharTable],
                $adapter->quoteInto(
                    'cv_store.entity_id = c.entity_id AND cv_store.attribute_id = ? AND cv_store.store_id = ' . (int) $storeId,
                    $nameAttributeId,
                ),
                [],
            )
            ->joinLeft(
                ['cv_default' => $varcharTable],
                $adapter->quoteInto(
                    'cv_default.entity_id = c.entity_id AND cv_default.attribute_id = ? AND cv_default.store_id = 0',
                    $nameAttributeId,
                ),
                [],
            )
            ->columns(['name' => new Maho\Db\Expr('COALESCE(cv_store.value, cv_default.value)')]);

        $rows = $adapter->fetchAll($select);

        foreach ($rows as $row) {
            self::$_categoryCache[(int) $row['entity_id']] = [
                'name' => (string) ($row['name'] ?? ''),
                'path' => (string) $row['path'],
            ];
        }

        self::$_categoriesCacheStoreId = $storeId;
    }

    /**
     * Get category name from cache
     */
    protected function _getCategoryName(int $categoryId): string
    {
        $this->_ensureCategoryCache();
        return self::$_categoryCache[$categoryId]['name'] ?? '';
    }

    /**
     * Get category path from cache
     */
    protected function _getCategoryPathData(int $categoryId): string
    {
        $this->_ensureCategoryCache();
        return self::$_categoryCache[$categoryId]['path'] ?? '';
    }

    /**
     * Get category names for product
     */
    protected function _getCategoryNames(Mage_Catalog_Model_Product $product): array
    {
        $this->_ensureCategoryCache();
        $names = [];
        $categoryIds = $product->getCategoryIds();

        foreach ($categoryIds as $categoryId) {
            $catId = (int) $categoryId;
            if (isset(self::$_categoryCache[$catId]) && self::$_categoryCache[$catId]['name'] !== '') {
                $names[] = self::$_categoryCache[$catId]['name'];
            }
        }

        return $names;
    }

    /**
     * Get deepest category path
     */
    protected function _getCategoryPath(Mage_Catalog_Model_Product $product): string
    {
        $this->_ensureCategoryCache();
        $categoryIds = $product->getCategoryIds();
        if (empty($categoryIds)) {
            return '';
        }

        $deepestPath = '';
        $maxDepth = 0;

        foreach ($categoryIds as $categoryId) {
            $catId = (int) $categoryId;
            if (!isset(self::$_categoryCache[$catId])) {
                continue;
            }

            $pathIds = explode('/', self::$_categoryCache[$catId]['path']);
            $depth = count($pathIds);

            if ($depth > $maxDepth) {
                $maxDepth = $depth;
                $pathNames = [];
                foreach ($pathIds as $pathId) {
                    $pId = (int) $pathId;
                    if ($pId > 1 && isset(self::$_categoryCache[$pId])) { // Skip root category
                        $pathNames[] = self::$_categoryCache[$pId]['name'];
                    }
                }
                $deepestPath = implode(' > ', $pathNames);
            }
        }

        return $deepestPath;
    }

    /**
     * Get mapped platform category
     */
    protected function _getMappedCategory(Mage_Catalog_Model_Product $product): ?string
    {
        $this->_ensureCategoryCache();
        $categoryIds = $product->getCategoryIds();

        // Try to find a mapped category, preferring deeper categories
        $bestMatch = null;
        $bestDepth = 0;

        foreach ($categoryIds as $categoryId) {
            $catId = (int) $categoryId;
            if (isset($this->_categoryMappings[$catId]) && isset(self::$_categoryCache[$catId])) {
                $depth = count(explode('/', self::$_categoryCache[$catId]['path']));

                if ($depth > $bestDepth) {
                    $bestDepth = $depth;
                    $bestMatch = $this->_categoryMappings[$catId];
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Get taxonomy path for a product from a specific platform's mappings
     *
     * @param string $platform Platform code (google, facebook, pinterest, bing)
     * @param Mage_Catalog_Model_Product $product Product to get taxonomy for
     * @return string|null Taxonomy path or null if no mapping found
     */
    protected function _getTaxonomyForProduct(string $platform, Mage_Catalog_Model_Product $product): ?string
    {
        if (empty($platform)) {
            return null;
        }

        // Load mappings for this platform if not cached (with depths for performance)
        if (!isset($this->_taxonomyMappingsByPlatform[$platform])) {
            $this->_taxonomyMappingsByPlatform[$platform] = [];

            $collection = Mage::getResourceModel('feedmanager/categoryMapping_collection')
                ->addFieldToFilter('platform', $platform);

            // Get all category IDs that have mappings
            $categoryIds = [];
            $mappingData = [];
            foreach ($collection as $mapping) {
                $catId = $mapping->getCategoryId();
                $categoryIds[] = $catId;
                $mappingData[$catId] = $mapping->getPlatformCategoryId();
            }

            // Load category depths in a single query instead of per-product
            if (!empty($categoryIds)) {
                $resource = Mage::getSingleton('core/resource');
                $adapter = $resource->getConnection('core_read');
                $table = $resource->getTableName('catalog/category');

                $select = $adapter->select()
                    ->from($table, ['entity_id', 'path'])
                    ->where('entity_id IN (?)', $categoryIds);

                $categoryPaths = $adapter->fetchPairs($select);

                foreach ($mappingData as $catId => $taxonomyPath) {
                    $depth = isset($categoryPaths[$catId])
                        ? count(explode('/', $categoryPaths[$catId]))
                        : 0;
                    $this->_taxonomyMappingsByPlatform[$platform][$catId] = [
                        'path' => $taxonomyPath,
                        'depth' => $depth,
                    ];
                }
            }
        }

        $mappings = $this->_taxonomyMappingsByPlatform[$platform];
        if (empty($mappings)) {
            return null;
        }

        $categoryIds = $product->getCategoryIds();

        // Find deepest mapped category (no model loading - depths are pre-cached)
        $bestMatch = null;
        $bestDepth = 0;

        foreach ($categoryIds as $categoryId) {
            if (isset($mappings[$categoryId])) {
                $mapping = $mappings[$categoryId];
                if ($mapping['depth'] > $bestDepth) {
                    $bestDepth = $mapping['depth'];
                    $bestMatch = $mapping['path'];
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Get platform-specific category attribute name
     */
    protected function _getCategoryAttributeName(): string
    {
        $platform = $this->_feed->getPlatform();

        return match ($platform) {
            'google' => 'google_product_category',
            'facebook' => 'google_product_category', // Facebook also uses Google taxonomy
            default => 'category',
        };
    }

    /**
     * Get parent product ID for simple products in configurable
     */
    protected function _getParentId(Mage_Catalog_Model_Product $product): ?int
    {
        $childId = (int) $product->getId();

        if (array_key_exists($childId, $this->_childParentMap)) {
            return $this->_childParentMap[$childId];
        }

        $parentIds = Mage::getModel('catalog/product_type_configurable')
            ->getParentIdsByChild($childId);
        $parentId = empty($parentIds) ? null : (int) $parentIds[0];
        $this->_childParentMap[$childId] = $parentId;

        return $parentId;
    }

    /**
     * Get source type options for dropdown
     */
    public static function getSourceTypeOptions(): array
    {
        $helper = Mage::helper('feedmanager');
        return [
            '' => $helper->__('-- Select Type --'),
            self::SOURCE_TYPE_ATTRIBUTE => $helper->__('Product Attribute'),
            self::SOURCE_TYPE_STATIC => $helper->__('Static Value'),
            self::SOURCE_TYPE_RULE => $helper->__('Dynamic Rule'),
            self::SOURCE_TYPE_COMBINED => $helper->__('Combined Fields'),
            self::SOURCE_TYPE_TAXONOMY => $helper->__('Category Taxonomy'),
        ];
    }

    /**
     * Get available taxonomy platforms for dropdown
     * Uses Platform model for consistency with Category Mapping screen,
     * plus any custom platforms from existing category mappings
     */
    public static function getTaxonomyPlatformOptions(): array
    {
        // Get platforms from registered adapters (same as Category Mapping screen)
        $options = Maho_FeedManager_Model_Platform::getPlatformOptions();

        // Add any custom platforms from existing category mappings
        try {
            $resource = Mage::getSingleton('core/resource');
            $adapter = $resource->getConnection('core_read');
            $table = $resource->getTableName('feedmanager/category_mapping');

            $customPlatforms = $adapter->fetchCol(
                $adapter->select()
                    ->distinct()
                    ->from($table, ['platform'])
                    ->where('platform NOT IN (?)', array_keys($options)),
            );

            foreach ($customPlatforms as $platform) {
                $options[$platform] = ucfirst($platform);
            }
        } catch (Exception $e) {
            // Silently fail if table doesn't exist yet
        }

        return $options;
    }

    /**
     * Get available rules for dropdown
     */
    public static function getAvailableRules(): array
    {
        return [
            '' => '-- Select Rule --',
            'stock_status' => 'Stock Status (in_stock/out_of_stock)',
            'availability' => 'Availability (in stock/out of stock)',
            'sale_price' => 'Sale Price (if on sale)',
            'has_sale' => 'Has Sale (yes/no)',
            'category_path' => 'Category Path',
            'identifier_exists' => 'Identifier Exists (GTIN/MPN check)',
            'item_group_id' => 'Item Group ID (parent for variants)',
        ];
    }

    /**
     * Get valid special price considering date restrictions
     *
     * Returns the special price only if:
     * - It exists and is greater than 0
     * - Current date is within the special_from_date and special_to_date range (if set)
     *
     * @param array $productData Product data array containing special_price, special_from_date, special_to_date
     * @return float|null The valid special price or null if invalid/expired
     */
    public static function getValidSpecialPrice(array $productData): ?float
    {
        $specialPrice = $productData['special_price'] ?? null;

        // No special price or zero
        if ($specialPrice === null || $specialPrice === '' || (float) $specialPrice <= 0) {
            return null;
        }

        $specialPrice = (float) $specialPrice;
        $now = strtotime('today');

        // Check from_date
        $fromDate = $productData['special_from_date'] ?? null;
        if ($fromDate && strtotime($fromDate) > $now) {
            return null; // Special price hasn't started yet
        }

        // Check to_date
        $toDate = $productData['special_to_date'] ?? null;
        if ($toDate && strtotime($toDate) < $now) {
            return null; // Special price has expired
        }

        return $specialPrice;
    }

    /**
     * Get attribute value with optional parent fallback
     *
     * If the child value is empty and use_parent_fallback is true,
     * returns the parent value instead.
     *
     * @param string $attribute Attribute code
     * @param array $productData Product data array (should contain both attribute and parent_attribute)
     * @param bool $useParentFallback Whether to fall back to parent value if empty
     * @return mixed The attribute value (child or parent)
     */
    public static function getValueWithParentFallback(string $attribute, array $productData, bool $useParentFallback): mixed
    {
        $value = $productData[$attribute] ?? null;

        // If value exists and is not empty, use it
        if ($value !== null && $value !== '') {
            return $value;
        }

        // If fallback is disabled, return the empty value
        if (!$useParentFallback) {
            return $value;
        }

        // Try parent value
        $parentKey = 'parent_' . $attribute;
        return $productData[$parentKey] ?? null;
    }

    /**
     * Get attribute value with parent mode
     *
     * Supports three modes:
     * - "" (empty): Use child value only
     * - "if_empty": Use child value if present, fall back to parent if empty
     * - "always": Always use parent value
     *
     * @param string $attribute Attribute code
     * @param array $productData Product data array (should contain both attribute and parent_attribute)
     * @param string $useParentMode The parent mode: "", "if_empty", or "always"
     * @return mixed The attribute value
     */
    public static function getValueWithParentMode(string $attribute, array $productData, string $useParentMode): mixed
    {
        // Guard against empty attribute code
        if ($attribute === '') {
            return null;
        }

        $parentKey = 'parent_' . $attribute;

        // "always" mode: always use parent value
        if ($useParentMode === 'always') {
            return $productData[$parentKey] ?? $productData[$attribute] ?? null;
        }

        $value = $productData[$attribute] ?? null;

        // "if_empty" mode: fall back to parent when child is empty
        if ($useParentMode === 'if_empty' && ($value === null || $value === '')) {
            return $productData[$parentKey] ?? null;
        }

        // Default (empty mode): use child value only
        return $value;
    }

    /**
     * Set mappings from CSV builder column definitions
     */
    public function setMappingsFromCsvColumns(array $columns): self
    {
        $this->_mappings = [];

        foreach ($columns as $column) {
            $name = $column['name'] ?? '';
            if (!$name) {
                continue;
            }

            $transformers = [];
            if (!empty($column['transformers'])) {
                $transformers = Maho_FeedManager_Model_Transformer::parseChainString($column['transformers']);
            }

            $this->_mappings[$name] = [
                'source_type' => $column['source_type'] ?? self::SOURCE_TYPE_ATTRIBUTE,
                'source_value' => $column['source_value'] ?? '',
                'transformers' => $transformers,
                'conditions' => [],
                'use_parent' => !empty($column['use_parent']),
            ];
        }

        return $this;
    }

    /**
     * Set mappings from JSON builder structure definition
     */
    public function setMappingsFromJsonStructure(array $structure): self
    {
        $this->_mappings = [];
        $this->_buildMappingsFromJsonStructure($structure, '');
        return $this;
    }

    /**
     * Recursively build mappings from JSON structure
     */
    protected function _buildMappingsFromJsonStructure(array $structure, string $prefix): void
    {
        foreach ($structure as $key => $config) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            $type = $config['type'] ?? 'string';

            if ($type === 'object' && isset($config['properties'])) {
                // Recurse into nested objects
                $this->_buildMappingsFromJsonStructure($config['properties'], $fullKey);
            } elseif ($type === 'array' && isset($config['items'])) {
                // Handle array items
                $this->_mappings["{$fullKey}[]"] = [
                    'source_type' => $config['items']['source_type'] ?? self::SOURCE_TYPE_ATTRIBUTE,
                    'source_value' => $config['items']['source_value'] ?? '',
                    'transformers' => [],
                    'conditions' => [],
                    'json_type' => $config['items']['type'] ?? 'string',
                ];
            } else {
                $transformers = [];
                if (!empty($config['transformers'])) {
                    $transformers = Maho_FeedManager_Model_Transformer::parseChainString($config['transformers']);
                }

                $this->_mappings[$fullKey] = [
                    'source_type' => $config['source_type'] ?? self::SOURCE_TYPE_ATTRIBUTE,
                    'source_value' => $config['source_value'] ?? '',
                    'transformers' => $transformers,
                    'conditions' => [],
                    'json_type' => $type,
                ];
            }
        }
    }

    /**
     * Map product to JSON structure format
     */
    public function mapProductToJsonStructure(Mage_Catalog_Model_Product $product, array $structure): array
    {
        $rawData = $this->_extractProductData($product);
        return $this->_buildJsonObject($structure, $rawData, $product);
    }

    /**
     * Build JSON object from structure definition
     */
    protected function _buildJsonObject(array $structure, array $rawData, Mage_Catalog_Model_Product $product): array
    {
        $result = [];

        foreach ($structure as $key => $config) {
            $type = $config['type'] ?? 'string';

            if ($type === 'object' && isset($config['properties'])) {
                $result[$key] = $this->_buildJsonObject($config['properties'], $rawData, $product);
            } elseif ($type === 'array') {
                // For now, handle simple arrays from comma-separated values or media gallery
                $value = $this->_getSourceValue($config['items'] ?? $config, $rawData, $product);
                if (is_array($value)) {
                    $result[$key] = $value;
                } elseif (is_string($value) && str_contains($value, ',')) {
                    $result[$key] = array_map('trim', explode(',', $value));
                } else {
                    $result[$key] = $value ? [$value] : [];
                }
            } else {
                $value = $this->_getSourceValue($config, $rawData, $product);
                $sourceValue = $config['source_value'] ?? '';

                // Apply transformers
                $hasExplicitTransformers = !empty($config['transformers']);
                if ($hasExplicitTransformers) {
                    $transformers = is_array($config['transformers'])
                        ? $config['transformers']
                        : Maho_FeedManager_Model_Transformer::parseChainString($config['transformers']);
                    $value = Maho_FeedManager_Model_Transformer::pipeline($value, $transformers, $rawData);
                } elseif ($this->_isPriceField($sourceValue) && is_numeric($value)) {
                    // Auto-format price fields using feed settings (only if no explicit transformer)
                    $value = $this->_formatPrice($value);
                }

                // Cast to appropriate type
                if ($type === 'number') {
                    $result[$key] = (float) $value;
                } elseif ($type === 'boolean') {
                    $result[$key] = (bool) $value;
                } else {
                    $result[$key] = (string) $value;
                }
            }
        }

        return $result;
    }

    /**
     * Map product to XML structure format
     *
     * @param Mage_Catalog_Model_Product $product Product to map
     * @param array $structure XML structure definition (array of elements)
     * @param string $itemTag Tag name for item wrapper (empty = no wrapper)
     * @param int $indent Indentation level
     * @return string XML string for this product
     */
    public function mapProductToXmlStructure(
        Mage_Catalog_Model_Product $product,
        array $structure,
        string $itemTag = 'item',
        int $indent = 0,
    ): string {
        $rawData = $this->_extractProductData($product);
        $indentStr = str_repeat('    ', $indent);
        $xml = '';

        if ($itemTag) {
            $xml .= $indentStr . '<' . $itemTag . ">\n";
        }

        $xml .= $this->_buildXmlElements($structure, $rawData, $product, $indent + ($itemTag ? 1 : 0));

        if ($itemTag) {
            $xml .= $indentStr . '</' . $itemTag . ">\n";
        }

        return $xml;
    }

    /**
     * Build XML elements from structure definition
     */
    protected function _buildXmlElements(array $structure, array $rawData, Mage_Catalog_Model_Product $product, int $indent = 0): string
    {
        $xml = '';
        $indentStr = str_repeat('    ', $indent);

        foreach ($structure as $config) {
            $tag = $config['tag'] ?? 'element';
            $cdata = !empty($config['cdata']);
            $optional = !empty($config['optional']);
            $sourceValue = $config['source_value'] ?? '';

            // Handle nested elements (groups)
            if (isset($config['children']) && !empty($config['children'])) {
                $xml .= $indentStr . '<' . $tag . ">\n";
                $xml .= $this->_buildXmlElements($config['children'], $rawData, $product, $indent + 1);
                $xml .= $indentStr . '</' . $tag . ">\n";
                continue;
            }

            // Leaf element - get value
            $value = $this->_getSourceValue($config, $rawData, $product);

            // Apply explicit transformers if specified
            $hasExplicitTransformers = !empty($config['transformers']);
            if ($hasExplicitTransformers) {
                $transformers = is_array($config['transformers'])
                    ? $config['transformers']
                    : Maho_FeedManager_Model_Transformer::parseChainString($config['transformers']);
                $value = Maho_FeedManager_Model_Transformer::pipeline($value, $transformers, $rawData);
            } elseif ($this->_isPriceField($sourceValue) && is_numeric($value)) {
                // Auto-format price fields using feed settings (only if no explicit transformer)
                $value = $this->_formatPrice($value);
            }

            // Skip optional empty values
            if ($optional && ($value === null || $value === '')) {
                continue;
            }

            // Convert arrays to comma-separated string
            if (is_array($value)) {
                $value = implode(',', array_filter($value, fn($v) => $v !== null && $v !== ''));
            }

            // Build XML element
            $valueStr = (string) ($value ?? '');
            if ($cdata) {
                $xml .= $indentStr . '<' . $tag . '><![CDATA[' . $valueStr . ']]></' . $tag . ">\n";
            } else {
                $xml .= $indentStr . '<' . $tag . '>' . htmlspecialchars($valueStr) . '</' . $tag . ">\n";
            }
        }

        return $xml;
    }

    /**
     * Check if a source value is a known price field
     */
    protected function _isPriceField(string $sourceValue): bool
    {
        return in_array($sourceValue, self::PRICE_FIELDS, true);
    }

    /**
     * Format a price value using feed settings
     */
    protected function _formatPrice(mixed $value): string
    {
        if (!is_numeric($value)) {
            return (string) $value;
        }

        $formatted = number_format(
            (float) $value,
            $this->_priceDecimals,
            $this->_priceDecimalPoint,
            $this->_priceThousandsSep,
        );

        if ($this->_priceCurrencySuffix && $this->_priceCurrency !== '') {
            $formatted .= ' ' . $this->_priceCurrency;
        }

        return $formatted;
    }
}
