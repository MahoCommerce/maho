<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Service;

use MeiliSearch\Client;

/**
 * Product Service - Business logic for product operations
 */
class ProductService
{
    /** @phpstan-ignore-next-line */
    private ?Client $meilisearchClient = null;
    private bool $useMeilisearch = false;
    private ?string $indexBaseName = null;

    /**
     * Cache for category default sort orders (request-scoped)
     * @var array<int, array|null>
     */
    private static array $categorySortCache = [];

    /**
     * @param Client|null $meilisearchClient Meilisearch client instance
     * @param string|null $indexBaseName Base index name including prefix and store code (e.g., "dev_default")
     */
    /** @phpstan-ignore-next-line */
    public function __construct(?Client $meilisearchClient = null, ?string $indexBaseName = null)
    {
        $this->meilisearchClient = $meilisearchClient;
        $this->indexBaseName = $indexBaseName;
        // Only use Meilisearch if we have both client and index name
        $this->useMeilisearch = $meilisearchClient !== null && $indexBaseName !== null;
    }

    private function getIndexName(string $suffix): string
    {
        return $this->indexBaseName . '_' . $suffix;
    }

    /**
     * Get the barcode attribute code (from POS module if available, or default)
     */
    private function getBarcodeAttributeCode(): string
    {
        /** @phpstan-ignore-next-line */
        $posHelper = \Mage::helper('maho_pos');
        if ($posHelper && method_exists($posHelper, 'getBarcodeAttributeCode')) {
            return $posHelper->getBarcodeAttributeCode();
        }
        return 'barcode'; // Default fallback
    }

    /**
     * Get category's default sort order (with caching)
     *
     * @return array|null Sort configuration or null if using store default
     */
    private function getCategoryDefaultSort(int $categoryId): ?array
    {
        // Check request-scoped cache first
        if (array_key_exists($categoryId, self::$categorySortCache)) {
            return self::$categorySortCache[$categoryId];
        }

        // Evict oldest entries if cache exceeds max size
        if (count(self::$categorySortCache) >= 100) {
            self::$categorySortCache = array_slice(self::$categorySortCache, -50, null, true);
        }

        $category = \Mage::getModel('catalog/category')->load($categoryId);
        if (!$category->getId()) {
            self::$categorySortCache[$categoryId] = null;
            return null;
        }

        // Get category's default_sort_by or fall back to store config
        $sortBy = $category->getDefaultSortBy();
        if (!$sortBy) {
            $sortBy = \Mage::getStoreConfig('catalog/frontend/default_sort_by');
        }

        if (!$sortBy) {
            self::$categorySortCache[$categoryId] = null;
            return null;
        }

        // Map Magento sort options to our format
        // Common values: position, name, price, created_at
        $direction = 'ASC';
        if ($sortBy === 'price') {
            $direction = 'ASC'; // Low to high by default
        } elseif ($sortBy === 'created_at') {
            $direction = 'DESC'; // Newest first
        }

        $result = [
            'field' => $sortBy,
            'direction' => $direction,
        ];

        self::$categorySortCache[$categoryId] = $result;
        return $result;
    }

    /**
     * Get category ID and all descendant category IDs
     *
     * @return int[]
     */
    private function getCategoryAndDescendantIds(int $categoryId): array
    {
        $category = \Mage::getModel('catalog/category')->load($categoryId);
        if (!$category->getId()) {
            return [$categoryId];
        }

        // Get all children IDs (returns comma-separated string or array)
        $childrenIds = $category->getAllChildren(true);
        if (is_string($childrenIds)) {
            $childrenIds = array_filter(explode(',', $childrenIds));
        }

        return array_map('intval', $childrenIds);
    }

    /**
     * Get product by ID
     */
    public function getProductById(int $id): ?\Mage_Catalog_Model_Product
    {
        $product = \Mage::getModel('catalog/product')->load($id);

        return $product->getId() ? $product : null;
    }

    /**
     * Get product by SKU
     */
    public function getProductBySku(string $sku): ?\Mage_Catalog_Model_Product
    {
        $productId = \Mage::getModel('catalog/product')
            ->getIdBySku($sku);

        return $productId ? $this->getProductById((int) $productId) : null;
    }

