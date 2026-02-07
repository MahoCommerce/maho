<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\Pagination\ArrayPaginator;
use Maho\ApiPlatform\Service\ProductService;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\ApiResource\Product;

/**
 * Product State Provider - Fetches product data for API Platform
 *
 * @implements ProviderInterface<Product>
 */
final class ProductProvider implements ProviderInterface
{
    private ?ProductService $productService = null;
    private ?\Mage_Catalog_Model_Product_Media_Config $mediaConfig = null;

    /**
     * Get cached MediaConfig instance
     */
    private function getMediaConfig(): \Mage_Catalog_Model_Product_Media_Config
    {
        if ($this->mediaConfig === null) {
            $this->mediaConfig = \Mage::getModel('catalog/product_media_config');
        }
        return $this->mediaConfig;
    }

    /**
     * Get or initialize ProductService (lazy initialization after store context is set)
     */
    private function getProductService(): ProductService
    {
        if ($this->productService !== null) {
            return $this->productService;
        }

        // Try to use Meilisearch for better performance (via Meilisearch extension config)
        $meilisearchClient = null;
        $indexBaseName = null;

        try {
            // Check if Meilisearch module is installed and enabled
            if (\Mage::helper('core')->isModuleEnabled('Meilisearch_Search')) {
                /** @var \Meilisearch_Search_Helper_Config $configHelper */
                /** @phpstan-ignore-next-line */
                $configHelper = \Mage::helper('meilisearch_search/config');

                /** @phpstan-ignore-next-line */
                $host = $configHelper->getServerUrl();
                /** @phpstan-ignore-next-line */
                $apiKey = $configHelper->getAPIKey();
                /** @phpstan-ignore-next-line */
                $indexPrefix = rtrim($configHelper->getIndexPrefix() ?: 'maho', '_');

                if ($host && $apiKey) {
                    /** @phpstan-ignore-next-line */
                    $meilisearchClient = new \Meilisearch\Client($host, $apiKey);
                    $storeCode = StoreContext::getStoreCode() ?: 'default';
                    $indexBaseName = $indexPrefix . '_' . $storeCode;
                }
            }
        } catch (\Throwable $e) {
            // Fall back to MySQL if Meilisearch fails or extension not installed
            \Mage::log('Meilisearch init failed: ' . $e->getMessage(), \Mage::LOG_WARNING);
        }

        /** @phpstan-ignore-next-line */
        $this->productService = new ProductService($meilisearchClient, $indexBaseName);
        return $this->productService;
    }

    /**
     * Provide product data based on operation type
     *
     * @return ArrayPaginator<Product>|Product|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ArrayPaginator|Product|null
    {
        // Ensure valid store context (MUST happen before getProductService)
        StoreContext::ensureStore();

        // Handle custom GraphQL queries
        $operationName = $operation->getName();

        if ($operationName === 'productBySku') {
            $sku = $context['args']['sku'] ?? null;
            return $sku ? $this->getProductBySku($sku) : null;
        }

        if ($operationName === 'productByBarcode') {
            $barcode = $context['args']['barcode'] ?? null;
            return $barcode ? $this->getProductByBarcode($barcode) : null;
        }

        if ($operationName === 'categoryProducts') {
            $categoryId = $context['args']['categoryId'] ?? null;
            if ($categoryId === null) {
                return new ArrayPaginator(items: [], currentPage: 1, itemsPerPage: 20, totalItems: 0);
            }
            // Inject categoryId into filters and delegate to getCollection
            $context['args'] = array_merge($context['args'] ?? [], ['categoryId' => (int) $categoryId]);
            return $this->getCollection($context);
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->getCollection($context);
        }

        return $this->getItem((int) $uriVariables['id']);
    }

    /**
     * Get a single product by ID
     */
    private function getItem(int $id): ?Product
    {
        $mahoProduct = $this->getProductService()->getProductById($id);
        return $mahoProduct ? $this->mapToDto($mahoProduct) : null;
    }

    /**
     * Get a product by SKU
     */
    private function getProductBySku(string $sku): ?Product
    {
        $mahoProduct = $this->getProductService()->getProductBySku($sku);
        return $mahoProduct ? $this->mapToDto($mahoProduct) : null;
    }

