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
 * Google Shopping Platform Adapter
 *
 * Implements Google Merchant Centre feed specification
 * @see https://support.google.com/merchants/answer/7052112
 */
class Maho_FeedManager_Model_Platform_Google extends Maho_FeedManager_Model_Platform_AbstractAdapter
{
    protected string $_code = 'google';
    protected string $_name = 'Google Shopping';
    protected array $_supportedFormats = ['xml'];
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
            'description' => 'Unique product identifier',
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
            'description' => 'Main product image URL',
        ],
        'availability' => [
            'label' => 'Availability',
            'required' => true,
            'description' => 'in_stock, out_of_stock, preorder, backorder',
        ],
        'price' => [
            'label' => 'Price',
            'required' => true,
            'description' => 'Product price with currency (e.g., 25.00 USD)',
        ],
        'brand' => [
            'label' => 'Brand',
            'required' => true,
            'description' => 'Product brand name',
        ],
        'google_product_category' => [
            'label' => 'Google Product Category',
            'required' => true,
            'description' => 'Google taxonomy category ID or path',
        ],
    ];

    protected array $_optionalAttributes = [
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
            'description' => 'Shipping cost and details',
        ],
        'shipping_weight' => [
            'label' => 'Shipping Weight',
            'required' => false,
            'description' => 'Product weight with unit (e.g., 2.5 kg)',
        ],
        'shipping_length' => [
            'label' => 'Shipping Length',
            'required' => false,
            'description' => 'Package length with unit',
        ],
        'shipping_width' => [
            'label' => 'Shipping Width',
            'required' => false,
            'description' => 'Package width with unit',
        ],
        'shipping_height' => [
            'label' => 'Shipping Height',
            'required' => false,
            'description' => 'Package height with unit',
        ],
        'tax' => [
            'label' => 'Tax',
            'required' => false,
            'description' => 'Tax rate information',
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
        'identifier_exists' => [
            'label' => 'Identifier Exists',
            'required' => false,
            'description' => 'yes/no - set to no if no GTIN/MPN/brand',
        ],
        'product_type' => [
            'label' => 'Product Type',
            'required' => false,
            'description' => 'Your own product categorization',
        ],
        'energy_efficiency_class' => [
            'label' => 'Energy Efficiency Class',
            'required' => false,
            'description' => 'EU energy label class',
        ],
        'min_energy_efficiency_class' => [
            'label' => 'Min Energy Efficiency Class',
            'required' => false,
            'description' => 'Minimum energy class on scale',
        ],
        'max_energy_efficiency_class' => [
            'label' => 'Max Energy Efficiency Class',
            'required' => false,
            'description' => 'Maximum energy class on scale',
        ],
        'multipack' => [
            'label' => 'Multipack',
            'required' => false,
            'description' => 'Number of items in multipack',
        ],
        'is_bundle' => [
            'label' => 'Is Bundle',
            'required' => false,
            'description' => 'yes/no - is this a bundle',
        ],
        'adult' => [
            'label' => 'Adult',
            'required' => false,
            'description' => 'yes/no - adult content',
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
        'google_product_category' => ['source_type' => 'taxonomy', 'source_value' => 'google', 'use_parent' => 'if_empty'],
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
        'shipping_length' => ['source_type' => 'static', 'source_value' => ''],
        'shipping_width' => ['source_type' => 'static', 'source_value' => ''],
        'shipping_height' => ['source_type' => 'static', 'source_value' => ''],
        'tax' => ['source_type' => 'static', 'source_value' => ''],
        'custom_label_0' => ['source_type' => 'static', 'source_value' => ''],
        'custom_label_1' => ['source_type' => 'static', 'source_value' => ''],
        'custom_label_2' => ['source_type' => 'static', 'source_value' => ''],
        'custom_label_3' => ['source_type' => 'static', 'source_value' => ''],
        'custom_label_4' => ['source_type' => 'static', 'source_value' => ''],
        'identifier_exists' => ['source_type' => 'static', 'source_value' => ''],
        'energy_efficiency_class' => ['source_type' => 'static', 'source_value' => ''],
        'min_energy_efficiency_class' => ['source_type' => 'static', 'source_value' => ''],
        'max_energy_efficiency_class' => ['source_type' => 'static', 'source_value' => ''],
        'multipack' => ['source_type' => 'static', 'source_value' => ''],
        'is_bundle' => ['source_type' => 'static', 'source_value' => ''],
        'adult' => ['source_type' => 'static', 'source_value' => ''],
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
            $currency = $productData['currency'] ?? 'AUD';
            $productData['price'] = $this->_formatPrice((float) $productData['price'], $currency);
            unset($productData['currency']);
        }

        // Same for sale_price
        if (isset($productData['sale_price']) && is_numeric($productData['sale_price'])) {
            $currency = $productData['currency'] ?? 'AUD';
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
                $errors[] = 'Products without GTIN, MPN, and brand must have identifier_exists set to "no"';
            }
        }

        // Validate price format
        if (isset($productData['price']) && !preg_match('/^\d+\.\d{2}\s[A-Z]{3}$/', $productData['price'])) {
            $errors[] = 'Price must be in format "XX.XX CUR" (e.g., 25.00 USD)';
        }

        // Validate availability values
        $validAvailability = ['in_stock', 'out_of_stock', 'preorder', 'backorder'];
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

    /**
     * Transform stock status to Google availability (uses underscore format)
     */
    #[\Override]
    protected function _transformAvailability(mixed $value, bool $useUnderscore = true): string
    {
        return parent::_transformAvailability($value, true);
    }

    /**
     * Get attributes that need the g: namespace prefix
     */
    public function getNamespacedAttributes(): array
    {
        // All Google Shopping attributes except id, title, link need g: prefix
        return array_diff(
            array_keys($this->getAllAttributes()),
            ['id', 'title', 'link'],
        );
    }
}