    /**
     * Get product by barcode (uses configured barcode attribute)
     */
    public function getProductByBarcode(string $barcode): ?\Mage_Catalog_Model_Product
    {
        // Get configured barcode attribute code
        $barcodeAttributeCode = $this->getBarcodeAttributeCode();

        $product = \Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addAttributeToFilter($barcodeAttributeCode, $barcode)
            ->getFirstItem();

        /** @phpstan-ignore return.type */
        return $product->getId() ? $product : null;
    }

    /**
     * Search products (uses Meilisearch if available, falls back to MySQL)
     *
     * @param string $query Search query
     * @param int $page Page number
     * @param int $pageSize Items per page
     * @param array $filters Additional filters
     * @param array|null $sort Sorting options
     * @param bool $usePosIndex Use POS index (includes disabled/out of stock)
     * @param int|null $storeId Filter by store ID
     * @param array<string, string> $attributeFilters EAV attribute filters (code => value)
     */
    public function searchProducts(
        string $query = '',
        int $page = 1,
        int $pageSize = 20,
        array $filters = [],
        ?array $sort = null,
        bool $usePosIndex = false,
        ?int $storeId = null,
        array $attributeFilters = [],
    ): array {
        // If filtering by category and no explicit sort, use category's default sort order
        if (!$sort && isset($filters['categoryId'])) {
            $sort = $this->getCategoryDefaultSort((int) $filters['categoryId']);
        }

        // Position-based sorting: use category-specific position field in Meilisearch
        // Field format: cat_position_{categoryId} (indexed by Meilisearch indexer)
        $sortByPosition = $sort && strtolower($sort['field']) === 'position';
        if ($sortByPosition && isset($filters['categoryId'])) {
            // Use the category-specific position field
            $sort['field'] = 'cat_position_' . (int) $filters['categoryId'];
        }

        // Use Meilisearch for all product queries when available (much faster than MySQL)
        if ($this->useMeilisearch) {
            $results = $this->searchWithMeilisearch($query, $page, $pageSize, $filters, $sort, $usePosIndex, $storeId, $attributeFilters);

            // Fallback to MySQL if no results - handles cases where:
            // 1. POS needs to find products by GTIN (even if disabled/out of stock)
            // 2. Category filtering when category_ids isn't fully indexed in Meilisearch
            $shouldFallback = empty($results['products']) && (
                ($usePosIndex && !empty($query)) ||
                isset($filters['categoryId'])
            );

            if ($shouldFallback) {
                $results = $this->searchWithMysql($query, $page, $pageSize, $filters, $sort, $usePosIndex, $attributeFilters);
            }

            return $results;
        }

        return $this->searchWithMysql($query, $page, $pageSize, $filters, $sort, $usePosIndex, $attributeFilters);
    }

