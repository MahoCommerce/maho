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
 * Microsoft Bing Shopping Platform Adapter
 *
 * Implements Microsoft Merchant Center feed specification
 * Compatible with Google Shopping format (Bing accepts Google-formatted feeds)
 * @see https://learn.microsoft.com/en-us/advertising/shopping-content/products-resource
 */
class Maho_FeedManager_Model_Platform_Bing extends Maho_FeedManager_Model_Platform_AbstractAdapter
{
    protected string $_code = 'bing';
    protected string $_name = 'Bing Shopping';
    protected array $_supportedFormats = ['xml', 'csv'];
    protected string $_defaultFormat = 'xml';
    protected string $_rootElement = 'feed';
    protected string $_itemElement = 'entry';
    protected ?string $_taxonomyFile = 'taxonomy/google_product_taxonomy.txt';

    protected array $_namespaces = [
        'xmlns' => 'http://www.w3.org/2005/Atom',
        'xmlns:g' => 'http://base.google.com/ns/1.0',
    ];

    protected array $_requiredAttributes = [
        'id' => [
            'label' => 'ID',
            'required' => true,
            'description' => 'Unique product identifier (SKU)',
        ],
        'title' => [
            'label' => 'Title',
            'required' => true,
            'description' => 'Product title (max 150 characters)',
        ],
        'description' => [
            'label' => 'Description',
            'required' => true,
            'description' => 'Product description (max 5000 characters)',
        ],
        'link' => [
            'label' => 'Link',
            'required' => true,
            'description' => 'Product page URL',
        ],
        'image_link' => [
            'label' => 'Image Link',
            'required' => true,
            'description' => 'Main product image URL (min 100x100px, no watermarks)',
        ],
        'price' => [
            'label' => 'Price',
            'required' => true,
            'description' => 'Product price with currency (e.g., 25.00 USD)',
        ],
        'availability' => [
            'label' => 'Availability',
            'required' => true,
            'description' => 'in stock, out of stock, preorder',
        ],
    ];

    protected array $_optionalAttributes = [
        'brand' => [
            'label' => 'Brand',
            'required' => false,
            'description' => 'Product brand name (required for some categories)',
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
            'description' => 'new, refurbished, used (defaults to new)',
        ],
        'product_category' => [
            'label' => 'Product Category',
            'required' => false,
            'description' => 'Google Product Category ID or path',
        ],
        'product_type' => [
            'label' => 'Product Type',
            'required' => false,
            'description' => 'Your own product categorization',
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
            'description' => 'Shipping cost and details (required in Germany)',
        ],
        'shipping_weight' => [
            'label' => 'Shipping Weight',
            'required' => false,
            'description' => 'Product weight with unit (e.g., 2.5 kg)',
        ],
        'identifier_exists' => [
            'label' => 'Identifier Exists',
            'required' => false,
            'description' => 'yes/no - set to no if no GTIN/MPN/brand',
        ],
        'custom_label_0' => [
            'label' => 'Custom Label 0',
            'required' => false,
            'description' => 'Custom grouping label for campaigns',
        ],
        'custom_label_1' => [
            'label' => 'Custom Label 1',
            'required' => false,
            'description' => 'Custom grouping label for campaigns',
        ],
        'custom_label_2' => [
            'label' => 'Custom Label 2',
            'required' => false,
            'description' => 'Custom grouping label for campaigns',
        ],
        'custom_label_3' => [
            'label' => 'Custom Label 3',
            'required' => false,
            'description' => 'Custom grouping label for campaigns',
        ],
        'custom_label_4' => [
            'label' => 'Custom Label 4',
            'required' => false,
            'description' => 'Custom grouping label for campaigns',
        ],
        'seller_name' => [
            'label' => 'Seller Name',
            'required' => false,
            'description' => 'Name of the seller (for marketplaces)',
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
        'availability' => ['source_type' => 'rule', 'source_value' => 'stock_status'],
        'brand' => ['source_type' => 'attribute', 'source_value' => 'manufacturer', 'use_parent' => 'if_empty'],
        'gtin' => ['source_type' => 'attribute', 'source_value' => 'gtin'],
        'mpn' => ['source_type' => 'attribute', 'source_value' => 'mpn'],
        'condition' => ['source_type' => 'static', 'source_value' => 'new'],
        'product_category' => ['source_type' => 'taxonomy', 'source_value' => 'bing', 'use_parent' => 'if_empty'],
        'product_type' => ['source_type' => 'attribute', 'source_value' => 'category_path', 'use_parent' => 'if_empty'],
        'item_group_id' => ['source_type' => 'attribute', 'source_value' => 'sku', 'use_parent' => 'always'],
        'color' => ['source_type' => 'attribute', 'source_value' => 'color'],
        'size' => ['source_type' => 'attribute', 'source_value' => 'size'],
        'gender' => ['source_type' => 'attribute', 'source_value' => 'gender'],
        'age_group' => ['source_type' => 'static', 'source_value' => ''],
        'material' => ['source_type' => 'attribute', 'source_value' => 'material'],
        'pattern' => ['source_type' => 'static', 'source_value' => ''],
        'shipping' => ['source_type' => 'static', 'source_value' => ''],
        'shipping_weight' => ['source_type' => 'attribute', 'source_value' => 'weight'],
        'identifier_exists' => ['source_type' => 'static', 'source_value' => ''],
        'custom_label_0' => ['source_type' => 'static', 'source_value' => ''],
        'custom_label_1' => ['source_type' => 'static', 'source_value' => ''],
        'custom_label_2' => ['source_type' => 'static', 'source_value' => ''],
        'custom_label_3' => ['source_type' => 'static', 'source_value' => ''],
        'custom_label_4' => ['source_type' => 'static', 'source_value' => ''],
        'seller_name' => ['source_type' => 'attribute', 'source_value' => 'store_name'],
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
                150,
            );
        }

        // Sanitize and truncate description
        if (isset($productData['description'])) {
            $productData['description'] = $this->_truncateText(
                $this->_sanitizeText($productData['description']),
                5000,
            );
        }

        // Ensure price has currency
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

        return $productData;
    }

    #[\Override]
    public function validateProductData(array $productData): array
    {
        $errors = parent::validateProductData($productData);

        // Validate identifier_exists logic
        $hasGtin = !empty($productData['gtin']);
        $hasMpn = !empty($productData['mpn']);
        $hasBrand = !empty($productData['brand']);

        if (!$hasGtin && !$hasMpn && !$hasBrand) {
            if (empty($productData['identifier_exists']) || $productData['identifier_exists'] !== 'no') {
                $errors[] = 'Products without GTIN, MPN, and brand should have identifier_exists set to "no"';
            }
        }

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

    /**
     * Get attributes that need the g: namespace prefix in XML
     */
    public function getNamespacedAttributes(): array
    {
        return array_diff(
            array_keys($this->getAllAttributes()),
            ['id', 'title', 'link'],
        );
    }
}
