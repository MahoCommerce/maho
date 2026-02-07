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
 * Custom Platform Adapter
 *
 * Generic adapter for custom feed formats without platform-specific rules
 */
class Maho_FeedManager_Model_Platform_Custom extends Maho_FeedManager_Model_Platform_AbstractAdapter
{
    protected string $_code = 'custom';
    protected string $_name = 'Custom Feed';
    protected array $_supportedFormats = ['xml', 'csv', 'json'];
    protected string $_defaultFormat = 'csv';
    protected string $_rootElement = 'products';
    protected string $_itemElement = 'product';
    protected ?string $_taxonomyFile = null;

    protected array $_namespaces = [];

    /**
     * No required attributes for custom feeds - fully user-defined
     */
    protected array $_requiredAttributes = [];

    /**
     * Common optional attributes that can be mapped
     */
    protected array $_optionalAttributes = [
        'id' => [
            'label' => 'Product ID',
            'required' => false,
            'description' => 'Unique product identifier',
        ],
        'sku' => [
            'label' => 'SKU',
            'required' => false,
            'description' => 'Stock Keeping Unit',
        ],
        'name' => [
            'label' => 'Product Name',
            'required' => false,
            'description' => 'Product title/name',
        ],
        'description' => [
            'label' => 'Description',
            'required' => false,
            'description' => 'Product description',
        ],
        'short_description' => [
            'label' => 'Short Description',
            'required' => false,
            'description' => 'Brief product description',
        ],
        'price' => [
            'label' => 'Price',
            'required' => false,
            'description' => 'Product price',
        ],
        'special_price' => [
            'label' => 'Special Price',
            'required' => false,
            'description' => 'Sale/special price',
        ],
        'url' => [
            'label' => 'Product URL',
            'required' => false,
            'description' => 'Product page link',
        ],
        'image' => [
            'label' => 'Main Image',
            'required' => false,
            'description' => 'Main product image URL',
        ],
        'additional_images' => [
            'label' => 'Additional Images',
            'required' => false,
            'description' => 'Additional product images',
        ],
        'category' => [
            'label' => 'Category',
            'required' => false,
            'description' => 'Product category',
        ],
        'brand' => [
            'label' => 'Brand',
            'required' => false,
            'description' => 'Product brand/manufacturer',
        ],
        'availability' => [
            'label' => 'Availability',
            'required' => false,
            'description' => 'Stock availability status',
        ],
        'quantity' => [
            'label' => 'Quantity',
            'required' => false,
            'description' => 'Stock quantity',
        ],
        'weight' => [
            'label' => 'Weight',
            'required' => false,
            'description' => 'Product weight',
        ],
        'color' => [
            'label' => 'Color',
            'required' => false,
            'description' => 'Product color',
        ],
        'size' => [
            'label' => 'Size',
            'required' => false,
            'description' => 'Product size',
        ],
        'gtin' => [
            'label' => 'GTIN/UPC/EAN',
            'required' => false,
            'description' => 'Global Trade Item Number',
        ],
        'mpn' => [
            'label' => 'MPN',
            'required' => false,
            'description' => 'Manufacturer Part Number',
        ],
        'condition' => [
            'label' => 'Condition',
            'required' => false,
            'description' => 'Product condition (new, used, etc.)',
        ],
    ];

    protected array $_defaultMappings = [
        'id' => ['source_type' => 'attribute', 'source_value' => 'entity_id'],
        'sku' => ['source_type' => 'attribute', 'source_value' => 'sku'],
        'name' => ['source_type' => 'attribute', 'source_value' => 'name'],
        'description' => ['source_type' => 'attribute', 'source_value' => 'description'],
        'price' => ['source_type' => 'attribute', 'source_value' => 'price'],
        'url' => ['source_type' => 'attribute', 'source_value' => 'url'],
        'image' => ['source_type' => 'attribute', 'source_value' => 'image'],
    ];

    /**
     * Custom feeds pass data through with minimal transformation
     */
    #[\Override]
    public function transformProductData(array $productData): array
    {
        // Basic sanitization only
        foreach ($productData as $key => $value) {
            if (is_string($value)) {
                // Remove control characters but preserve formatting
                $productData[$key] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
            }
        }

        return $productData;
    }

    /**
     * No strict validation for custom feeds
     */
    #[\Override]
    public function validateProductData(array $productData): array
    {
        // Custom feeds have no required fields by default
        return [];
    }

    /**
     * Custom feeds don't support category mapping by default
     */
    #[\Override]
    public function supportsCategoryMapping(): bool
    {
        return false;
    }
}
