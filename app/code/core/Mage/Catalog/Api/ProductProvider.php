<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Service\StoreContext;

/**
 * Product State Provider - Fetches product data for API Platform
 */
final class ProductProvider extends \Maho\ApiPlatform\Provider
{
    private ?\Mage_Catalog_Model_Product_Media_Config $mediaConfig = null;

    private function getMediaConfig(): \Mage_Catalog_Model_Product_Media_Config
    {
        return $this->mediaConfig ??= \Mage::getModel('catalog/product_media_config');
    }

    /**
     * Provide product data based on operation type
     *
     * @return TraversablePaginator<Product>|Product|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|Product|null
    {
        // Ensure valid store context
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
                return new TraversablePaginator(new \ArrayIterator([]), 1, 20, 0);
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
        $storeId = StoreContext::getStoreId();
        $cacheKey = "api_product_{$id}_{$storeId}";

        $cached = \Mage::app()->getCache()->load($cacheKey);
        if ($cached !== false) {
            $data = json_decode($cached, true);
            if ($data !== null) {
                return Product::fromArray($data);
            }
        }

        $product = \Mage::getModel('catalog/product')->load($id);
        if (!$product->getId()) {
            return null;
        }

        $dto = $this->toDto($product);

        \Mage::app()->getCache()->save(
            (string) json_encode($dto->toArray()),
            $cacheKey,
            ['API_PRODUCTS', "API_PRODUCT_{$id}"],
            $this->getCacheTtl(),
        );

        return $dto;
    }

    /**
     * Get a product by SKU
     */
    private function getProductBySku(string $sku): ?Product
    {
        $product = \Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
        return $product instanceof \Mage_Catalog_Model_Product ? $this->toDto($product) : null;
    }

    /**
     * Get a product by barcode
     */
    private function getProductByBarcode(string $barcode): ?Product
    {
        $product = \Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToFilter('barcode', $barcode)
            ->getFirstItem();
        /** @var \Mage_Catalog_Model_Product $product */
        return $product->getId() ? $this->toDto($product) : null;
    }