    /**
     * Search products using Meilisearch (fast, typo-tolerant)
     */
    /**
     * @param array<string, string> $attributeFilters
     */
    private function searchWithMeilisearch(
        string $query,
        int $page,
        int $pageSize,
        array $filters,
        ?array $sort,
        bool $usePosIndex = false,
        ?int $storeId = null,
        array $attributeFilters = [],
    ): array {
        $searchParams = [
            'limit' => $pageSize,
            'offset' => ($page - 1) * $pageSize,
            'attributesToRetrieve' => ['*'], // Get all attributes from Meilisearch
        ];

        // Build filter string
        $filterStrings = [];

        // Filter by store ID if specified
        // Note: store_id filtering temporarily disabled as the index doesn't have this as filterable
        // TODO: Add store_id to filterable attributes when creating POS-specific index
        // if ($storeId !== null) {
        //     $filterStrings[] = "store_id = {$storeId}";
        // }

        if (isset($filters['status']) && $filters['status'] === 'ENABLED') {
            $filterStrings[] = 'status = enabled';
        }

        if (isset($filters['stockStatus']) && $filters['stockStatus'] !== 'OUT_OF_STOCK') {
            $filterStrings[] = 'stock_status != out_of_stock';
        }

        if (isset($filters['categoryId'])) {
            // Get category and all its descendants for inclusive filtering
            $categoryIds = $this->getCategoryAndDescendantIds((int) $filters['categoryId']);
            if (count($categoryIds) === 1) {
                $filterStrings[] = "category_ids = {$categoryIds[0]}";
            } else {
                // OR logic: product must be in any of these categories
                $catFilter = array_map(fn($id) => "category_ids = {$id}", $categoryIds);
                $filterStrings[] = '(' . implode(' OR ', $catFilter) . ')';
            }
        }

        // Use currency-specific price field for filtering (e.g., price.AUD.default, price.NZD.default)
        $currencyCode = \Mage::app()->getStore()->getCurrentCurrencyCode();
        $priceField = "price.{$currencyCode}.default";

        if (isset($filters['priceFrom'])) {
            $priceFrom = (float) $filters['priceFrom'];
            $filterStrings[] = "{$priceField} >= {$priceFrom}";
        }

        if (isset($filters['priceTo'])) {
            $priceTo = (float) $filters['priceTo'];
            $filterStrings[] = "{$priceField} <= {$priceTo}";
        }

        // Attribute filters (e.g., brand_id=10, series=1877)
        foreach ($attributeFilters as $code => $value) {
            if (preg_match('/^[a-z][a-z0-9_]*$/', $code)) {
                $filterStrings[] = "{$code} = {$value}";
            }
        }

        if (!empty($filterStrings)) {
            $searchParams['filter'] = implode(' AND ', $filterStrings);
        }

        // Add sorting - use currency-specific price field when sorting by price
        if ($sort) {
            $direction = $sort['direction'] === 'DESC' ? ':desc' : ':asc';
            $sortField = strtolower($sort['field']);
            if ($sortField === 'price') {
                $sortField = $priceField;
            }
            $searchParams['sort'] = [$sortField . $direction];
        }

        // Use POS index or regular products index
        // Fallback to regular index if POS index doesn't exist yet
        if ($usePosIndex) {
            $indexName = $this->getIndexName('pos');
            try {
                /** @phpstan-ignore-next-line */
                $index = $this->meilisearchClient->index($indexName);
                // Test if index exists by getting stats
                $index->stats();
            } catch (\Exception $e) {
                // POS index doesn't exist, fall back to regular products index
                $indexName = $this->getIndexName('products');
                /** @phpstan-ignore-next-line */
                $index = $this->meilisearchClient->index($indexName);
            }
        } else {
            $indexName = $this->getIndexName('products');
            /** @phpstan-ignore-next-line */
            $index = $this->meilisearchClient->index($indexName);
        }

        $results = $index->search($query, $searchParams);

        // Convert Meilisearch hits to product data (no DB loading needed)
        $products = [];
        foreach ($results->getHits() as $hit) {
            $products[] = $this->convertMeilisearchHitToProductData($hit);
        }

        $total = $results->getEstimatedTotalHits() ?? $results->getTotalHits() ?? 0;

        return [
            'products' => $products,
            'total' => $total,
            'processingTimeMs' => $results->getProcessingTimeMs(),
        ];
    }

