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
 * OpenAI Commerce Platform Adapter
 *
 * Implements OpenAI Product Feed specification for ChatGPT shopping
 * @see https://developers.openai.com/commerce/specs/feed/
 */
class Maho_FeedManager_Model_Platform_Openai extends Maho_FeedManager_Model_Platform_AbstractAdapter
{
    protected string $_code = 'openai';
    protected string $_name = 'OpenAI Commerce';
    protected array $_supportedFormats = ['jsonl', 'csv'];
    protected string $_defaultFormat = 'jsonl';
    protected string $_rootElement = '';
    protected string $_itemElement = '';
    protected ?string $_taxonomyFile = null;

    protected array $_namespaces = [];

    protected array $_requiredAttributes = [
        'item_id' => [
            'label' => 'Item ID',
            'required' => true,
            'description' => 'Unique merchant product ID (max 100 chars)',
        ],
        'title' => [
            'label' => 'Title',
            'required' => true,
            'description' => 'Product name (max 150 chars)',
        ],
        'description' => [
            'label' => 'Description',
            'required' => true,
            'description' => 'Full product details (max 5,000 chars, plain text)',
        ],
        'url' => [
            'label' => 'URL',
            'required' => true,
            'description' => 'Product page URL (must resolve with HTTP 200)',
        ],
        'brand' => [
            'label' => 'Brand',
            'required' => true,
            'description' => 'Manufacturer name (max 70 chars)',
        ],
        'price' => [
            'label' => 'Price',
            'required' => true,
            'description' => 'Regular price with ISO 4217 currency code',
        ],
        'availability' => [
            'label' => 'Availability',
            'required' => true,
            'description' => 'in_stock, out_of_stock, pre_order, backorder, unknown',
        ],
        'image_url' => [
            'label' => 'Image URL',
            'required' => true,
            'description' => 'Main product image (JPEG/PNG, HTTPS preferred)',
        ],
        'group_id' => [
            'label' => 'Group ID',
            'required' => true,
            'description' => 'Variant grouping identifier (max 70 chars)',
        ],
        'store_name' => [
            'label' => 'Store Name',
            'required' => true,
            'description' => 'Seller/merchant name (max 70 chars)',
        ],
        'return_policy' => [
            'label' => 'Return Policy URL',
            'required' => true,
            'description' => 'URL to return policy page (HTTPS preferred)',
        ],
        'return_window' => [
            'label' => 'Return Window',
            'required' => true,
            'description' => 'Number of days allowed for returns',
        ],
    ];