    /**
     * Get a product by barcode
     */
    private function getProductByBarcode(string $barcode): ?Product
    {
        $mahoProduct = $this->getProductService()->getProductByBarcode($barcode);
        return $mahoProduct ? $this->mapToDto($mahoProduct) : null;
    }

    /**
     * Cache TTL for product listings (in seconds)
     */
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Generate cache key for product collection
     */
    private function getCollectionCacheKey(array $filters): string
    {
        $keyData = array_filter($filters, fn($v) => $v !== '' && $v !== null);
        ksort($keyData);
        return 'api_products_' . md5(json_encode($keyData) . '_' . StoreContext::getStoreId());
    }

    /**
     * Get product collection with pagination and search
     *
     * @return ArrayPaginator<Product>
     */
    private function getCollection(array $context): ArrayPaginator
    {
        // Merge REST filters and GraphQL args (GraphQL args take precedence)
        $requestFilters = array_merge($context['filters'] ?? [], $context['args'] ?? []);
        $page = (int) ($requestFilters['page'] ?? 1);
        $pageSize = min((int) ($requestFilters['itemsPerPage'] ?? $requestFilters['pageSize'] ?? 20), 100);
        // Support both 'search' and 'q' parameters for compatibility
        $search = $requestFilters['search'] ?? $requestFilters['q'] ?? '';

        // Try cache first for non-search queries (search results change frequently)
        $cacheKey = null;
        if (empty($search)) {
            $cacheKey = $this->getCollectionCacheKey($requestFilters);
            $cached = \Mage::app()->getCache()->load($cacheKey);
            if ($cached !== false) {
                $cachedData = json_decode($cached, true);
                if ($cachedData !== null) {
                    // Reconstruct Product DTOs from cached data
                    $products = array_map(fn($data) => $this->arrayToProductDto($data), $cachedData['products']);
                    return new ArrayPaginator(
                        items: $products,
                        currentPage: $cachedData['page'],
                        itemsPerPage: $cachedData['pageSize'],
                        totalItems: $cachedData['total'],
                    );
                }
            }
        }

        // Build filters for ProductService
        $serviceFilters = [];
        if (!empty($requestFilters['categoryId'])) {
            $serviceFilters['categoryId'] = (int) $requestFilters['categoryId'];
        }
        // Map priceMin/priceMax to priceFrom/priceTo for ProductService
        if (!empty($requestFilters['priceMin'])) {
            $serviceFilters['priceFrom'] = (float) $requestFilters['priceMin'];
        }
        if (!empty($requestFilters['priceMax'])) {
            $serviceFilters['priceTo'] = (float) $requestFilters['priceMax'];
        }

        // Build sort
        $sort = null;
        if (!empty($requestFilters['sortBy'])) {
            $sortDir = ($requestFilters['sortDir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
            $sort = [
                'field' => $requestFilters['sortBy'],
                'direction' => $sortDir,
            ];
        }

        $result = $this->getProductService()->searchProducts(
            query: $search,
            page: $page,
            pageSize: $pageSize,
            filters: $serviceFilters,
            sort: $sort,
            usePosIndex: false,
            storeId: StoreContext::getStoreId(),
        );

        // Collect product IDs for batch operations
        $productIds = [];
        $mahoProducts = [];
        foreach ($result['products'] as $product) {
            if ($product instanceof \Mage_Catalog_Model_Product) {
                $productIds[] = (int) $product->getId();
                $mahoProducts[] = $product;
            }
        }

        // Batch load review summaries to avoid N+1 queries
        $reviewSummaries = $this->batchLoadReviewSummaries($productIds);

        // Batch load category IDs to avoid N+1 queries
        $categoryIdsByProduct = $this->batchLoadCategoryIds($productIds);

        // Batch load stock items to avoid N+1 queries
        $stockItemsByProduct = $this->batchLoadStockItems($productIds);

        $products = [];
        foreach ($result['products'] as $product) {
            if ($product instanceof \Mage_Catalog_Model_Product) {
                $productId = (int) $product->getId();
                // For listings, skip expensive operations (custom options, variants)
                $products[] = $this->mapToDto(
                    $product,
                    forListing: true,
                    reviewSummary: $reviewSummaries[$productId] ?? null,
                    categoryIds: $categoryIdsByProduct[$productId] ?? [],
                    stockItem: $stockItemsByProduct[$productId] ?? null,
                );
            } elseif (is_array($product)) {
                $products[] = $this->mapArrayToDto($product);
            }
        }

        // Cache the results for non-search queries
        if ($cacheKey !== null && !empty($products)) {
            $cacheData = [
                'products' => array_map(fn($dto) => $this->productDtoToArray($dto), $products),
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => (int) $result['total'],
            ];
            \Mage::app()->getCache()->save(
                json_encode($cacheData),
                $cacheKey,
                ['API_PRODUCTS'],
                self::CACHE_TTL,
            );
        }

        // Return paginator with total count for proper pagination
        return new ArrayPaginator(
            items: $products,
            currentPage: $page,
            itemsPerPage: $pageSize,
            totalItems: (int) $result['total'],
        );
    }

    /**
     * Convert Product DTO to array for caching
     */
    private function productDtoToArray(Product $dto): array
    {
        return [
            'id' => $dto->id,
            'sku' => $dto->sku,
            'name' => $dto->name,
            'description' => $dto->description,
            'shortDescription' => $dto->shortDescription,
            'type' => $dto->type,
            'status' => $dto->status,
            'visibility' => $dto->visibility,
            'stockStatus' => $dto->stockStatus,
            'price' => $dto->price,
            'specialPrice' => $dto->specialPrice,
            'finalPrice' => $dto->finalPrice,
            'stockQty' => $dto->stockQty,
            'weight' => $dto->weight,
            'barcode' => $dto->barcode,
            'imageUrl' => $dto->imageUrl,
            'smallImageUrl' => $dto->smallImageUrl,
            'thumbnailUrl' => $dto->thumbnailUrl,
            'categoryIds' => $dto->categoryIds,
            'createdAt' => $dto->createdAt,
            'updatedAt' => $dto->updatedAt,
            'hasRequiredOptions' => $dto->hasRequiredOptions,
            'reviewCount' => $dto->reviewCount,
            'averageRating' => $dto->averageRating,
        ];
    }

    /**
     * Reconstruct Product DTO from cached array
     */
    private function arrayToProductDto(array $data): Product
    {
        $dto = new Product();
        $dto->id = $data['id'] ?? null;
        $dto->sku = $data['sku'] ?? '';
        $dto->name = $data['name'] ?? '';
        $dto->description = $data['description'] ?? null;
        $dto->shortDescription = $data['shortDescription'] ?? null;
        $dto->type = $data['type'] ?? 'simple';
        $dto->status = $data['status'] ?? 'enabled';
        $dto->visibility = $data['visibility'] ?? 'catalog_search';
        $dto->stockStatus = $data['stockStatus'] ?? 'in_stock';
        $dto->price = $data['price'] ?? null;
        $dto->specialPrice = $data['specialPrice'] ?? null;
        $dto->finalPrice = $data['finalPrice'] ?? null;
        $dto->stockQty = $data['stockQty'] ?? null;
        $dto->weight = $data['weight'] ?? null;
        $dto->barcode = $data['barcode'] ?? null;
        $dto->imageUrl = $data['imageUrl'] ?? null;
        $dto->smallImageUrl = $data['smallImageUrl'] ?? null;
        $dto->thumbnailUrl = $data['thumbnailUrl'] ?? null;
        $dto->categoryIds = $data['categoryIds'] ?? [];
        $dto->createdAt = $data['createdAt'] ?? null;
        $dto->updatedAt = $data['updatedAt'] ?? null;
        $dto->hasRequiredOptions = $data['hasRequiredOptions'] ?? false;
        $dto->reviewCount = $data['reviewCount'] ?? 0;
        $dto->averageRating = $data['averageRating'] ?? null;
        return $dto;
    }

    /**
     * Map Maho product model to Product DTO
     *
     * @param \Mage_Catalog_Model_Product $product
     * @param bool $forListing Skip expensive operations for listings (custom options, variants)
     * @param array|null $reviewSummary Pre-loaded review summary data (for batch loading)
     * @param int[]|null $categoryIds Pre-loaded category IDs (for batch loading)
     * @param \Mage_CatalogInventory_Model_Stock_Item|null $stockItem Pre-loaded stock item (for batch loading)
     */
    private function mapToDto(
        \Mage_Catalog_Model_Product $product,
        bool $forListing = false,
        ?array $reviewSummary = null,
        ?array $categoryIds = null,
        ?\Mage_CatalogInventory_Model_Stock_Item $stockItem = null,
    ): Product {
        $dto = new Product();
        $dto->id = (int) $product->getId();
        $dto->sku = $product->getSku() ?? '';
        $dto->name = $product->getName() ?? '';
        $dto->description = $product->getDescription();
        $dto->shortDescription = $product->getShortDescription();
        $dto->type = $product->getTypeId();
        $dto->status = $product->getStatus() == 1 ? 'enabled' : 'disabled';
        $dto->visibility = match ((int) $product->getVisibility()) {
            \Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE => 'not_visible',
            \Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG => 'catalog',
            \Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH => 'search',
            default => 'catalog_search',
        };
        $dto->price = $product->getPrice() ? (float) $product->getPrice() : null;
        $dto->specialPrice = $product->getSpecialPrice() ? (float) $product->getSpecialPrice() : null;

        // getFinalPrice() can fail for configurable products without quote context
        try {
            $dto->finalPrice = $product->getFinalPrice() ? (float) $product->getFinalPrice() : null;
        } catch (\Throwable $e) {
            // Fallback: use special price if set, otherwise base price
            $dto->finalPrice = $dto->specialPrice ?? $dto->price;
        }

        // Get stock information - use pre-loaded stock item if available (batch loading)
        $stockData = $stockItem ?? $product->getStockItem();
        if ($stockData) {
            $dto->stockQty = (float) $stockData->getQty();
            $dto->stockStatus = $stockData->getIsInStock() ? 'in_stock' : 'out_of_stock';
        }

        $dto->weight = $product->getWeight() ? (float) $product->getWeight() : null;

        // Get barcode from configured attribute (if POS module available)
        /** @phpstan-ignore-next-line */
        $posHelper = \Mage::helper('maho_pos');
        $barcodeAttr = ($posHelper && method_exists($posHelper, 'getBarcodeAttributeCode'))
            ? $posHelper->getBarcodeAttributeCode()
            : 'barcode';
        $dto->barcode = $product->getData($barcodeAttr);

        // Use pre-loaded category IDs if available (batch loading), otherwise load individually
        $dto->categoryIds = $categoryIds ?? ($product->getCategoryIds() ?: []);
        $dto->createdAt = $product->getCreatedAt();
        $dto->updatedAt = $product->getUpdatedAt();

        // Get image URLs (use cached MediaConfig)
        $mediaConfig = $this->getMediaConfig();
        if ($product->getImage() && $product->getImage() !== 'no_selection') {
            $dto->imageUrl = $mediaConfig->getMediaUrl($product->getImage());
        }
        if ($product->getSmallImage() && $product->getSmallImage() !== 'no_selection') {
            $dto->smallImageUrl = $mediaConfig->getMediaUrl($product->getSmallImage());
        }
        if ($product->getThumbnail() && $product->getThumbnail() !== 'no_selection') {
            $dto->thumbnailUrl = $mediaConfig->getMediaUrl($product->getThumbnail());
        }

        // Get review summary - use pre-loaded data if available (batch loading)
        if ($reviewSummary !== null) {
            if ($reviewSummary['reviews_count'] > 0) {
                $dto->reviewCount = $reviewSummary['reviews_count'];
                $dto->averageRating = $reviewSummary['rating_summary']
                    ? round((float) $reviewSummary['rating_summary'] / 20, 1) // Convert 0-100 to 0-5 scale
                    : null;
            }
        } else {
            // Fallback for single product loads (not batch)
            $reviewSummaryModel = \Mage::getModel('review/review_summary')
                ->setStoreId(StoreContext::getStoreId())
                ->load($product->getId());
            if ($reviewSummaryModel->getReviewsCount()) {
                $dto->reviewCount = (int) $reviewSummaryModel->getReviewsCount();
                $dto->averageRating = $reviewSummaryModel->getRatingSummary()
                    ? round((float) $reviewSummaryModel->getRatingSummary() / 20, 1)
                    : null;
            }
        }

        // For listings, we only need basic info + flags for the grid
        // hasRequiredOptions flag is cheap to get from product attribute
        $dto->hasRequiredOptions = (bool) $product->getRequiredOptions();

        // Expensive operations: only load for single product detail views
        if (!$forListing) {
            // Get configurable product options and variants
            if ($product->getTypeId() === \Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
                $dto->configurableOptions = $this->getConfigurableOptions($product);
                $dto->variants = $this->getConfigurableVariants($product);
            }

            // Get custom options (e.g., String, Tension, Cover for tennis racquets)
            $dto->customOptions = $this->getCustomOptions($product);

            // Media gallery images
            $dto->mediaGallery = $this->getMediaGallery($product);

            // Linked products (related, cross-sell, up-sell)
            $dto->relatedProducts = $this->getLinkedProducts($product->getRelatedProductCollection());
            $dto->crosssellProducts = $this->getLinkedProducts($product->getCrossSellProductCollection());
            $dto->upsellProducts = $this->getLinkedProducts($product->getUpSellProductCollection());

            // Downloadable links
            if ($product->getTypeId() === 'downloadable') {
                $dto->downloadableLinks = $this->getDownloadableLinks($product);
                $dto->linksTitle = $product->getData('links_title')
                    ?: \Mage::getStoreConfig('catalog/downloadable/links_title')
                    ?: 'Links';
                $dto->linksPurchasedSeparately = (bool) $product->getData('links_purchased_separately');
            }
        }

        return $dto;
    }

    /**
     * Get configurable attributes with their available values
     */
    private function getConfigurableOptions(\Mage_Catalog_Model_Product $product): array
    {
        $options = [];
        $typeInstance = $product->getTypeInstance(true);
        /** @phpstan-ignore-next-line */
        $configurableAttributes = $typeInstance->getConfigurableAttributes($product);

        foreach ($configurableAttributes as $attribute) {
            $productAttribute = $attribute->getProductAttribute();
            $attributeCode = $productAttribute->getAttributeCode();

            // Get all available values from child products
            $values = [];
            $usedValues = [];
            /** @phpstan-ignore-next-line */
            $childProducts = $typeInstance->getUsedProducts(null, $product);

            foreach ($childProducts as $child) {
                $valueId = $child->getData($attributeCode);
                if ($valueId && !isset($usedValues[$valueId])) {
                    $usedValues[$valueId] = true;
                    $label = $productAttribute->getSource()->getOptionText($valueId);
                    $values[] = [
                        'id' => (int) $valueId,
                        'label' => $label ?: $valueId,
                    ];
                }
            }

            $options[] = [
                'id' => (int) $attribute->getAttributeId(),
                'code' => $attributeCode,
                'label' => $productAttribute->getStoreLabel() ?: $productAttribute->getFrontendLabel(),
                'values' => $values,
            ];
        }

        return $options;
    }

    /**
     * Get configurable child products with their attribute values
     */
    private function getConfigurableVariants(\Mage_Catalog_Model_Product $product): array
    {
        $variants = [];
        $typeInstance = $product->getTypeInstance(true);
        /** @phpstan-ignore-next-line */
        $configurableAttributes = $typeInstance->getConfigurableAttributes($product);
        /** @phpstan-ignore-next-line */
        $childProducts = $typeInstance->getUsedProducts(null, $product);
        $mediaConfig = $this->getMediaConfig();
        /** @phpstan-ignore-next-line */
        $posHelper = \Mage::helper('maho_pos');
        $barcodeAttr = ($posHelper && method_exists($posHelper, 'getBarcodeAttributeCode'))
            ? $posHelper->getBarcodeAttributeCode()
            : 'barcode';

        // Batch load all stock items at once to avoid N+1 queries
        $childIds = [];
        foreach ($childProducts as $child) {
            $childIds[] = $child->getId();
        }
        $stockByProduct = $this->batchLoadStockItems($childIds);

        foreach ($childProducts as $child) {
            // Get attribute values for this variant
            $attributes = [];
            foreach ($configurableAttributes as $attribute) {
                $attributeCode = $attribute->getProductAttribute()->getAttributeCode();
                $attributes[$attributeCode] = (int) $child->getData($attributeCode);
            }

            // Get stock info from batch-loaded data
            $productId = $child->getId();
            $stockItem = $stockByProduct[$productId] ?? null;
            $stockQty = $stockItem ? (float) $stockItem->getQty() : 0;
            $inStock = $stockItem ? (bool) $stockItem->getIsInStock() : false;

            $variant = [
                'id' => (int) $child->getId(),
                'sku' => $child->getSku(),
                'price' => (float) $child->getPrice(),
                'finalPrice' => (float) $child->getFinalPrice(),
                'stockQty' => $stockQty,
                'inStock' => $inStock,
                'attributes' => $attributes,
            ];

            // Add barcode if available
            if ($barcodeAttr && $child->getData($barcodeAttr)) {
                $variant['barcode'] = (string) $child->getData($barcodeAttr);
            }

            // Add image if different from parent
            if ($child->getImage() && $child->getImage() !== 'no_selection') {
                $variant['imageUrl'] = $mediaConfig->getMediaUrl($child->getImage());
            }

            $variants[] = $variant;
        }

        return $variants;
    }

    /**
     * Batch load stock items for multiple product IDs
     *
     * @param int[] $productIds
     * @return array<int, \Mage_CatalogInventory_Model_Stock_Item>
     */
    private function batchLoadStockItems(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $collection = \Mage::getResourceModel('cataloginventory/stock_item_collection');
        $collection->addFieldToFilter('product_id', ['in' => $productIds]);

        $stockByProduct = [];
        foreach ($collection as $stockItem) {
            $stockByProduct[$stockItem->getProductId()] = $stockItem;
        }

        return $stockByProduct;
    }

    /**
     * Batch load review summaries for multiple product IDs
     *
     * @param int[] $productIds
     * @return array<int, array{reviews_count: int, rating_summary: float|null}>
     */
    private function batchLoadReviewSummaries(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $storeId = StoreContext::getStoreId();
        $adapter = \Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = \Mage::getSingleton('core/resource')->getTableName('review/review_aggregate');

        $select = $adapter->select()
            ->from($tableName, ['entity_pk_value', 'reviews_count', 'rating_summary'])
            ->where('entity_pk_value IN (?)', $productIds)
            ->where('store_id = ?', $storeId);

        $result = [];
        foreach ($adapter->fetchAll($select) as $row) {
            $result[(int) $row['entity_pk_value']] = [
                'reviews_count' => (int) $row['reviews_count'],
                'rating_summary' => $row['rating_summary'] ? (float) $row['rating_summary'] : null,
            ];
        }

        return $result;
    }

    /**
     * Batch load category IDs for multiple product IDs
     *
     * @param int[] $productIds
     * @return array<int, int[]>
     */
    private function batchLoadCategoryIds(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $adapter = \Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = \Mage::getSingleton('core/resource')->getTableName('catalog/category_product');

        $select = $adapter->select()
            ->from($tableName, ['product_id', 'category_id'])
            ->where('product_id IN (?)', $productIds);

        $result = [];
        foreach ($adapter->fetchAll($select) as $row) {
            $productId = (int) $row['product_id'];
            if (!isset($result[$productId])) {
                $result[$productId] = [];
            }
            $result[$productId][] = (int) $row['category_id'];
        }

        return $result;
    }

    /**
     * Get media gallery images for a product
     *
     * @return array<array{url: string, label: string|null, position: int}>
     */
    private function getMediaGallery(\Mage_Catalog_Model_Product $product): array
    {
        $gallery = [];
        $mediaConfig = $this->getMediaConfig();

        $images = $product->getMediaGalleryImages();
        if ($images) {
            foreach ($images as $image) {
                $gallery[] = [
                    'url' => $mediaConfig->getMediaUrl($image->getFile()),
                    'label' => $image->getLabel() ?: null,
                    'position' => (int) $image->getPosition(),
                ];
            }
        }

        return $gallery;
    }

    /**
     * Get linked products (related, cross-sell, up-sell) as lightweight DTOs
     *
     * @return Product[]
     */
    private function getLinkedProducts(\Mage_Catalog_Model_Resource_Product_Collection $collection): array
    {
        $collection->addAttributeToSelect(['name', 'price', 'special_price', 'small_image', 'status', 'visibility'])
            ->addFieldToFilter('status', \Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setPageSize(20);

        $products = [];
        foreach ($collection as $product) {
            $products[] = $this->mapToDto($product, forListing: true);
        }

        return $products;
    }

    /**
     * Get custom product options (e.g., String, Tension, Cover)
     */
    private function getCustomOptions(\Mage_Catalog_Model_Product $product): array
    {
        $customOptions = [];

        foreach ($product->getOptions() as $option) {
            $optionData = [
                'id' => (int) $option->getId(),
                'title' => $option->getTitle(),
                'type' => $option->getType(),
                'required' => (bool) $option->getIsRequire(),
                'sortOrder' => (int) $option->getSortOrder(),
                'values' => [],
            ];

            // Get values for select/dropdown/radio/checkbox types
            if ($option->getValues()) {
                foreach ($option->getValues() as $value) {
                    $optionData['values'][] = [
                        'id' => (int) $value->getId(),
                        'title' => $value->getTitle(),
                        'price' => (float) $value->getPrice(),
                        'priceType' => $value->getPriceType(),
                        'sortOrder' => (int) $value->getSortOrder(),
                    ];
                }
            }

            $customOptions[] = $optionData;
        }

        return $customOptions;
    }

    /**
     * Get downloadable product links
     *
     * @return array<array{id: int, title: string, price: float, sortOrder: int, numberOfDownloads: int, sampleUrl: string|null}>
     */
    private function getDownloadableLinks(\Mage_Catalog_Model_Product $product): array
    {
        /** @var \Mage_Downloadable_Model_Product_Type $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $links = $typeInstance->getLinks($product);

        if (!$links) {
            return [];
        }

        $result = [];
        $store = \Mage::app()->getStore();

        foreach ($links as $link) {
            /** @var \Mage_Downloadable_Model_Link $link */
            $sampleUrl = null;
            if ($link->getSampleFile()) {
                $sampleUrl = \Mage::getUrl('downloadable/download/linkSample', ['link_id' => $link->getId()]);
            } elseif ($link->getSampleUrl()) {
                $sampleUrl = $link->getSampleUrl();
            }

            $result[] = [
                'id' => (int) $link->getId(),
                'title' => $link->getStoreTitle() ?: $link->getTitle(),
                'price' => (float) $store->convertPrice($link->getPrice(), false),
                'sortOrder' => (int) $link->getSortOrder(),
                'numberOfDownloads' => (int) $link->getNumberOfDownloads(),
                'sampleUrl' => $sampleUrl,
            ];
        }

        // Sort by sort_order
        usort($result, fn($a, $b) => $a['sortOrder'] <=> $b['sortOrder']);

        return $result;
    }

    /**
     * Map array data (from Meilisearch) to Product DTO
     */
    private function mapArrayToDto(array $data): Product
    {
        $dto = new Product();
        $dto->id = isset($data['id']) ? (int) $data['id'] : null;
        $dto->sku = $data['sku'] ?? '';
        $dto->name = $data['name'] ?? '';
        $dto->description = $data['description'] ?? null;
        $dto->type = $data['type'] ?? \Mage_Catalog_Model_Product_Type::TYPE_SIMPLE;
        $dto->status = $data['status'] ?? 'enabled';
        $dto->price = isset($data['price']) ? (float) $data['price'] : null;
        $dto->specialPrice = isset($data['special_price']) ? (float) $data['special_price'] : null;
        $dto->finalPrice = isset($data['final_price']) ? (float) $data['final_price'] : null;
        $dto->stockQty = isset($data['stock_qty']) ? (float) $data['stock_qty'] : null;
        $dto->stockStatus = $data['stock_status'] ?? 'in_stock';

        // Get barcode from configured attribute (if POS module available)
        /** @phpstan-ignore-next-line */
        $posHelper = \Mage::helper('maho_pos');
        $barcodeAttr = ($posHelper && method_exists($posHelper, 'getBarcodeAttributeCode'))
            ? $posHelper->getBarcodeAttributeCode()
            : 'barcode';
        $dto->barcode = isset($data[$barcodeAttr]) ? (string) $data[$barcodeAttr] : null;

        $dto->imageUrl = $data['image_url'] ?? null;
        $dto->smallImageUrl = $data['small_image_url'] ?? null;
        $dto->thumbnailUrl = $data['thumbnail_url'] ?? null;
        $dto->categoryIds = $data['categories'] ?? [];
        $dto->createdAt = $data['created_at'] ?? null;
        $dto->updatedAt = $data['updated_at'] ?? null;

        // Check if product needs options selection
        // For configurable products or products with required_options flag
        $dto->hasRequiredOptions = (bool) ($data['has_required_options'] ?? $data['required_options'] ?? false);

        // If type is configurable, always requires options
        if ($dto->type === \Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $dto->hasRequiredOptions = true;
        }

        return $dto;
    }
}
