<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Facebook/Meta Platform Adapter
 *
 * Implements Facebook Commerce/Meta Catalog feed specification
 * @see https://www.facebook.com/business/help/120325381656392
 */
class Maho_FeedManager_Model_Platform_Facebook extends Maho_FeedManager_Model_Platform_AbstractAdapter
{
    protected string $_code = 'facebook';
    protected string $_name = 'Facebook / Meta';
    protected array $_supportedFormats = ['xml', 'csv'];
    protected string $_defaultFormat = 'xml';
    protected string $_rootElement = 'feed';
    protected string $_itemElement = 'item';
    protected ?string $_taxonomyFile = 'taxonomy/google_product_taxonomy.txt'; // Facebook uses Google taxonomy

    protected array $_namespaces = [
        'xmlns' => 'http://www.w3.org/2005/Atom',
        'xmlns:g' => 'http://base.google.com/ns/1.0',
    ];

    protected array $_requiredAttributes = [
        'id' => [
            'label' => 'ID',
            'required' => true,
            'description' => 'Unique product identifier (max 100 characters)',
        ],
        'title' => [
            'label' => 'Title',
            'required' => true,
            'description' => 'Product title (max 200 characters)',
        ],
        'description' => [
            'label' => 'Description',
            'required' => true,
            'description' => 'Product description (max 9999 characters)',
        ],
        'availability' => [
            'label' => 'Availability',
            'required' => true,
            'description' => 'in stock, out of stock, available for order, discontinued',
        ],
        'condition' => [
            'label' => 'Condition',
            'required' => true,
            'description' => 'new, refurbished, used',
        ],
        'price' => [
            'label' => 'Price',
            'required' => true,
            'description' => 'Product price with currency (e.g., 25.00 USD)',
        ],
        'link' => [
            'label' => 'Link',
            'required' => true,
            'description' => 'Product page URL',
        ],
        'image_link' => [
            'label' => 'Image Link',
            'required' => true,
            'description' => 'Main product image URL (min 500x500px)',
        ],
        'brand' => [
            'label' => 'Brand',
            'required' => true,
            'description' => 'Product brand name',
        ],
    ];

