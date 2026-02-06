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

namespace Maho\ApiPlatform\Service\GraphQL;

use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Service\ProductService;

/**
 * Product Query Handler
 *
 * Handles all product-related GraphQL operations for admin API.
 * Extracted from AdminGraphQlController for better code organization.
 */
class ProductQueryHandler
{
    private ProductService $productService;

    /** @var array<int, array{type: string, value: string}>|null */
    private static ?array $swatchCache = null;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
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
        return ['product' => $product ? $this->mapProduct($product) : null];
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
        return ['productBySku' => $product ? $this->mapProduct($product) : null];
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
        return ['productByBarcode' => $product ? $this->mapProduct($product) : null];
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
        $edges = array_values(array_map(fn($p) => ['node' => $this->mapProductForRelay($p)], $result['products'] ?? []));

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

        $result = $this->mapProduct($product);

        // Add configurable options and variants if this is a configurable product
        if ($product->getTypeId() === \Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $configurableData = $this->getConfigurableData($product);
            $result['configurableOptions'] = $configurableData['configurableOptions'];
            $result['variants'] = $configurableData['variants'];
        }

        return ['getConfigurableProduct' => $result];
    }

    /**
     * Map product to array format
     *
     * @param \Mage_Catalog_Model_Product|array $product
     */
    public function mapProduct($product): array
    {
        if (is_array($product)) {
            $stockStatus = $product['stock_status'] ?? 'out_of_stock';
            $type = $product['type'] ?? $product['type_id'] ?? \Mage_Catalog_Model_Product_Type::TYPE_SIMPLE;
            return [
                'id' => (int) ($product['id'] ?? $product['objectID'] ?? 0),
                'sku' => $product['sku'] ?? '',
                'name' => $product['name'] ?? '',
                'price' => (float) ($product['price'] ?? $product['final_price'] ?? 0),
                'specialPrice' => isset($product['special_price']) ? (float) $product['special_price'] : null,
                'finalPrice' => (float) ($product['final_price'] ?? $product['price'] ?? 0),
                'typeId' => strtoupper($type),
                'status' => $product['status'] ?? 'enabled',
                'stockStatus' => strtoupper(str_replace('_', '_', $stockStatus)),
                'stockQty' => (int) ($product['stock_qty'] ?? 0),
                'image' => $product['thumbnail_url'] ?? $product['image_url'] ?? null,
                'imageUrl' => $product['thumbnail_url'] ?? $product['image_url'] ?? null,
                'thumbnailUrl' => $product['thumbnail_url'] ?? null,
            ];
        }

        return [
            'id' => (int) $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'price' => (float) $product->getPrice(),
            'specialPrice' => $product->getSpecialPrice() ? (float) $product->getSpecialPrice() : null,
            'finalPrice' => (float) $product->getFinalPrice(),
            'typeId' => strtoupper($product->getTypeId()),
            'status' => (int) $product->getStatus(),
            'stockStatus' => $product->isSaleable()
                ? \Mage_CatalogInventory_Model_Stock_Status::STATUS_IN_STOCK
                : \Mage_CatalogInventory_Model_Stock_Status::STATUS_OUT_OF_STOCK,
            'stockQty' => (int) ($product->getStockItem() ? $product->getStockItem()->getQty() : 0),
            'image' => $product->getImageUrl(),
            'imageUrl' => $product->getImageUrl(),
            'thumbnailUrl' => $product->getThumbnailUrl(),
        ];
    }

    /**
     * Map product to GraphQL relay format.
     * Following Magento 2 pattern: configurable/grouped data only loaded when explicitly requested.
     *
     * @param \Mage_Catalog_Model_Product|array $product
     */
    public function mapProductForRelay($product, bool $includeConfigurableData = false): array
    {
        if (is_array($product)) {
            $stockStatus = $product['stock_status'] ?? 'out_of_stock';
            $type = $product['type'] ?? $product['type_id'] ?? \Mage_Catalog_Model_Product_Type::TYPE_SIMPLE;
            // Prefer thumbnail_url as it usually has actual product image, image_url often has placeholder
            $imageUrl = $product['thumbnail_url'] ?? $product['image_url'] ?? null;
            // If image_url is a placeholder, use thumbnail instead
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
                    $configurableData = $this->getConfigurableData($fullProduct);
                    $result['configurableOptions'] = $configurableData['configurableOptions'];
                    $result['variants'] = $configurableData['variants'];
                }
            }

            return $result;
        }

        $imageUrl = null;
        try {
            $imageUrl = $product->getImageUrl();
        } catch (\Exception $e) {
        }
        $typeName = $this->getProductTypeName($product->getTypeId());

        $result = [
            '__typename' => $typeName,
            'id' => (int) $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'type' => strtoupper($product->getTypeId()),
            'finalPrice' => ['value' => (float) $product->getFinalPrice()],
            'stockStatus' => $product->isSaleable()
                ? \Mage_CatalogInventory_Model_Stock_Status::STATUS_IN_STOCK
                : \Mage_CatalogInventory_Model_Stock_Status::STATUS_OUT_OF_STOCK,
            'stockQty' => (int) ($product->getStockItem() ? $product->getStockItem()->getQty() : 0),
            'images' => $imageUrl ? [['url' => $imageUrl, 'label' => $product->getName()]] : [],
        ];

        // Include configurable data for configurable products
        if ($includeConfigurableData && $product->getTypeId() === \Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            $configurableData = $this->getConfigurableData($product);
            $result['configurableOptions'] = $configurableData['configurableOptions'];
            $result['variants'] = $configurableData['variants'];
        }

        return $result;
    }

    /**
     * Extract configurable options and variants from a configurable product
     */
    public function getConfigurableData(\Mage_Catalog_Model_Product $product): array
    {
        $result = [
            'configurableOptions' => [],
            'variants' => [],
        ];

        if ($product->getTypeId() !== \Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            return $result;
        }

        /** @var \Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance(true);

        // Get configurable attributes
        $configurableAttributes = $typeInstance->getConfigurableAttributes($product);

        // Get child products (variants) first so we can filter options
        $childProducts = $typeInstance->getUsedProducts(null, $product);

        // Build a map of which attribute values are actually used by child products
        $usedValues = [];
        foreach ($configurableAttributes as $attribute) {
            $attrCode = $attribute->getProductAttribute()->getAttributeCode();
            $usedValues[$attrCode] = [];
        }
        foreach ($childProducts as $child) {
            foreach ($configurableAttributes as $attribute) {
                $attrCode = $attribute->getProductAttribute()->getAttributeCode();
                $value = $child->getData($attrCode);
                if ($value) {
                    $usedValues[$attrCode][$value] = true;
                }
            }
        }

        // Load swatch data for all options
        $swatchData = $this->loadSwatchData();

        // Build configurable options with only the values used by this product's children
        foreach ($configurableAttributes as $attribute) {
            $productAttribute = $attribute->getProductAttribute();
            $attrCode = $productAttribute->getAttributeCode();
            $options = [];

            /** @phpstan-ignore arguments.count */
            foreach ($productAttribute->getSource()->getAllOptions(false) as $option) {
                // Only include options that are actually used by child products
                if ($option['value'] && isset($usedValues[$attrCode][$option['value']])) {
                    $optionId = (int) $option['value'];
                    $swatch = $swatchData[$optionId] ?? null;

                    $options[] = [
                        'valueIndex' => $optionId,
                        'label' => $option['label'],
                        'swatchData' => $swatch ? [
                            'type' => $swatch['type'],
                            'value' => $swatch['value'],
                        ] : null,
                    ];
                }
            }

            $result['configurableOptions'][] = [
                'attributeId' => (int) $attribute->getAttributeId(),
                'attributeCode' => $attrCode,
                'label' => $attribute->getLabel() ?: $productAttribute->getFrontendLabel(),
                'values' => $options,
            ];
        }

        // Build variants array
        foreach ($childProducts as $child) {
            $variantAttributes = [];
            foreach ($configurableAttributes as $attribute) {
                $productAttribute = $attribute->getProductAttribute();
                $attrCode = $productAttribute->getAttributeCode();
                $variantAttributes[] = [
                    'code' => $attrCode,
                    'valueIndex' => (int) $child->getData($attrCode),
                ];
            }

            $stockItem = $child->getStockItem();
            $result['variants'][] = [
                'product' => [
                    'id' => (int) $child->getId(),
                    'sku' => $child->getSku(),
                    'name' => $child->getName(),
                    'finalPrice' => ['value' => (float) $child->getFinalPrice()],
                    'stockStatus' => ($stockItem && $stockItem->getIsInStock()) ? 'IN_STOCK' : 'OUT_OF_STOCK',
                    'stockQty' => $stockItem ? (int) $stockItem->getQty() : 0,
                ],
                'attributes' => $variantAttributes,
            ];
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
            'downloadable' => 'DownloadableProduct',
            'giftcard' => 'GiftCardProduct',
            default => 'SimpleProduct',
        };
    }

    /**
     * Load swatch data from database, indexed by option_id
     *
     * Loaded once per request from the eav_attribute_option_swatch table.
     * Size is bounded by the number of swatch-enabled attribute options.
     *
     * @return array<int, array{type: string, value: string}>
     */
    private function loadSwatchData(): array
    {
        if (self::$swatchCache !== null) {
            return self::$swatchCache;
        }

        self::$swatchCache = [];

        try {
            $conn = \Mage::getSingleton('core/resource')->getConnection('core_read');
            $table = \Mage::getSingleton('core/resource')->getTableName('eav_attribute_option_swatch');

            $results = $conn->fetchAll("SELECT option_id, value, filename FROM {$table}");

            foreach ($results as $row) {
                $optionId = (int) $row['option_id'];

                // Determine swatch type: color (hex value) or image (filename)
                if (!empty($row['filename'])) {
                    self::$swatchCache[$optionId] = [
                        'type' => 'IMAGE',
                        'value' => '/media/attribute/swatch/' . $row['filename'],
                    ];
                } elseif (!empty($row['value'])) {
                    // Check if it's a hex color
                    if (preg_match('/^#?[0-9A-Fa-f]{3,6}$/', $row['value'])) {
                        self::$swatchCache[$optionId] = [
                            'type' => 'COLOR',
                            'value' => $row['value'],
                        ];
                    } else {
                        self::$swatchCache[$optionId] = [
                            'type' => 'TEXT',
                            'value' => $row['value'],
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Swatch table may not exist, ignore
            \Mage::logException($e);
        }

        return self::$swatchCache;
    }
}
