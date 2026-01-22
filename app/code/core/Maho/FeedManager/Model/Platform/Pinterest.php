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
 * Pinterest Catalog Platform Adapter
 *
 * Implements Pinterest Catalog feed specification
 * Uses Google Merchant Data specifications for RSS 2.0
 * @see https://help.pinterest.com/en/business/article/before-you-get-started-with-catalogs
 */
class Maho_FeedManager_Model_Platform_Pinterest extends Maho_FeedManager_Model_Platform_AbstractAdapter
{
    protected string $_code = 'pinterest';
    protected string $_name = 'Pinterest';
    protected array $_supportedFormats = ['xml', 'csv'];
    protected string $_defaultFormat = 'xml';
    protected string $_rootElement = 'rss';
    protected string $_itemElement = 'item';
    protected ?string $_taxonomyFile = 'taxonomy/google_product_taxonomy.txt';

    protected array $_namespaces = [
        'xmlns:g' => 'http://base.google.com/ns/1.0',
    ];

    protected array $_requiredAttributes = [
        'id' => [
            'label' => 'ID',
            'required' => true,
            'description' => 'Unique product identifier',
        ],
        'title' => [
            'label' => 'Title',
            'required' => true,
            'description' => 'Product title (max 500 characters)',
        ],
        'description' => [
            'label' => 'Description',
            'required' => true,
            'description' => 'Product description (max 10000 characters)',
        ],
        'link' => [
            'label' => 'Link',
            'required' => true,
            'description' => 'Product page URL',
        ],
        'image_link' => [
            'label' => 'Image Link',
            'required' => true,
            'description' => 'Main product image URL (recommended 1000x1500px, 2:3 ratio)',
        ],
        'availability' => [
            'label' => 'Availability',
            'required' => true,
            'description' => 'in stock, out of stock, preorder',
        ],
        'price' => [
            'label' => 'Price',
            'required' => true,
            'description' => 'Product price with ISO 4217 currency (e.g., 25.00 USD)',
        ],
    ];

