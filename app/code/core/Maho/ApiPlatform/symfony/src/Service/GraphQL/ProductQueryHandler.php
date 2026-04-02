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

namespace Maho\ApiPlatform\Service\GraphQL;

use Mage\Catalog\Api\ProductProvider;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Service\ProductService;

/**
 * Product Query Handler
 *
 * Handles all product-related GraphQL operations for admin API.
 * Uses ProductProvider::mapToDto() for model-based mapping to ensure
 * events (api_product_dto_build) and extensions fire consistently.
 */
class ProductQueryHandler
{
    private ProductService $productService;
    private ProductProvider $productProvider;

    public function __construct(ProductService $productService, ProductProvider $productProvider)
    {
        $this->productService = $productService;
        $this->productProvider = $productProvider;
    }

    /**
     * Handle getProduct query
     */
    public function handleGetProduct(array $variables): array
    {
        $id = $variables['id'] ?? $variables['productId'] ?? null;
        if (!$id) {
            throw ValidationException::requiredField('id');
        }
        $product = $this->productService->getProductById((int) $id);
        return ['product' => $product ? $this->productProvider->mapToDto($product)->toArray() : null];
    }

    /**
     * Handle getProductBySku query
     */
    public function handleGetProductBySku(array $variables): array
    {
        $sku = $variables['sku'] ?? null;
        if (!$sku) {
            throw ValidationException::requiredField('sku');
        }
        $product = $this->productService->getProductBySku($sku);
        return ['productBySku' => $product ? $this->productProvider->mapToDto($product)->toArray() : null];
    }

    /**
     * Handle getProductByBarcode query
     */
    public function handleGetProductByBarcode(array $variables): array
    {
        $barcode = $variables['barcode'] ?? null;
        if (!$barcode) {
            throw ValidationException::requiredField('barcode');
        }
        $product = $this->productService->getProductByBarcode($barcode);
        return ['productByBarcode' => $product ? $this->productProvider->mapToDto($product)->toArray() : null];
    }

    /**
     * Handle searchProducts query
     */
    public function handleSearchProducts(array $variables, array $context): array
    {
        $search = $variables['search'] ?? $variables['query'] ?? '';
        $page = $variables['page'] ?? 1;
        $pageSize = $variables['pageSize'] ?? $variables['limit'] ?? 20;
        $storeId = $variables['storeId'] ?? $context['store_id'] ?? null;
        $usePosIndex = $variables['usePosIndex'] ?? false;
        $categoryId = $variables['categoryId'] ?? null;

        $filters = [];
        if ($categoryId) {
            $filters['categoryId'] = $categoryId;
        }

        $result = $this->productService->searchProducts($search, $page, $pageSize, $filters, null, $usePosIndex, $storeId);
        $edges = array_values(array_map(fn($p) => ['node' => $this->mapForRelay($p)], $result['products'] ?? []));

        return ['products' => [
            'edges' => $edges,
            'pageInfo' => [
                'totalCount' => $result['total'] ?? 0,
                'currentPage' => $page,
                'pageSize' => $pageSize,
            ],
        ]];
    }

    /**
     * Handle getConfigurableProduct query
     */
    public function handleGetConfigurableProduct(array $variables): array
    {
        $sku = $variables['sku'] ?? null;
        if (!$sku) {
            throw ValidationException::requiredField('sku');
        }
        $product = $this->productService->getProductBySku($sku);
        if (!$product) {
            return ['getConfigurableProduct' => null];
        }

        // mapToDto with forListing: false loads configurable options, variants, etc.
        return ['getConfigurableProduct' => $this->productProvider->mapToDto($product)->toArray()];
    }

    /**
     * Map a product to relay-style format for paginated GraphQL responses.
     *
     * Accepts either a Mage model or a search-index array (Meilisearch).
     * For models, delegates to the Provider DTO to ensure events fire.
     * For arrays (search results), maps directly since there's no model to load.
     *
     * @param \Mage_Catalog_Model_Product|array $product
     */
    public function mapForRelay($product, bool $includeConfigurableData = false): array
    {
        if (is_array($product)) {
            return $this->mapSearchResultForRelay($product, $includeConfigurableData);
        }

        $dto = $this->productProvider->mapToDto($product, forListing: true);
        $typeName = $this->getProductTypeName($product->getTypeId());

        $result = [
            '__typename' => $typeName,
            'id' => $dto->id,
            'sku' => $dto->sku,
            'name' => $dto->name,
            'type' => strtoupper($dto->type),
            'finalPrice' => ['value' => $dto->finalPrice ?? $dto->price ?? 0.0],
            'stockStatus' => strtoupper(str_replace('-', '_', $dto->stockStatus)),
            'stockQty' => (int) ($dto->stockQty ?? 0),
            'images' => $dto->imageUrl ? [['url' => $dto->imageUrl, 'label' => $dto->name]] : [],
        ];

        if ($includeConfigurableData && !empty($dto->configurableOptions)) {
            $result['configurableOptions'] = $dto->configurableOptions;
            $result['variants'] = $dto->variants;
        }

        return $result;
    }

    /**
     * Map a search-index array (Meilisearch) result to relay format.
     * No model available — map directly from the array.
     */
    private function mapSearchResultForRelay(array $product, bool $includeConfigurableData = false): array
    {
        $stockStatus = $product['stock_status'] ?? 'out_of_stock';
        $type = $product['type'] ?? $product['type_id'] ?? \Mage_Catalog_Model_Product_Type::TYPE_SIMPLE;
        $imageUrl = $product['thumbnail_url'] ?? $product['image_url'] ?? null;
        if ($imageUrl && str_contains($imageUrl, 'placeholder')) {
            $imageUrl = $product['thumbnail_url'] ?? $imageUrl;
        }
        $typeName = $this->getProductTypeName($type);

        $result = [
            '__typename' => $typeName,
            'id' => (int) ($product['id'] ?? $product['objectID'] ?? 0),
            'sku' => $product['sku'] ?? '',
            'name' => $product['name'] ?? '',
            'type' => strtoupper($type),
            'finalPrice' => ['value' => (float) ($product['final_price'] ?? $product['price'] ?? 0)],
            'stockStatus' => strtoupper(str_replace('_', '_', $stockStatus)),
            'stockQty' => (int) ($product['stock_qty'] ?? 0),
            'images' => $imageUrl ? [['url' => $imageUrl, 'label' => $product['name'] ?? '']] : [],
        ];

        // For configurable products from Meilisearch, load full product to get options
        if ($includeConfigurableData && $type === \Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $fullProduct = \Mage::getModel('catalog/product')->load($result['id']);
            if ($fullProduct->getId()) {
                $dto = $this->productProvider->mapToDto($fullProduct);
                $result['configurableOptions'] = $dto->configurableOptions;
                $result['variants'] = $dto->variants;
            }
        }

        return $result;
    }

    /**
     * Get GraphQL __typename for a product type
     */
    public function getProductTypeName(string $typeId): string
    {
        return match ($typeId) {
            \Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE => 'ConfigurableProduct',
            \Mage_Catalog_Model_Product_Type::TYPE_BUNDLE => 'BundleProduct',
            \Mage_Catalog_Model_Product_Type::TYPE_GROUPED => 'GroupedProduct',
            \Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL => 'VirtualProduct',
            \Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE => 'DownloadableProduct',
            'giftcard' => 'GiftCardProduct',
            default => 'SimpleProduct',
        };
    }

}