    protected array $_optionalAttributes = [
        'is_eligible_search' => [
            'label' => 'Eligible for Search',
            'required' => false,
            'description' => 'Controls product discoverability in ChatGPT (true/false)',
        ],
        'is_eligible_checkout' => [
            'label' => 'Eligible for Checkout',
            'required' => false,
            'description' => 'Enables direct purchase; requires search eligibility (true/false)',
        ],
        'sale_price' => [
            'label' => 'Sale Price',
            'required' => false,
            'description' => 'Discounted price (must be <= regular price)',
        ],
        'sale_price_start_date' => [
            'label' => 'Sale Start Date',
            'required' => false,
            'description' => 'Sale price start date (ISO 8601)',
        ],
        'sale_price_end_date' => [
            'label' => 'Sale End Date',
            'required' => false,
            'description' => 'Sale price end date (ISO 8601)',
        ],
        'availability_date' => [
            'label' => 'Availability Date',
            'required' => false,
            'description' => 'Required if pre_order (ISO 8601, future date)',
        ],
        'additional_image_urls' => [
            'label' => 'Additional Image URLs',
            'required' => false,
            'description' => 'Extra product images (comma-separated)',
        ],
        'video_url' => [
            'label' => 'Video URL',
            'required' => false,
            'description' => 'Product video (publicly accessible)',
        ],
        'listing_has_variations' => [
            'label' => 'Has Variations',
            'required' => false,
            'description' => 'Boolean flag for variant products',
        ],
        'color' => [
            'label' => 'Color',
            'required' => false,
            'description' => 'Product color variant attribute',
        ],
        'size' => [
            'label' => 'Size',
            'required' => false,
            'description' => 'Product size variant attribute',
        ],
        'size_system' => [
            'label' => 'Size System',
            'required' => false,
            'description' => 'Size system (US, UK, EU, etc.)',
        ],
        'variant_dict' => [
            'label' => 'Variant Dictionary',
            'required' => false,
            'description' => 'JSON object mapping variant attributes',
        ],
        'seller_url' => [
            'label' => 'Seller URL',
            'required' => false,
            'description' => 'Seller page URL (HTTPS preferred)',
        ],
        'seller_privacy_policy' => [
            'label' => 'Privacy Policy URL',
            'required' => false,
            'description' => 'Required if checkout eligible',
        ],
        'seller_tos' => [
            'label' => 'Terms of Service URL',
            'required' => false,
            'description' => 'Required if checkout eligible',
        ],
        'accepts_returns' => [
            'label' => 'Accepts Returns',
            'required' => false,
            'description' => 'Boolean flag for return acceptance',
        ],
        'accepts_exchanges' => [
            'label' => 'Accepts Exchanges',
            'required' => false,
            'description' => 'Boolean flag for exchange acceptance',
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
        'shipping_weight' => [
            'label' => 'Shipping Weight',
            'required' => false,
            'description' => 'Product weight with unit',
        ],
        'popularity_score' => [
            'label' => 'Popularity Score',
            'required' => false,
            'description' => 'Quality signal for product ranking',
        ],
        'return_rate' => [
            'label' => 'Return Rate',
            'required' => false,
            'description' => 'Product return rate (quality signal)',
        ],
    ];

    protected array $_defaultMappings = [
        'item_id' => ['source_type' => 'attribute', 'source_value' => 'sku'],
        'title' => ['source_type' => 'attribute', 'source_value' => 'name'],
        'description' => ['source_type' => 'attribute', 'source_value' => 'description'],
        'url' => ['source_type' => 'attribute', 'source_value' => 'url', 'use_parent' => 'always'],
        'image_url' => ['source_type' => 'attribute', 'source_value' => 'image'],
        'price' => ['source_type' => 'attribute', 'source_value' => 'price'],
        'sale_price' => ['source_type' => 'attribute', 'source_value' => 'special_price'],
        'sale_price_start_date' => [
            'source_type' => 'attribute',
            'source_value' => 'special_from_date',
            'transformers' => 'format_date:output_format=Y-m-d',
        ],
        'sale_price_end_date' => [
            'source_type' => 'attribute',
            'source_value' => 'special_to_date',
            'transformers' => 'format_date:output_format=Y-m-d',
        ],
        'brand' => ['source_type' => 'attribute', 'source_value' => 'manufacturer'],
        'gtin' => ['source_type' => 'static', 'source_value' => ''],
        'mpn' => ['source_type' => 'static', 'source_value' => ''],
        'condition' => ['source_type' => 'static', 'source_value' => 'new'],
        'availability' => ['source_type' => 'rule', 'source_value' => 'stock_status'],
        'color' => ['source_type' => 'attribute', 'source_value' => 'color'],
        'size' => ['source_type' => 'attribute', 'source_value' => 'size'],
        'size_system' => ['source_type' => 'attribute', 'source_value' => 'store_country'],
        'gender' => ['source_type' => 'attribute', 'source_value' => 'gender'],
        'age_group' => ['source_type' => 'static', 'source_value' => ''],
        'material' => ['source_type' => 'attribute', 'source_value' => 'material'],
        'group_id' => ['source_type' => 'attribute', 'source_value' => 'sku', 'use_parent' => 'always'],
        'listing_has_variations' => ['source_type' => 'attribute', 'source_value' => 'is_variant'],
        'variant_dict' => ['source_type' => 'static', 'source_value' => ''],
        'video_url' => ['source_type' => 'static', 'source_value' => ''],
        'additional_image_urls' => ['source_type' => 'attribute', 'source_value' => 'additional_images_csv', 'use_parent' => 'if_empty'],
        'availability_date' => ['source_type' => 'static', 'source_value' => ''],
        'shipping_weight' => ['source_type' => 'attribute', 'source_value' => 'weight'],
        'popularity_score' => ['source_type' => 'static', 'source_value' => ''],
        'return_rate' => ['source_type' => 'static', 'source_value' => ''],
        'is_eligible_search' => ['source_type' => 'static', 'source_value' => 'true'],
        'is_eligible_checkout' => ['source_type' => 'static', 'source_value' => 'false'],
        'store_name' => ['source_type' => 'attribute', 'source_value' => 'store_name'],
        'seller_url' => ['source_type' => 'attribute', 'source_value' => 'store_url'],
        'seller_privacy_policy' => ['source_type' => 'static', 'source_value' => ''],
        'seller_tos' => ['source_type' => 'static', 'source_value' => ''],
        'return_policy' => ['source_type' => 'static', 'source_value' => ''],
        'return_window' => ['source_type' => 'static', 'source_value' => '30'],
        'accepts_returns' => ['source_type' => 'static', 'source_value' => 'true'],
        'accepts_exchanges' => ['source_type' => 'static', 'source_value' => 'true'],
    ];

    #[\Override]
    public function transformProductData(array $productData): array
    {
        // Transform availability to OpenAI format (uses underscore: pre_order)
        if (isset($productData['availability'])) {
            $productData['availability'] = $this->_transformAvailabilityOpenai($productData['availability']);
        }

        // Transform condition
        if (isset($productData['condition'])) {
            $productData['condition'] = $this->_transformCondition($productData['condition']);
        }

        // Sanitize and truncate item_id
        if (isset($productData['item_id'])) {
            $productData['item_id'] = $this->_truncateText(
                $this->_sanitizeText($productData['item_id']),
                100,
            );
        }

        // Sanitize and truncate title
        if (isset($productData['title'])) {
            $productData['title'] = $this->_truncateText(
                $this->_sanitizeText($productData['title']),
                150,
            );
        }

        // Sanitize and truncate description - plain text only
        if (isset($productData['description'])) {
            $productData['description'] = $this->_truncateText(
                strip_tags($this->_sanitizeText($productData['description'])),
                5000,
            );
        }

        // Truncate brand
        if (isset($productData['brand'])) {
            $productData['brand'] = $this->_truncateText(
                $this->_sanitizeText($productData['brand']),
                70,
            );
        }

        // Truncate group_id
        if (isset($productData['group_id'])) {
            $productData['group_id'] = $this->_truncateText(
                $this->_sanitizeText($productData['group_id']),
                70,
            );
        }

        // Truncate store_name
        if (isset($productData['store_name'])) {
            $productData['store_name'] = $this->_truncateText(
                $this->_sanitizeText($productData['store_name']),
                70,
            );
        }

        // Ensure price has currency in correct format
        if (isset($productData['price']) && is_numeric($productData['price'])) {
            $currency = $productData['currency'] ?? 'AUD';
            $productData['price'] = $this->_formatPriceOpenai((float) $productData['price'], $currency);
            unset($productData['currency']);
        }

        // Same for sale_price
        if (isset($productData['sale_price']) && is_numeric($productData['sale_price'])) {
            $currency = $productData['currency'] ?? 'AUD';
            $productData['sale_price'] = $this->_formatPriceOpenai((float) $productData['sale_price'], $currency);
        }

        // Transform boolean fields to actual booleans for JSONL
        foreach (['is_eligible_search', 'is_eligible_checkout', 'listing_has_variations', 'accepts_returns', 'accepts_exchanges'] as $boolField) {
            if (isset($productData[$boolField])) {
                $productData[$boolField] = $this->_toBool($productData[$boolField]);
            }
        }

        // Ensure return_window is integer
        if (isset($productData['return_window'])) {
            $productData['return_window'] = (int) $productData['return_window'];
        }

        // Transform gender
        if (isset($productData['gender'])) {
            $productData['gender'] = $this->_transformGender($productData['gender']);
        }

        // Transform age_group
        if (isset($productData['age_group'])) {
            $productData['age_group'] = $this->_transformAgeGroup($productData['age_group']);
        }

        return $productData;
    }

    #[\Override]
    public function validateProductData(array $productData): array
    {
        $errors = parent::validateProductData($productData);

        // Validate availability values
        $validAvailability = ['in_stock', 'out_of_stock', 'pre_order', 'backorder', 'unknown'];
        if (isset($productData['availability']) && !in_array($productData['availability'], $validAvailability)) {
            $errors[] = 'Invalid availability value: ' . $productData['availability'];
        }

        // Validate condition values
        $validCondition = ['new', 'refurbished', 'used'];
        if (isset($productData['condition']) && !in_array($productData['condition'], $validCondition)) {
            $errors[] = 'Invalid condition value: ' . $productData['condition'];
        }

        // If checkout eligible, require privacy policy and ToS
        if (!empty($productData['is_eligible_checkout']) && $productData['is_eligible_checkout'] === true) {
            if (empty($productData['seller_privacy_policy'])) {
                $errors[] = 'seller_privacy_policy is required when checkout is enabled';
            }
            if (empty($productData['seller_tos'])) {
                $errors[] = 'seller_tos is required when checkout is enabled';
            }
            if (empty($productData['is_eligible_search']) || $productData['is_eligible_search'] !== true) {
                $errors[] = 'is_eligible_search must be true when checkout is enabled';
            }
        }

        // If pre_order, require availability_date
        if (isset($productData['availability']) && $productData['availability'] === 'pre_order') {
            if (empty($productData['availability_date'])) {
                $errors[] = 'availability_date is required for pre_order items';
            }
        }

        // Validate URL fields
        $urlFields = ['url', 'image_url', 'video_url', 'seller_url', 'seller_privacy_policy', 'seller_tos', 'return_policy'];
        foreach ($urlFields as $field) {
            if (!empty($productData[$field]) && !filter_var($productData[$field], FILTER_VALIDATE_URL)) {
                $errors[] = "Invalid URL for {$field}: " . $productData[$field];
            }
        }

        return $errors;
    }

    /**
     * Transform availability to OpenAI format
     */
    protected function _transformAvailabilityOpenai(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'in_stock' : 'out_of_stock';
        }

        $value = strtolower(trim((string) $value));

        $mapping = [
            '1' => 'in_stock',
            '0' => 'out_of_stock',
            'true' => 'in_stock',
            'false' => 'out_of_stock',
            'in stock' => 'in_stock',
            'instock' => 'in_stock',
            'in_stock' => 'in_stock',
            'out of stock' => 'out_of_stock',
            'outofstock' => 'out_of_stock',
            'out_of_stock' => 'out_of_stock',
            'preorder' => 'pre_order',
            'pre-order' => 'pre_order',
            'pre_order' => 'pre_order',
            'backorder' => 'backorder',
            'back-order' => 'backorder',
            'back_order' => 'backorder',
            'unknown' => 'unknown',
        ];

        return $mapping[$value] ?? 'unknown';
    }

    /**
     * Format price for OpenAI (ISO 4217 format)
     */
    protected function _formatPriceOpenai(float $price, string $currency): string
    {
        return sprintf('%.2f %s', $price, strtoupper($currency));
    }

    /**
     * Convert value to actual boolean for JSONL output
     */
    protected function _toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}