    protected array $_optionalAttributes = [
        'brand' => [
            'label' => 'Brand',
            'required' => false,
            'description' => 'Product brand name',
        ],
        'gtin' => [
            'label' => 'GTIN',
            'required' => false,
            'description' => 'Global Trade Item Number (UPC, EAN, ISBN)',
        ],
        'mpn' => [
            'label' => 'MPN',
            'required' => false,
            'description' => 'Manufacturer Part Number',
        ],
        'condition' => [
            'label' => 'Condition',
            'required' => false,
            'description' => 'new, refurbished, used',
        ],
        'google_product_category' => [
            'label' => 'Google Product Category',
            'required' => false,
            'description' => 'Google taxonomy category',
        ],
        'product_type' => [
            'label' => 'Product Type',
            'required' => false,
            'description' => 'Your own product categorization (max 5 levels, separated by >)',
        ],
        'sale_price' => [
            'label' => 'Sale Price',
            'required' => false,
            'description' => 'Discounted price (must be lower than regular price)',
        ],
        'additional_image_link' => [
            'label' => 'Additional Image Links',
            'required' => false,
            'description' => 'Additional product images (comma-separated)',
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
        'gender' => [
            'label' => 'Gender',
            'required' => false,
            'description' => 'male, female, unisex',
        ],
        'age_group' => [
            'label' => 'Age Group',
            'required' => false,
            'description' => 'newborn, infant, toddler, kids, adult',
        ],
        'material' => [
            'label' => 'Material',
            'required' => false,
            'description' => 'Product material',
        ],
        'pattern' => [
            'label' => 'Pattern',
            'required' => false,
            'description' => 'Product pattern',
        ],
        'item_group_id' => [
            'label' => 'Item Group ID',
            'required' => false,
            'description' => 'Groups variants of the same product',
        ],
        'shipping' => [
            'label' => 'Shipping',
            'required' => false,
            'description' => 'Shipping information',
        ],
        'shipping_weight' => [
            'label' => 'Shipping Weight',
            'required' => false,
            'description' => 'Product weight with unit',
        ],
        'custom_label_0' => [
            'label' => 'Custom Label 0',
            'required' => false,
            'description' => 'Custom grouping label',
        ],
        'custom_label_1' => [
            'label' => 'Custom Label 1',
            'required' => false,
            'description' => 'Custom grouping label',
        ],
        'custom_label_2' => [
            'label' => 'Custom Label 2',
            'required' => false,
            'description' => 'Custom grouping label',
        ],
        'custom_label_3' => [
            'label' => 'Custom Label 3',
            'required' => false,
            'description' => 'Custom grouping label',
        ],
        'custom_label_4' => [
            'label' => 'Custom Label 4',
            'required' => false,
            'description' => 'Custom grouping label',
        ],
        'adult' => [
            'label' => 'Adult',
            'required' => false,
            'description' => 'yes/no - adult content flag',
        ],
        'free_shipping_label' => [
            'label' => 'Free Shipping Label',
            'required' => false,
            'description' => 'Label for free shipping eligibility',
        ],
        'free_shipping_limit' => [
            'label' => 'Free Shipping Limit',
            'required' => false,
            'description' => 'Minimum order for free shipping',
        ],
    ];

    protected array $_defaultMappings = [
        'id' => ['source_type' => 'attribute', 'source_value' => 'sku'],
        'title' => ['source_type' => 'attribute', 'source_value' => 'name'],
        'description' => ['source_type' => 'attribute', 'source_value' => 'description'],
        'link' => ['source_type' => 'attribute', 'source_value' => 'url', 'use_parent' => 'always'],
        'image_link' => ['source_type' => 'attribute', 'source_value' => 'image'],
        'additional_image_link' => ['source_type' => 'attribute', 'source_value' => 'additional_images_csv', 'use_parent' => 'if_empty'],
        'price' => ['source_type' => 'attribute', 'source_value' => 'price'],
        'sale_price' => ['source_type' => 'attribute', 'source_value' => 'special_price'],
        'availability' => ['source_type' => 'rule', 'source_value' => 'stock_status'],
        'brand' => ['source_type' => 'attribute', 'source_value' => 'manufacturer'],
        'gtin' => ['source_type' => 'attribute', 'source_value' => 'gtin'],
        'mpn' => ['source_type' => 'attribute', 'source_value' => 'mpn'],
        'condition' => ['source_type' => 'static', 'source_value' => 'new'],
        'item_group_id' => ['source_type' => 'attribute', 'source_value' => 'sku', 'use_parent' => 'always'],
        'product_type' => ['source_type' => 'attribute', 'source_value' => 'category_path'],
        'color' => ['source_type' => 'attribute', 'source_value' => 'color'],
        'size' => ['source_type' => 'attribute', 'source_value' => 'size'],
        'gender' => ['source_type' => 'attribute', 'source_value' => 'gender'],
        'adult' => ['source_type' => 'static', 'source_value' => 'no'],
    ];

    #[\Override]
    public function transformProductData(array $productData): array
    {
        // Transform availability
        if (isset($productData['availability'])) {
            $productData['availability'] = $this->_transformAvailability($productData['availability']);
        }

        // Transform condition
        if (isset($productData['condition'])) {
            $productData['condition'] = $this->_transformCondition($productData['condition']);
        }

        // Sanitize and truncate title
        if (isset($productData['title'])) {
            $productData['title'] = $this->_truncateText(
                $this->_sanitizeText($productData['title']),
                500,
            );
        }

        // Sanitize and truncate description
        if (isset($productData['description'])) {
            $productData['description'] = $this->_truncateText(
                $this->_sanitizeText($productData['description']),
                10000,
            );
        }

        // Ensure price has currency (ISO 4217)
        if (isset($productData['price']) && is_numeric($productData['price'])) {
            $currency = $productData['currency'] ?? 'USD';
            $productData['price'] = $this->_formatPrice((float) $productData['price'], $currency);
            unset($productData['currency']);
        }

        // Same for sale_price
        if (isset($productData['sale_price']) && is_numeric($productData['sale_price']) && $productData['sale_price'] > 0) {
            $currency = $productData['currency'] ?? 'USD';
            $productData['sale_price'] = $this->_formatPrice((float) $productData['sale_price'], $currency);
        }

        // Transform gender
        if (isset($productData['gender'])) {
            $productData['gender'] = $this->_transformGender($productData['gender']);
        }

        // Transform age_group
        if (isset($productData['age_group'])) {
            $productData['age_group'] = $this->_transformAgeGroup($productData['age_group']);
        }

        // Transform adult flag
        if (isset($productData['adult'])) {
            $productData['adult'] = $this->_transformBoolean($productData['adult']);
        }

        // Ensure product_type has max 5 levels
        if (isset($productData['product_type'])) {
            $productData['product_type'] = $this->_limitCategoryLevels($productData['product_type'], 5);
        }

        return $productData;
    }

    #[\Override]
    public function validateProductData(array $productData): array
    {
        $errors = parent::validateProductData($productData);

        // Validate availability values
        $validAvailability = ['in stock', 'out of stock', 'preorder'];
        if (isset($productData['availability']) && !in_array($productData['availability'], $validAvailability)) {
            $errors[] = 'Invalid availability value: ' . $productData['availability'];
        }

        // Validate condition values
        $validCondition = ['new', 'refurbished', 'used'];
        if (isset($productData['condition']) && !in_array($productData['condition'], $validCondition)) {
            $errors[] = 'Invalid condition value: ' . $productData['condition'];
        }

        // Validate sale price is lower than price
        if (!empty($productData['sale_price']) && !empty($productData['price'])) {
            $salePrice = (float) preg_replace('/[^0-9.]/', '', $productData['sale_price']);
            $price = (float) preg_replace('/[^0-9.]/', '', $productData['price']);
            if ($salePrice >= $price) {
                $errors[] = 'Sale price must be lower than regular price';
            }
        }

        return $errors;
    }

    #[\Override]
    protected function _transformAvailability(mixed $value, bool $useUnderscore = false): string
    {
        if (is_numeric($value)) {
            return (int) $value > 0 ? 'in stock' : 'out of stock';
        }

        $map = [
            '1' => 'in stock',
            '0' => 'out of stock',
            'in_stock' => 'in stock',
            'out_of_stock' => 'out of stock',
            'in stock' => 'in stock',
            'out of stock' => 'out of stock',
            'available' => 'in stock',
            'unavailable' => 'out of stock',
            'yes' => 'in stock',
            'no' => 'out of stock',
            'preorder' => 'preorder',
        ];

        $normalized = strtolower(trim((string) $value));
        return $map[$normalized] ?? 'out of stock';
    }

    protected function _limitCategoryLevels(string $path, int $maxLevels): string
    {
        $parts = explode(' > ', $path);
        if (count($parts) > $maxLevels) {
            $parts = array_slice($parts, 0, $maxLevels);
        }
        return implode(' > ', $parts);
    }

    /**
     * Get attributes that need the g: namespace prefix in XML
     */
    public function getNamespacedAttributes(): array
    {
        return array_diff(
            array_keys($this->getAllAttributes()),
            ['title', 'description', 'link'],
        );
    }
}