    /**
     * Convert Meilisearch hit to product data array (used by GraphQL resolver)
     */
    private function convertMeilisearchHitToProductData(array $hit): array
    {
        // Extract price from currency structure (e.g., {"AUD": {"default": 12.95}})
        $price = 0.0;
        if (isset($hit['price']) && is_array($hit['price'])) {
            // Get current store's currency code
            $currencyCode = \Mage::app()->getStore()->getCurrentCurrencyCode();
            $price = $hit['price'][$currencyCode]['default'] ?? 0.0;
        } elseif (isset($hit['price'])) {
            $price = (float) $hit['price'];
        }

        // Handle SKU - for configurable products, it's an array of variant SKUs
        $sku = $hit['sku'] ?? '';
        if (is_array($sku)) {
            $sku = $sku[0] ?? ''; // Use first SKU (parent product SKU)
        }

        // Get configured barcode attribute code
        $barcodeAttributeCode = $this->getBarcodeAttributeCode();

        return [
            'id' => $hit['objectID'] ?? $hit['id'] ?? null,
            'sku' => $sku,
            'name' => $hit['name'] ?? '',
            'description' => $hit['description'] ?? '',
            'type' => $hit['type_id'] ?? $hit['type'] ?? \Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, // Algolia uses 'type_id', custom index uses 'type'
            'price' => $price,
            'special_price' => null, // TODO: Extract from price structure if special price exists
            'final_price' => $price,  // Using regular price for now
            'status' => ($hit['in_stock'] ?? 0) ? 'enabled' : 'disabled',
            'stock_status' => ($hit['in_stock'] ?? 0) ? 'in_stock' : 'out_of_stock',
            'stock_qty' => $hit['stock_qty'] ?? 0,
            'categories' => $hit['categories'] ?? [],
            $barcodeAttributeCode => $hit[$barcodeAttributeCode] ?? null, // Dynamic barcode attribute
            'image_url' => $hit['image_url'] ?? null,
            'small_image_url' => $hit['small_image_url'] ?? null,
            'thumbnail_url' => $hit['thumbnail_url'] ?? null,
            'created_at' => $hit['created_at'] ?? null,
            'updated_at' => $hit['updated_at'] ?? null,
            'url_key' => $hit['url_key'] ?? null,
        ];
    }

    /**
     * Search products using MySQL (fallback when Meilisearch unavailable)
     */
    /**
     * Attributes needed for API product listings
     * Only load what's actually used to minimize EAV joins
     */
    private const LISTING_ATTRIBUTES = [
        'name',
        'sku',
        'url_key',
        'description',
        'short_description',
        'price',
        'special_price',
        'status',
        'visibility',
        'image',
        'small_image',
        'thumbnail',
        'required_options',
        'weight',
    ];