    protected array $_optionalAttributes = [
        'google_product_category' => [
            'label' => 'Google Product Category',
            'required' => false,
            'description' => 'Google taxonomy category',
        ],
        'fb_product_category' => [
            'label' => 'Facebook Product Category',
            'required' => false,
            'description' => 'Facebook-specific category',
        ],
        'sale_price' => [
            'label' => 'Sale Price',
            'required' => false,
            'description' => 'Sale price with currency',
        ],
        'sale_price_effective_date' => [
            'label' => 'Sale Price Effective Date',
            'required' => false,
            'description' => 'Date range for sale price (ISO 8601)',
        ],
        'gtin' => [
            'label' => 'GTIN',
            'required' => false,
            'description' => 'Global Trade Item Number',
        ],
        'mpn' => [
            'label' => 'MPN',
            'required' => false,
            'description' => 'Manufacturer Part Number',
        ],
        'additional_image_link' => [
            'label' => 'Additional Image Links',
            'required' => false,
            'description' => 'Additional product images (max 10)',
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
        'size_system' => [
            'label' => 'Size System',
            'required' => false,
            'description' => 'Size system used (US, UK, EU, DE, FR, etc.)',
        ],
        'size_type' => [
            'label' => 'Size Type',
            'required' => false,
            'description' => 'Cut of clothing (regular, petite, plus, big_and_tall, maternity)',
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
        'quantity_to_sell_on_facebook' => [
            'label' => 'Quantity to Sell',
            'required' => false,
            'description' => 'Stock quantity available for Facebook',
        ],
        'inventory' => [
            'label' => 'Inventory',
            'required' => false,
            'description' => 'Total inventory count',
        ],
        'rich_text_description' => [
            'label' => 'Rich Text Description',
            'required' => false,
            'description' => 'HTML-formatted description',
        ],
        'product_type' => [
            'label' => 'Product Type',
            'required' => false,
            'description' => 'Your own product categorization',
        ],
        'short_description' => [
            'label' => 'Short Description',
            'required' => false,
            'description' => 'Brief product description',
        ],
        'video' => [
            'label' => 'Video',
            'required' => false,
            'description' => 'Product video URL',
        ],
    ];

    protected array $_defaultMappings = [
        'id' => ['source_type' => 'attribute', 'source_value' => 'sku'],
        'title' => ['source_type' => 'attribute', 'source_value' => 'name'],
        'description' => ['source_type' => 'attribute', 'source_value' => 'description', 'use_parent' => 'if_empty'],
        'link' => ['source_type' => 'attribute', 'source_value' => 'url', 'use_parent' => 'always'],
        'image_link' => ['source_type' => 'attribute', 'source_value' => 'image', 'use_parent' => 'if_empty'],
        'additional_image_link' => ['source_type' => 'attribute', 'source_value' => 'additional_images_csv', 'use_parent' => 'if_empty'],
        'price' => ['source_type' => 'attribute', 'source_value' => 'price'],
        'sale_price' => ['source_type' => 'attribute', 'source_value' => 'special_price'],
        'sale_price_effective_date' => ['source_type' => 'static', 'source_value' => ''],
        'brand' => ['source_type' => 'attribute', 'source_value' => 'manufacturer', 'use_parent' => 'if_empty'],
        'gtin' => ['source_type' => 'attribute', 'source_value' => 'gtin'],
        'mpn' => ['source_type' => 'attribute', 'source_value' => 'mpn'],
        'condition' => ['source_type' => 'static', 'source_value' => 'new'],
        'availability' => ['source_type' => 'rule', 'source_value' => 'stock_status'],
        'google_product_category' => ['source_type' => 'taxonomy', 'source_value' => 'facebook', 'use_parent' => 'if_empty'],
        'fb_product_category' => ['source_type' => 'static', 'source_value' => ''],
        'product_type' => ['source_type' => 'attribute', 'source_value' => 'category_path', 'use_parent' => 'if_empty'],
        'item_group_id' => ['source_type' => 'attribute', 'source_value' => 'sku', 'use_parent' => 'always'],
        'color' => ['source_type' => 'attribute', 'source_value' => 'color'],
        'size' => ['source_type' => 'attribute', 'source_value' => 'size'],
        'size_system' => ['source_type' => 'static', 'source_value' => ''],
        'size_type' => ['source_type' => 'static', 'source_value' => ''],
        'gender' => ['source_type' => 'attribute', 'source_value' => 'gender'],
        'age_group' => ['source_type' => 'static', 'source_value' => ''],
        'material' => ['source_type' => 'attribute', 'source_value' => 'material'],
        'pattern' => ['source_type' => 'static', 'source_value' => ''],
        'shipping' => ['source_type' => 'static', 'source_value' => ''],
        'shipping_weight' => ['source_type' => 'attribute', 'source_value' => 'weight'],
        'custom_label_0' => ['source_type' => 'static', 'source_value' => ''],
        'custom_label_1' => ['source_type' => 'static', 'source_value' => ''],
        'custom_label_2' => ['source_type' => 'static', 'source_value' => ''],
        'custom_label_3' => ['source_type' => 'static', 'source_value' => ''],
        'custom_label_4' => ['source_type' => 'static', 'source_value' => ''],
        'quantity_to_sell_on_facebook' => ['source_type' => 'attribute', 'source_value' => 'qty'],
        'inventory' => ['source_type' => 'attribute', 'source_value' => 'qty'],
        'rich_text_description' => ['source_type' => 'static', 'source_value' => ''],
        'short_description' => ['source_type' => 'attribute', 'source_value' => 'short_description'],
        'video' => ['source_type' => 'static', 'source_value' => ''],
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

        // Sanitize and truncate title (Facebook allows 200 chars)
        if (isset($productData['title'])) {
            $productData['title'] = $this->_truncateText(
                $this->_sanitizeText($productData['title']),
                200,
            );
        }

        // Truncate ID to 100 characters
        if (isset($productData['id'])) {
            $productData['id'] = $this->_truncateText((string) $productData['id'], 100);
        }

        // Sanitize and truncate description
        if (isset($productData['description'])) {
            $productData['description'] = $this->_truncateText(
                $this->_sanitizeText($productData['description']),
                9999,
            );
        }

        // Extract currency before formatting prices
        $currency = $productData['currency'] ?? Mage::app()->getStore()->getBaseCurrencyCode();
        unset($productData['currency']);

        // Ensure price has currency
        if (isset($productData['price']) && is_numeric($productData['price'])) {
            $productData['price'] = $this->_formatPrice((float) $productData['price'], $currency);
        }

        // Same for sale_price
        if (isset($productData['sale_price']) && is_numeric($productData['sale_price'])) {
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

        // Ensure inventory is integer
        if (isset($productData['inventory'])) {
            $productData['inventory'] = (int) $productData['inventory'];
        }

        return $productData;
    }

    #[\Override]
    public function validateProductData(array $productData): array
    {
        $errors = parent::validateProductData($productData);

        // Validate availability values
        $validAvailability = ['in stock', 'out of stock', 'available for order', 'discontinued', 'preorder', 'pending'];
        if (isset($productData['availability']) && !in_array($productData['availability'], $validAvailability)) {
            $errors[] = 'Invalid availability value: ' . $productData['availability'];
        }

        // Validate condition values
        $validCondition = ['new', 'refurbished', 'used'];
        if (isset($productData['condition']) && !in_array($productData['condition'], $validCondition)) {
            $errors[] = 'Invalid condition value: ' . $productData['condition'];
        }

        // Validate ID length
        if (isset($productData['id']) && strlen($productData['id']) > 100) {
            $errors[] = 'ID must be 100 characters or less';
        }

        // Validate title length
        if (isset($productData['title']) && mb_strlen($productData['title']) > 200) {
            $errors[] = 'Title must be 200 characters or less';
        }

        return $errors;
    }

    /**
     * Transform stock status to Facebook availability
     * Note: Facebook uses spaces in availability values
     */
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
            'available for order' => 'available for order',
            'preorder' => 'preorder',
            'backorder' => 'available for order',
            'pending' => 'pending',
            'discontinued' => 'discontinued',
            'yes' => 'in stock',
            'no' => 'out of stock',
        ];

        $normalized = strtolower(trim((string) $value));
        return $map[$normalized] ?? 'out of stock';
    }

    /**
     * Get attributes that need the g: namespace prefix (for XML format)
     */
    #[\Override]
    public function getNamespacedAttributes(): array
    {
        // Facebook XML format uses g: prefix for most attributes
        return array_diff(
            array_keys($this->getAllAttributes()),
            ['id', 'title', 'link'],
        );
    }
}
