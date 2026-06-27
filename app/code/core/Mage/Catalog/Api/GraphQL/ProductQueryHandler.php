<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api\GraphQL;

use Mage\Catalog\Api\Category;
use Mage\Catalog\Api\Product;
use Mage\Catalog\Api\ProductProvider;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\AdminAcl;

/**
 * Product Query Handler.
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
        AdminAcl::checkResource(Product::class);
        $id = $variables['id'] ?? $variables['productId'] ?? null;
        if (!$id) {
            throw ValidationException::requiredField('id');
        }
        $dto = $this->productProvider->loadProductDto((int) $id, false);
        return ['product' => $dto ? $dto->toArray() : null];
    }

    /**
     * Handle getProductBySku query
     */
    public function handleGetProductBySku(array $variables): array
    {
        AdminAcl::checkResource(Product::class);
        $sku = $variables['sku'] ?? null;
        if (!$sku) {
            throw ValidationException::requiredField('sku');
        }
        $dto = $this->productProvider->getProductBySku($sku, false);
        return ['productBySku' => $dto ? $dto->toArray() : null];
    }

    /**
     * Handle getProductByBarcode query
     */
    public function handleGetProductByBarcode(array $variables): array
    {
        AdminAcl::checkResource(Product::class);
        $barcode = $variables['barcode'] ?? null;
        if (!$barcode) {
            throw ValidationException::requiredField('barcode');
        }
        $dto = $this->productProvider->getProductByBarcode($barcode, false);
        return ['productByBarcode' => $dto ? $dto->toArray() : null];
    }

    /**
     * Handle searchProducts query
     */
    public function handleSearchProducts(array $variables, array $context): array
    {
        AdminAcl::checkResource(Product::class);
        $search = $variables['search'] ?? $variables['query'] ?? '';
        $page = $variables['page'] ?? 1;
        $pageSize = $variables['pageSize'] ?? $variables['limit'] ?? 20;
        $categoryId = $variables['categoryId'] ?? null;

        // Use search layer for text queries, catalog layer for browsing.
        // Use fresh instances instead of singletons: under FPM workers (and the
        // test runner) the layer/helper singletons retain state across requests,
        // so a previous request's query text or current category would leak in.
        if (!empty($search)) {
            \Mage::unregister('_helper/catalogsearch');
            $searchHelper = \Mage::helper('catalogsearch');
            \Mage::app()->getRequest()->setParam($searchHelper->getQueryParamName(), $search);
            $searchHelper->getQuery()->setStoreId((int) \Mage::app()->getStore()->getId());
            $searchHelper->getQuery()->setQueryText($search);
            $layer = \Mage::getModel('catalogsearch/layer');
        } else {
            $layer = \Mage::getModel('catalog/layer');
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
        AdminAcl::checkResource(Product::class);
        $sku = $variables['sku'] ?? null;
        if (!$sku) {
            throw ValidationException::requiredField('sku');
        }
        $dto = $this->productProvider->getProductBySku($sku, false);
        return ['getConfigurableProduct' => $dto ? $dto->toArray() : null];
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
            'currency' => $dto->currency,
            'finalPrice' => (float) ($dto->finalPrice ?? $dto->price ?? 0.0),
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
        AdminAcl::checkResource(Category::class);
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

        // maxDepth is relative to the requested parent, so cap on the parent's
        // absolute tree level rather than assuming the parent is the store root.
        // Using $maxDepth + 1 against the global level only works when the parent
        // sits at level 1; deeper parents would otherwise truncate or return
        // nothing.
        $parentCategory = \Mage::getModel('catalog/category')->load((int) $parentId);
        $parentLevel = $parentCategory->getId() ? (int) $parentCategory->getLevel() : 1;
        $absoluteMaxLevel = $parentLevel + $maxDepth;

        $escapedParentId = addcslashes((string) $parentId, '%_');
        $collection = \Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect(['name', 'is_active', 'position', 'level', 'children_count', 'image'])
            ->addFieldToFilter('path', ['like' => "%/{$escapedParentId}/%"])
            ->addFieldToFilter('level', ['lteq' => $absoluteMaxLevel])
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