    /**
     * Get configurable cache TTL (from admin config, default 300s)
     */
    private function getCacheTtl(): int
    {
        return \Maho_ApiPlatform_Model_Observer::getCacheTtl();
    }

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
     * Get products by urlKey — direct DB lookup
     *
     * @return TraversablePaginator<Product>
     */
    private function getByUrlKey(string $urlKey, int $page, int $pageSize): TraversablePaginator
    {
        $collection = \Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect('*')
            ->addAttributeToFilter('url_key', $urlKey)
            ->addAttributeToFilter('status', \Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setPageSize($pageSize)
            ->setCurPage($page);

        // Sort configurables first so they take priority over simples with shared url_keys
        $collection->getSelect()->order(
            new \Maho\Db\Expr("FIELD(e.type_id, '" . \Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE . "', '" . \Mage_Catalog_Model_Product_Type::TYPE_GROUPED . "', '" . \Mage_Catalog_Model_Product_Type::TYPE_BUNDLE . "') DESC"),
        );

        $storeId = StoreContext::getStoreId();
        if ($storeId) {
            $collection->setStoreId($storeId);
        }

        $products = [];
        foreach ($collection as $product) {
            $products[] = $this->toDto($product, forListing: true);
        }

        return new TraversablePaginator(new \ArrayIterator($products), $page, $pageSize, (int) $collection->getSize());
    }

    /**
     * Get product collection with pagination and search
     *
     * @return TraversablePaginator<Product>
     */
    private function getCollection(array $context): TraversablePaginator
    {
        // Merge REST filters and GraphQL args (GraphQL args take precedence)
        $requestFilters = array_merge($context['filters'] ?? [], $context['args'] ?? []);
        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context);
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
                    $products = array_map(fn($data) => Product::fromArray($data), $cachedData['products']);
                    return new TraversablePaginator(new \ArrayIterator($products), $cachedData['page'], $cachedData['pageSize'], $cachedData['total']);
                }
            }
        }

        // Handle urlKey filter — direct DB lookup, bypass search
        if (!empty($requestFilters['urlKey'])) {
            return $this->getByUrlKey((string) $requestFilters['urlKey'], $page, $pageSize);
        }

        // Use search layer for text queries, catalog layer for browsing
        if (!empty($search)) {
            \Mage::helper('catalogsearch')->getQuery()->setQueryText($search);
            $layer = \Mage::getSingleton('catalogsearch/layer');
        } else {
            $layer = \Mage::getSingleton('catalog/layer');
        }

        if (!empty($requestFilters['categoryId'])) {
            $category = \Mage::getModel('catalog/category')->load((int) $requestFilters['categoryId']);
            if ($category->getId()) {
                $layer->setCurrentCategory($category);
            }
        }

        $collection = $layer->getProductCollection();
        $collection->addAttributeToFilter('status', \Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

        if (!empty($requestFilters['priceMin']) || !empty($requestFilters['priceMax'])) {
            $priceFilter = [];
            if (!empty($requestFilters['priceMin'])) {
                $priceFilter['from'] = (string) (float) $requestFilters['priceMin'];
            }
            if (!empty($requestFilters['priceMax'])) {
                $priceFilter['to'] = (string) (float) $requestFilters['priceMax'];
            }
            $collection->addAttributeToFilter('price', $priceFilter);
        }

        // Extract attribute filters — REST uses attr_ prefix, GraphQL uses JSON string
        $attributeFilters = [];
        if (!empty($requestFilters['attributeFilters'])) {
            $decoded = json_decode($requestFilters['attributeFilters'], true);
            if (is_array($decoded)) {
                $attributeFilters = $decoded;
            }
        }
        foreach ($requestFilters as $key => $value) {
            if (str_starts_with($key, 'attr_') && $value !== '') {
                $attributeFilters[substr($key, 5)] = $value;
            }
        }
        foreach ($attributeFilters as $code => $value) {
            if (preg_match('/^[a-z][a-z0-9_]*$/', $code)) {
                $collection->addAttributeToFilter($code, $value);
            }
        }

        if (!empty($requestFilters['sortBy'])) {
            $sortDir = ($requestFilters['sortDir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
            $collection->setOrder($requestFilters['sortBy'], $sortDir);
        } else {
            $collection->setOrder('name', 'ASC');
        }

        $total = $collection->getSize();
        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $result = [
            'products' => iterator_to_array($collection),
            'total' => $total,
        ];

        // Collect product IDs for batch operations
        $productIds = [];
        $products = [];
        foreach ($result['products'] as $product) {
            if ($product instanceof \Mage_Catalog_Model_Product) {
                $productIds[] = (int) $product->getId();
                $products[] = $product;
            }
        }

        // Batch load review summaries to avoid N+1 queries
        $reviewSummaries = $this->batchLoadReviewSummaries($productIds);

        // Batch load category IDs to avoid N+1 queries
        $categoryIdsByProduct = $this->batchLoadCategoryIds($productIds);

        // Batch load stock items to avoid N+1 queries
        $stockItemsByProduct = $this->batchLoadStockItems($productIds);

        $forListing = empty($requestFilters['fullDetail']);

        $products = [];
        foreach ($result['products'] as $product) {
            if ($product instanceof \Mage_Catalog_Model_Product) {
                $productId = (int) $product->getId();
                $dto = Product::fromModel($product);
                $this->enrichProduct(
                    $dto,
                    $product,
                    forListing: $forListing,
                    reviewSummary: $reviewSummaries[$productId] ?? null,
                    categoryIds: $categoryIdsByProduct[$productId] ?? [],
                    stockItem: $stockItemsByProduct[$productId] ?? null,
                );
                $products[] = $dto;
            } elseif (is_array($product)) {
                $products[] = Product::fromArray($product);
            }
        }

        // Cache the results for non-search queries
        if ($cacheKey !== null && !empty($products)) {
            $cacheData = [
                'products' => array_map(fn($dto) => $dto->toArray(), $products),
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => (int) $result['total'],
            ];
            \Mage::app()->getCache()->save(
                json_encode($cacheData),
                $cacheKey,
                ['API_PRODUCTS'],
                $this->getCacheTtl(),
            );
        }

        // Return paginator with total count for proper pagination
        return new TraversablePaginator(new \ArrayIterator($products), $page, $pageSize, (int) $result['total']);
    }

    /**
     * Build a complete Product DTO from a Mage product model.
     */
    #[\Override]
    public function toDto(object $product, bool $forListing = false): Product
    {
        $dto = Product::fromModel($product);
        $this->enrichProduct($dto, $product, forListing: $forListing);
        return $dto;
    }

    /**
     * Enrich a Product DTO with external data (stock, reviews, categories)
     * and optionally detail-only sub-resources.
     *
     * Called after Product::fromModel() which handles basic field mapping and
     * computed fields (status/visibility enums, prices, image URLs, barcode).
     */
    private function enrichProduct(
        Product $dto,
        \Mage_Catalog_Model_Product $product,
        bool $forListing = false,
        ?array $reviewSummary = null,
        ?array $categoryIds = null,
        ?\Mage_CatalogInventory_Model_Stock_Item $stockItem = null,
    ): void {
        $stockData = $stockItem ?? $product->getStockItem();
        if ($stockData) {
            $dto->stockQty = (float) $stockData->getQty();
            $dto->stockStatus = $stockData->getIsInStock() ? 'in_stock' : 'out_of_stock';
        }

        $dto->categoryIds = $categoryIds ?? ($product->getCategoryIds() ?: []);

        if ($reviewSummary !== null) {
            if ($reviewSummary['reviews_count'] > 0) {
                $dto->reviewCount = $reviewSummary['reviews_count'];
                $dto->averageRating = $reviewSummary['rating_summary']
                    ? round((float) $reviewSummary['rating_summary'] / 20, 1)
                    : null;
            }
        } else {
            $reviewModel = \Mage::getModel('review/review_summary')
                ->setStoreId(StoreContext::getStoreId())
                ->load($product->getId());
            if ($reviewModel->getReviewsCount()) {
                $dto->reviewCount = (int) $reviewModel->getReviewsCount();
                $dto->averageRating = $reviewModel->getRatingSummary()
                    ? round((float) $reviewModel->getRatingSummary() / 20, 1)
                    : null;
            }
        }

        if ($forListing) {
            \Mage::dispatchEvent('api_product_dto_build', ['product' => $product, 'for_listing' => true, 'dto' => $dto]);
            return;
        }

        $typeId = $product->getTypeId();
        $typeInstance = $product->getTypeInstance(true);
        $mediaConfig = $this->getMediaConfig();

        // Configurable: options + variants
        if ($typeId === \Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            /** @var \Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
            $childProducts = $typeInstance->getUsedProducts(null, $product);
            $configAttributes = $typeInstance->getConfigurableAttributes($product);

            // Build used-value map once
            $usedValues = [];
            foreach ($childProducts as $child) {
                foreach ($configAttributes as $attr) {
                    $code = $attr->getProductAttribute()->getAttributeCode();
                    $val = $child->getData($code);
                    if ($val) {
                        $usedValues[$code][$val] = true;
                    }
                }
            }

            $dto->configurableOptions = [];
            foreach ($configAttributes as $attr) {
                $prodAttr = $attr->getProductAttribute();
                $code = $prodAttr->getAttributeCode();
                $values = [];
                foreach ($prodAttr->getSource()->getAllOptions() as $opt) {
                    if (($opt['value'] ?? '') !== '' && isset($usedValues[$code][$opt['value']])) {
                        $values[] = ['id' => (int) $opt['value'], 'label' => $opt['label'] ?: $opt['value']];
                    }
                }
                $dto->configurableOptions[] = [
                    'id' => (int) $attr->getAttributeId(),
                    'code' => $code,
                    'label' => $prodAttr->getStoreLabel() ?: $prodAttr->getFrontendLabel(),
                    'values' => $values,
                ];
            }

            $childIds = array_map(fn($c) => $c->getId(), $childProducts);
            $stockByChild = $this->batchLoadStockItems($childIds);

            $dto->variants = [];
            foreach ($childProducts as $child) {
                $attrs = [];
                foreach ($configAttributes as $attr) {
                    $attrs[$attr->getProductAttribute()->getAttributeCode()] = (int) $child->getData($attr->getProductAttribute()->getAttributeCode());
                }
                $si = $stockByChild[$child->getId()] ?? null;
                $variant = [
                    'id' => (int) $child->getId(),
                    'sku' => $child->getSku(),
                    'price' => (float) $child->getPrice(),
                    'finalPrice' => (float) $child->getFinalPrice(),
                    'stockQty' => $si ? (float) $si->getQty() : 0,
                    'inStock' => $si ? (bool) $si->getIsInStock() : false,
                    'attributes' => $attrs,
                ];
                if ($child->getData('barcode')) {
                    $variant['barcode'] = (string) $child->getData('barcode');
                }
                if ($child->getImage() && $child->getImage() !== 'no_selection') {
                    $variant['imageUrl'] = $mediaConfig->getMediaUrl($child->getImage());
                }
                $dto->variants[] = $variant;
            }
        }

        // Custom options
        $dto->customOptions = array_map(fn($opt) => [
            'id' => (int) $opt->getId(),
            'title' => $opt->getTitle(),
            'type' => $opt->getType(),
            'required' => (bool) $opt->getIsRequire(),
            'sortOrder' => (int) $opt->getSortOrder(),
            'values' => $opt->getValues() ? array_map(fn($v) => [
                'id' => (int) $v->getId(),
                'title' => $v->getTitle(),
                'price' => (float) $v->getPrice(),
                'priceType' => $v->getPriceType(),
                'sortOrder' => (int) $v->getSortOrder(),
            ], $opt->getValues()) : [],
        ], $product->getOptions());

        // Media gallery
        $images = $product->getMediaGalleryImages();
        $dto->mediaGallery = $images ? array_map(fn($img) => [
            'url' => $mediaConfig->getMediaUrl($img->getFile()),
            'label' => $img->getLabel() ?: null,
            'position' => (int) $img->getPosition(),
        ], iterator_to_array($images)) : [];

        // Linked products
        $dto->relatedProducts = $this->getLinkedProducts($product->getRelatedProductCollection());
        $dto->crosssellProducts = $this->getLinkedProducts($product->getCrossSellProductCollection());
        $dto->upsellProducts = $this->getLinkedProducts($product->getUpSellProductCollection());

        // Grouped children
        if ($typeId === \Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
            /** @var \Mage_Catalog_Model_Product_Type_Grouped $typeInstance */
            $associated = $typeInstance->getAssociatedProducts($product);
            if (!empty($associated)) {
                $childIds = array_map(fn($c) => (int) $c->getId(), $associated);
                $stockByChild = $this->batchLoadStockItems($childIds);
                $dto->groupedProducts = [];
                foreach ($associated as $child) {
                    $si = $stockByChild[(int) $child->getId()] ?? null;
                    $imgUrl = null;
                    foreach (['getThumbnail', 'getSmallImage'] as $method) {
                        $img = $child->$method();
                        if ($img && $img !== 'no_selection') {
                            $imgUrl = $mediaConfig->getMediaUrl($img);
                            break;
                        }
                    }
                    $dto->groupedProducts[] = [
                        'id' => (int) $child->getId(),
                        'sku' => $child->getSku(),
                        'name' => $child->getName(),
                        'price' => (float) $child->getPrice(),
                        'finalPrice' => (float) $child->getFinalPrice(),
                        'imageUrl' => $imgUrl,
                        'inStock' => $si ? (bool) $si->getIsInStock() : false,
                        'stockQty' => $si ? (float) $si->getQty() : 0,
                        'defaultQty' => (float) ($child->getQty() ?: 0),
                        'position' => (int) ($child->getPosition() ?: 0),
                    ];
                }
                usort($dto->groupedProducts, fn($a, $b) => $a['position'] <=> $b['position']);
            }
        }

        // Bundle options
        if ($typeId === \Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
            $dto->bundleOptions = $this->getBundleOptions($product);
        }

        // Downloadable links
        if ($typeId === \Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE) {
            /** @var \Mage_Downloadable_Model_Product_Type $typeInstance */
            $links = $typeInstance->getLinks($product);
            $store = \Mage::app()->getStore();
            $dto->downloadableLinks = $links ? array_map(function ($link) use ($store) {
                $sampleUrl = $link->getSampleFile()
                    ? \Mage::getUrl('downloadable/download/linkSample', ['link_id' => $link->getId()])
                    : ($link->getSampleUrl() ?: null);
                return [
                    'id' => (int) $link->getId(),
                    'title' => $link->getStoreTitle() ?: $link->getTitle(),
                    'price' => (float) $store->convertPrice($link->getPrice(), false),
                    'sortOrder' => (int) $link->getSortOrder(),
                    'numberOfDownloads' => (int) $link->getNumberOfDownloads(),
                    'sampleUrl' => $sampleUrl,
                ];
            }, $links) : [];
            $dto->linksTitle = $product->getData('links_title')
                ?: \Mage::getStoreConfig('catalog/downloadable/links_title')
                ?: 'Links';
            $dto->linksPurchasedSeparately = (bool) $product->getData('links_purchased_separately');
        }

        // Tier prices
        $rawTiers = $product->getTierPrice();
        if (is_array($rawTiers) && !empty($rawTiers)) {
            $basePrice = (float) $product->getPrice();
            $dto->tierPrices = [];
            foreach ($rawTiers as $tp) {
                $price = (float) ($tp['price'] ?? 0);
                if ($price > 0) {
                    $dto->tierPrices[] = [
                        'qty' => (float) ($tp['price_qty'] ?? 1),
                        'price' => $price,
                        'savePercent' => $basePrice > 0 ? (int) round((1 - $price / $basePrice) * 100) : 0,
                    ];
                }
            }
            usort($dto->tierPrices, fn($a, $b) => $a['qty'] <=> $b['qty']);
        }

        // Additional visible attributes (specifications tab)
        $excludeCodes = [
            'sku', 'name', 'description', 'short_description', 'price', 'special_price',
            'weight', 'status', 'visibility', 'url_key', 'meta_title', 'meta_description',
            'meta_keyword', 'image', 'small_image', 'thumbnail', 'page_layout',
            'tax_class_id', 'country_of_manufacture',
        ];
        $dto->additionalAttributes = [];
        foreach ($product->getAttributes() as $attribute) {
            if (!$attribute->getIsVisibleOnFront()) {
                continue;
            }
            $code = $attribute->getAttributeCode();
            if (in_array($code, $excludeCodes, true)) {
                continue;
            }
            $value = $attribute->getFrontend()->getValue($product);
            if ($value === null || $value === '' || $value === false || $value === 'N/A' || $value === 'No') {
                continue;
            }
            $dto->additionalAttributes[] = [
                'label' => $attribute->getStoreLabel() ?: $attribute->getFrontendLabel() ?: $code,
                'value' => (string) (is_array($value) ? implode(', ', $value) : $value),
                'code' => $code,
            ];
        }

        \Mage::dispatchEvent('api_product_dto_build', ['product' => $product, 'for_listing' => false, 'dto' => $dto]);
    }

    /**
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

        $result = [];
        foreach ($collection as $stockItem) {
            $result[$stockItem->getProductId()] = $stockItem;
        }
        return $result;
    }

    /**
     * @param int[] $productIds
     * @return array<int, array{reviews_count: int, rating_summary: float|null}>
     */
    private function batchLoadReviewSummaries(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $adapter = \Mage::getSingleton('core/resource')->getConnection('core_read');
        $select = $adapter->select()
            ->from(\Mage::getSingleton('core/resource')->getTableName('review/review_aggregate'), ['entity_pk_value', 'reviews_count', 'rating_summary'])
            ->where('entity_pk_value IN (?)', $productIds)
            ->where('store_id = ?', StoreContext::getStoreId());

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
     * @param int[] $productIds
     * @return array<int, int[]>
     */
    private function batchLoadCategoryIds(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $adapter = \Mage::getSingleton('core/resource')->getConnection('core_read');
        $select = $adapter->select()
            ->from(\Mage::getSingleton('core/resource')->getTableName('catalog/category_product'), ['product_id', 'category_id'])
            ->where('product_id IN (?)', $productIds);

        $result = [];
        foreach ($adapter->fetchAll($select) as $row) {
            $result[(int) $row['product_id']][] = (int) $row['category_id'];
        }
        return $result;
    }

    /**
     * @return Product[]
     */
    private function getLinkedProducts(\Mage_Catalog_Model_Resource_Product_Collection $collection): array
    {
        $collection->addAttributeToSelect(['name', 'price', 'special_price', 'image', 'small_image', 'thumbnail', 'url_key', 'status', 'visibility'])
            ->addFieldToFilter('status', \Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setPageSize(20);
        $collection->setVisibility(\Mage::getSingleton('catalog/product_visibility')->getVisibleInCatalogIds());
        if (!\Mage::getStoreConfigFlag('cataloginventory/options/show_out_of_stock')) {
            \Mage::getModel('cataloginventory/stock_status')->addIsInStockFilterToCollection($collection);
        }

        $products = [];
        foreach ($collection as $product) {
            $products[] = $this->toDto($product, forListing: true);
        }
        return $products;
    }

    /**
     * Bundle options with selections — kept as helper due to complexity
     * (dynamic vs fixed pricing, batch stock + price loading).
     */
    private function getBundleOptions(\Mage_Catalog_Model_Product $product): array
    {
        /** @var \Mage_Bundle_Model_Product_Type $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $isDynamic = (int) $product->getPriceType() === 0;

        $optionsCollection = $typeInstance->getOptionsCollection($product);
        if (!$optionsCollection || $optionsCollection->getSize() === 0) {
            return [];
        }

        $optionIds = [];
        foreach ($optionsCollection as $option) {
            $optionIds[] = (int) $option->getId();
        }

        $selectionsCollection = $typeInstance->getSelectionsCollection($optionIds, $product);
        $selectionsByOption = [];
        $selectionProductIds = [];
        foreach ($selectionsCollection as $selection) {
            $selectionsByOption[(int) $selection->getOptionId()][] = $selection;
            $selectionProductIds[] = (int) $selection->getProductId();
        }

        $uniqueProductIds = array_unique($selectionProductIds);
        $stockByProduct = $this->batchLoadStockItems($uniqueProductIds);

        $childProducts = [];
        if ($isDynamic && !empty($uniqueProductIds)) {
            $collection = \Mage::getResourceModel('catalog/product_collection')
                ->addIdFilter($uniqueProductIds)
                ->addAttributeToSelect(['price', 'special_price', 'special_from_date', 'special_to_date'])
                ->addPriceData()
                ->addTierPriceData();
            foreach ($collection as $child) {
                $childProducts[(int) $child->getId()] = $child;
            }
        }

        $result = [];
        foreach ($optionsCollection as $option) {
            $optionId = (int) $option->getId();
            $selections = [];

            foreach ($selectionsByOption[$optionId] ?? [] as $selection) {
                $selProductId = (int) $selection->getProductId();
                $si = $stockByProduct[$selProductId] ?? null;

                if ($isDynamic && isset($childProducts[$selProductId])) {
                    $selPrice = (float) $childProducts[$selProductId]->getFinalPrice();
                } else {
                    $selPrice = (float) $selection->getSelectionPriceValue() ?: (float) $selection->getPrice();
                }

                $selData = [
                    'id' => (int) $selection->getSelectionId(),
                    'productId' => $selProductId,
                    'sku' => $selection->getSku(),
                    'name' => $selection->getName(),
                    'price' => $selPrice,
                    'priceType' => (int) $selection->getSelectionPriceType() === 1 ? 'percent' : 'fixed',
                    'inStock' => $si ? (bool) $si->getIsInStock() : false,
                    'isDefault' => (bool) $selection->getIsDefault(),
                    'canChangeQty' => (bool) $selection->getSelectionCanChangeQty(),
                    'defaultQty' => (float) ($selection->getSelectionQty() ?: 1),
                    'position' => (int) ($selection->getPosition() ?: 0),
                ];

                if ($isDynamic && isset($childProducts[$selProductId])) {
                    $basePrice = (float) $childProducts[$selProductId]->getPrice();
                    $rawTiers = $childProducts[$selProductId]->getTierPrice();
                    if (is_array($rawTiers)) {
                        $selData['tierPrices'] = [];
                        foreach ($rawTiers as $tp) {
                            $p = (float) ($tp['price'] ?? 0);
                            if ($p > 0) {
                                $selData['tierPrices'][] = [
                                    'qty' => (float) ($tp['price_qty'] ?? 1),
                                    'price' => $p,
                                    'savePercent' => $basePrice > 0 ? (int) round((1 - $p / $basePrice) * 100) : 0,
                                ];
                            }
                        }
                    }
                }

                $selections[] = $selData;
            }

            usort($selections, fn($a, $b) => $a['position'] <=> $b['position']);
            $result[] = [
                'id' => $optionId,
                'title' => $option->getDefaultTitle() ?: $option->getTitle(),
                'type' => $option->getType(),
                'required' => (bool) $option->getRequired(),
                'position' => (int) ($option->getPosition() ?: 0),
                'selections' => $selections,
            ];
        }

        usort($result, fn($a, $b) => $a['position'] <=> $b['position']);
        return $result;
    }
}