    /**
     * Search products using MySQL (fallback when Meilisearch unavailable)
     */
    /**
     * @param array<string, string> $attributeFilters
     */
    private function searchWithMysql(
        string $query,
        int $page,
        int $pageSize,
        array $filters,
        ?array $sort,
        bool $includeDisabled = false,
        array $attributeFilters = [],
    ): array {
        // Get barcode attribute to include in selection
        $barcodeAttr = $this->getBarcodeAttributeCode();
        $attributes = self::LISTING_ATTRIBUTES;
        if ($barcodeAttr && $barcodeAttr !== 'sku') {
            $attributes[] = $barcodeAttr;
        }

        $collection = \Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect($attributes)
            ->addPriceData()
            ->addStoreFilter();

        // Apply search query - search across core text attributes
        // Uses contains (%query%) for better results than starts-with
        if (!empty($query)) {
            $collection->addAttributeToFilter([
                ['attribute' => 'name', 'like' => "%{$query}%"],
                ['attribute' => 'sku', 'like' => "%{$query}%"],
                ['attribute' => 'description', 'like' => "%{$query}%"],
                ['attribute' => 'short_description', 'like' => "%{$query}%"],
            ]);
        }

        // Apply filters
        // For POS fallback search, skip status filter to find disabled products
        if (isset($filters['status']) && !$includeDisabled) {
            $status = $filters['status'] === 'ENABLED'
                ? \Mage_Catalog_Model_Product_Status::STATUS_ENABLED
                : \Mage_Catalog_Model_Product_Status::STATUS_DISABLED;
            $collection->addAttributeToFilter('status', $status);
        }

        // Filter by visibility - exclude "Not Visible Individually" products (configurable children)
        // unless explicitly requesting all products (POS mode)
        if (!$includeDisabled) {
            // Include: Catalog (2), Search (3), Catalog,Search (4) - exclude: Not Visible Individually (1)
            $collection->addAttributeToFilter('visibility', ['neq' => \Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE]);
        }

        // For POS fallback search, skip stock status filter to find out-of-stock products
        if (isset($filters['stockStatus']) && !$includeDisabled) {
            $stockStatus = $filters['stockStatus'];
            if ($stockStatus !== 'OUT_OF_STOCK') {
                $collection->joinField(
                    'is_in_stock',
                    'cataloginventory/stock_item',
                    'is_in_stock',
                    'product_id=entity_id',
                    ['is_in_stock' => 1],
                );
            }
        }

        // Track if we're filtering by category for position-based sorting
        $categoryForPosition = null;
        if (isset($filters['categoryId'])) {
            $category = \Mage::getModel('catalog/category')->load($filters['categoryId']);
            $collection->addCategoryFilter($category);
            $categoryForPosition = $category;
        }

        if (isset($filters['priceFrom']) || isset($filters['priceTo'])) {
            $priceFilter = [];
            // Cast to string to avoid DB adapter type issues with floats
            if (isset($filters['priceFrom'])) {
                $priceFilter['from'] = (string) $filters['priceFrom'];
            }
            if (isset($filters['priceTo'])) {
                $priceFilter['to'] = (string) $filters['priceTo'];
            }
            $collection->addAttributeToFilter('price', $priceFilter);
        }

        // Apply SKU filter
        if (isset($filters['sku']['eq'])) {
            $collection->addAttributeToFilter('sku', $filters['sku']['eq']);
        } elseif (isset($filters['sku']['like'])) {
            $collection->addAttributeToFilter('sku', ['like' => "%{$filters['sku']['like']}%"]);
        }

        // Apply name filter
        if (isset($filters['name']['eq'])) {
            $collection->addAttributeToFilter('name', $filters['name']['eq']);
        } elseif (isset($filters['name']['like'])) {
            $collection->addAttributeToFilter('name', ['like' => "%{$filters['name']['like']}%"]);
        }

        // Apply EAV attribute filters via catalog_product_index_eav
        $storeId = (int) \Mage::app()->getStore()->getId();
        foreach ($attributeFilters as $code => $value) {
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $code)) {
                continue;
            }
            $attribute = \Mage::getSingleton('eav/config')
                ->getAttribute(\Mage_Catalog_Model_Product::ENTITY, $code);
            if (!$attribute || !$attribute->getId() || !$attribute->getIsFilterable()) {
                continue;
            }
            $alias = 'attr_filter_' . $code;
            $attrId = (int) $attribute->getId();
            $collection->getSelect()->join(
                [$alias => $collection->getTable('catalog/product_index_eav')],
                "{$alias}.entity_id = e.entity_id"
                    . " AND {$alias}.attribute_id = {$attrId}"
                    . " AND {$alias}.store_id = {$storeId}",
                [],
            );
            $collection->getSelect()->where("{$alias}.value = ?", $value);
        }

        // Apply sorting
        if ($sort) {
            $field = strtolower($sort['field']);
            $direction = $sort['direction'] === 'DESC' ? 'DESC' : 'ASC';

            // Position sorting uses category_product position
            if ($field === 'position' && $categoryForPosition) {
                // addCategoryFilter already joins cat_index which has position
                // We can use 'cat_index_position' for proper ordering
                $collection->getSelect()->order("cat_index.position {$direction}");
            } elseif ($field === 'price') {
                // Price sorting requires the price index for correct results
                $collection->addPriceData();
                $collection->getSelect()->order("price_index.price {$direction}");
            } else {
                $collection->setOrder($field, $direction);
            }
        } else {
            // Default sort by name
            $collection->setOrder('name', 'ASC');
        }

        // Get total count before pagination
        $total = $collection->getSize();

        // Apply pagination
        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        return [
            'products' => iterator_to_array($collection),
            'total' => $total,
            'processingTimeMs' => null,
        ];
    }

    /**
     * Index all products to Meilisearch
     *
     * @param int|null $storeId Store ID to index products for (null = all stores)
     * @param bool $posIndex Whether to create POS index (includes disabled/out of stock)
     * @return array Indexing statistics
     */
    public function indexAllProducts(?int $storeId = null, bool $posIndex = false): array
    {
        if (!$this->useMeilisearch) {
            throw new \RuntimeException('Meilisearch client not configured');
        }

        $indexName = $posIndex ? $this->getIndexName('pos') : $this->getIndexName('products');

        // Create index with explicit primary key to avoid ambiguity with 'store_id'
        /** @phpstan-ignore-next-line */
        $index = $this->meilisearchClient->index($indexName);
        try {
            $index->update(['primaryKey' => 'id']);
        } catch (\Exception $e) {
            // Index already exists, that's fine
        }

        // Configure index settings for optimal POS search
        $index->updateSettings([
            'searchableAttributes' => [
                'sku',
                'name',
                'barcode',
                'gtin',
                'description',
            ],
            'filterableAttributes' => [
                'status',
                'stock_status',
                'categories',
                'price',
                'store_id',
            ],
            'sortableAttributes' => [
                'name',
                'price',
                'created_at',
            ],
            'rankingRules' => [
                'words',
                'typo',
                'proximity',
                'attribute',
                'sort',
                'exactness',
            ],
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => [
                    'oneTypo' => 4,
                    'twoTypos' => 8,
                ],
            ],
        ]);

        // Get all products (for POS, include disabled and out of stock)
        $collection = \Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('*');

        // For non-POS index, filter to enabled and in-stock products only
        if (!$posIndex) {
            $collection->addAttributeToFilter('status', \Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        }

        // Filter by store if specified
        if ($storeId !== null) {
            $collection->addStoreFilter($storeId);
        }

        $documents = [];
        foreach ($collection as $product) {
            $doc = $this->convertProductToMeilisearchDocument($product);
            $doc['store_id'] = $storeId ?? 0;
            $documents[] = $doc;

            // Batch index every 1000 products
            if (count($documents) >= 1000) {
                $index->addDocuments($documents);
                $documents = [];
            }
        }

        // Index remaining products
        if (!empty($documents)) {
            $index->addDocuments($documents);
        }

        return [
            'indexed' => $collection->getSize(),
            'index' => $indexName,
            'store_id' => $storeId,
        ];
    }

    /**
     * Convert Maho product to Meilisearch document
     */
    private function convertProductToMeilisearchDocument(\Mage_Catalog_Model_Product $product): array
    {
        $stockItem = $product->getStockItem();
        $stockStatus = 'out_of_stock';
        if ($stockItem && $stockItem->getIsInStock()) {
            $stockStatus = $stockItem->getBackorders() > 0 ? 'backorder' : 'in_stock';
        }

        // Get image URL
        $imageUrl = null;
        $thumbnailUrl = null;
        $mediaConfig = \Mage::getModel('catalog/product_media_config');

        if ($product->getImage() && $product->getImage() !== 'no_selection') {
            $imageUrl = $mediaConfig->getMediaUrl($product->getImage());
        }

        if ($product->getThumbnail() && $product->getThumbnail() !== 'no_selection') {
            $thumbnailUrl = $mediaConfig->getMediaUrl($product->getThumbnail());
        }

        // Get configured barcode attribute
        $barcodeAttributeCode = $this->getBarcodeAttributeCode();
        $barcodeValue = $product->getData($barcodeAttributeCode);

        return [
            'id' => $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'description' => strip_tags($product->getDescription() ?? ''),
            'type' => $product->getTypeId(), // Product type (simple, configurable, grouped, etc.)
            'price' => (float) $product->getPrice(),
            'special_price' => $product->getSpecialPrice() ? (float) $product->getSpecialPrice() : null,
            'final_price' => (float) $product->getFinalPrice(),
            'status' => $product->getStatus() == \Mage_Catalog_Model_Product_Status::STATUS_ENABLED ? 'enabled' : 'disabled',
            'stock_status' => $stockStatus,
            'stock_qty' => $stockItem ? (float) $stockItem->getQty() : 0,
            'categories' => $product->getCategoryIds(),
            $barcodeAttributeCode => $barcodeValue, // Dynamic barcode attribute
            'image_url' => $imageUrl,
            'thumbnail_url' => $thumbnailUrl,
            'created_at' => strtotime($product->getCreatedAt()),
            'updated_at' => strtotime($product->getUpdatedAt()),
        ];
    }
}
