<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Catalog\Api\GraphQL;

use Mage\Catalog\Api\ProductProvider;
use Maho\ApiPlatform\Exception\ValidationException;

/**
 * Product Query Handler
 *
 * Handles all product-related GraphQL operations for admin API.
 * Uses ProductProvider::toDto() for model-based mapping to ensure
 * events (api_product_dto_build) and extensions fire consistently.
 */
class ProductQueryHandler
{
    private ProductProvider $productProvider;

    public function __construct(ProductProvider $productProvider)
    {
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
        $product = \Mage::getModel('catalog/product')->load((int) $id);
        return ['product' => $product->getId() ? $this->productProvider->toDto($product)->toArray() : null];
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
        $product = \Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
        $dto = $product instanceof \Mage_Catalog_Model_Product ? $this->productProvider->toDto($product)->toArray() : null;
        return ['productBySku' => $dto];
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
        $product = \Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToFilter('barcode', $barcode)
            ->getFirstItem();
        /** @var \Mage_Catalog_Model_Product $product */
        return ['productByBarcode' => $product->getId() ? $this->productProvider->toDto($product)->toArray() : null];
    }

    /**
     * Handle searchProducts query
     */
    public function handleSearchProducts(array $variables, array $context): array
    {
        $search = $variables['search'] ?? $variables['query'] ?? '';
        $page = $variables['page'] ?? 1;
        $pageSize = $variables['pageSize'] ?? $variables['limit'] ?? 20;
        $categoryId = $variables['categoryId'] ?? null;

        // Use search layer for text queries, catalog layer for browsing
        if (!empty($search)) {
            \Mage::helper('catalogsearch')->getQuery()->setQueryText($search);
            $layer = \Mage::getSingleton('catalogsearch/layer');
        } else {
            $layer = \Mage::getSingleton('catalog/layer');
        }

        if ($categoryId) {
            $category = \Mage::getModel('catalog/category')->load((int) $categoryId);
            if ($category->getId()) {
                $layer->setCurrentCategory($category);
            }
        }

        $collection = $layer->getProductCollection();
        $collection->addAttributeToFilter('status', \Mage_Catalog_Model_Product_Status::STATUS_ENABLED);
        $collection->setOrder('name', 'ASC');

        $total = $collection->getSize();
        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $edges = [];
        foreach ($collection as $product) {
            $edges[] = ['node' => $this->mapForRelay($product)];
        }

        return ['products' => [
            'edges' => $edges,
            'pageInfo' => [
                'totalCount' => $total,
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
        $product = \Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
        if (!$product instanceof \Mage_Catalog_Model_Product) {
            return ['getConfigurableProduct' => null];
        }

        return ['getConfigurableProduct' => $this->productProvider->toDto($product)->toArray()];
    }

    /**
     * Map a product model to relay-style format for paginated GraphQL responses.
     */
    public function mapForRelay(\Mage_Catalog_Model_Product $product, bool $includeConfigurableData = false): array
    {
        $dto = $this->productProvider->toDto($product, forListing: true);
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
     * Handle getCategories query
     */
    public function handleGetCategories(array $variables, array $context): array
    {
        $storeId = $context['store_id'] ?? 1;
        \Mage::app()->setCurrentStore($storeId);

        $parentId = $variables['parentId'] ?? null;
        $maxDepth = $variables['maxDepth'] ?? 3;
        $includeInactive = $variables['includeInactive'] ?? false;

        // Get root category for the store if no parent specified
        if ($parentId === null) {
            $rootCategoryId = \Mage::app()->getStore($storeId)->getRootCategoryId();
            $parentId = $rootCategoryId;
        }

        $escapedParentId = addcslashes((string) $parentId, '%_');
        $collection = \Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect(['name', 'is_active', 'position', 'level', 'children_count', 'image'])
            ->addFieldToFilter('path', ['like' => "%/{$escapedParentId}/%"])
            ->addFieldToFilter('level', ['lteq' => $maxDepth + 1])
            ->setOrder('position', 'ASC');

        if (!$includeInactive) {
            $collection->addFieldToFilter('is_active', 1);
        }

        $categories = [];
        foreach ($collection as $category) {
            // Skip root category itself
            if ($category->getId() == $parentId) {
                continue;
            }

            $categories[] = [
                'id' => (int) $category->getId(),
                'name' => $category->getName(),
                'parentId' => (int) $category->getParentId(),
                'level' => (int) $category->getLevel(),
                'position' => (int) $category->getPosition(),
                'isActive' => (bool) $category->getIsActive(),
                'childrenCount' => (int) $category->getChildrenCount(),
                'path' => $category->getPath(),
                'image' => $category->getImageUrl() ?: null,
            ];
        }

        return ['categories' => $categories];
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
