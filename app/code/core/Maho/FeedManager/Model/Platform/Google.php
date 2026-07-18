<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

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
            'description' => 'Unique product identifier (max 50 unicode characters)',
        ],
        'title' => [
            'label' => 'Title',
            'required' => true,
            'description' => 'Plain-text product name (max 150 characters)',
        ],
        'description' => [
            'label' => 'Description',
            'required' => true,
            'description' => 'Plain-text product description (max 5000 characters)',
        ],
        'link' => [
            'label' => 'Link',
            'required' => true,
            'description' => 'Product landing page URL (HTTP/HTTPS, RFC 2396/1738 compliant)',
        ],
        'image_link' => [
            'label' => 'Image Link',
            'required' => true,
            'description' => 'Main product image URL (min 500x500px; JPEG/PNG/WebP/GIF/BMP/TIFF)',
        ],
        'availability' => [
            'label' => 'Availability',
            'required' => true,
            'description' => 'in_stock, out_of_stock, preorder, backorder',
        ],
        'price' => [
            'label' => 'Price',
            'required' => true,
            'description' => 'Product price with ISO 4217 currency (e.g., 25.00 USD)',
        ],
        'brand' => [
            'label' => 'Brand',
            'required' => true,
            'description' => 'Manufacturer name (max 70 characters; required for new products)',
        ],
        'google_product_category' => [
            'label' => 'Google Product Category',
            'required' => true,
            'description' => 'Google taxonomy category ID or full path',
        ],
    ];

    protected array $_optionalAttributes = [
        // Product identifiers (conditionally required)
        'gtin' => [
            'label' => 'GTIN',
            'required' => false,
            'description' => 'Global Trade Item Number (UPC/EAN/JAN/ISBN/ITF-14); required when the product has one assigned',
        ],
        'mpn' => [
            'label' => 'MPN',
            'required' => false,
            'description' => 'Manufacturer Part Number; required when no GTIN is available',
        ],
        'identifier_exists' => [
            'label' => 'Identifier Exists',
            'required' => false,
            'description' => 'yes / no — set to no if the product has neither GTIN nor MPN nor brand',
        ],
        // Condition & state
        'condition' => [
            'label' => 'Condition',
            'required' => false,
            'description' => 'new, refurbished, used; required when the condition is not new',
        ],
        'adult' => [
            'label' => 'Adult',
            'required' => false,
            'description' => 'yes / no — required if the product is sexually suggestive',
        ],
        // Pricing
        'sale_price' => [
            'label' => 'Sale Price',
            'required' => false,
            'description' => 'Discounted price with ISO 4217 currency (requires non-sale price too)',
        ],
        'sale_price_effective_date' => [
            'label' => 'Sale Price Effective Date',
            'required' => false,
            'description' => 'ISO 8601 date range during which the sale price applies (max 51 chars)',
        ],
        'cost_of_goods_sold' => [
            'label' => 'Cost of Goods Sold',
            'required' => false,
            'description' => 'COGS for margin analysis (numeric + ISO 4217 currency)',
        ],
        'expiration_date' => [
            'label' => 'Expiration Date',
            'required' => false,
            'description' => 'ISO 8601 date when the product stops being shown (max 30 days in the future)',
        ],
        'unit_pricing_measure' => [
            'label' => 'Unit Pricing Measure',
            'required' => false,
            'description' => 'Product dimension/weight for unit pricing (number + unit; e.g., 750 ml)',
        ],
        'unit_pricing_base_measure' => [
            'label' => 'Unit Pricing Base Measure',
            'required' => false,
            'description' => 'Base unit for unit pricing (integer + unit; e.g., 100 ml)',
        ],
        'auto_pricing_min_price' => [
            'label' => 'Auto Pricing Min Price',
            'required' => false,
            'description' => 'Lowest price allowed for dynamic pricing campaigns (numeric + ISO 4217)',
        ],
        // Availability date
        'availability_date' => [
            'label' => 'Availability Date',
            'required' => false,
            'description' => 'ISO 8601 date a preorder/backorder product becomes available (required when availability is preorder or backorder)',
        ],
        // Imagery & media
        'additional_image_link' => [
            'label' => 'Additional Image Link',
            'required' => false,
            'description' => 'Up to 10 additional product image URLs (max 2000 chars each)',
        ],
        'lifestyle_image_link' => [
            'label' => 'Lifestyle Image Link',
            'required' => false,
            'description' => 'Lifestyle/context image URL (RFC 3986 compliant, max 2000 chars)',
        ],
        'video_link' => [
            'label' => 'Video Link',
            'required' => false,
            'description' => 'Product video URL (6–240 s, 720p+, MP4/MPG/WMV/AVI/MOV/FLV)',
        ],
        'mobile_link' => [
            'label' => 'Mobile Link',
            'required' => false,
            'description' => 'Mobile-optimised landing page URL (max 2000 chars)',
        ],
        // Category
        'product_type' => [
            'label' => 'Product Type',
            'required' => false,
            'description' => 'Your own product categorisation (max 750 chars; full path recommended)',
        ],
        // Apparel / variant attributes
        'color' => [
            'label' => 'Color',
            'required' => false,
            'description' => 'Product colour(s); required for apparel and multi-colour products (max 100 chars total, 40 per colour)',
        ],
        'size' => [
            'label' => 'Size',
            'required' => false,
            'description' => 'Product size; required for apparel (max 100 chars)',
        ],
        'size_type' => [
            'label' => 'Size Type',
            'required' => false,
            'description' => 'regular, petite, maternity, big, tall, plus',
        ],
        'size_system' => [
            'label' => 'Size System',
            'required' => false,
            'description' => 'US, UK, EU, DE, FR, JP, CN, IT, BR, MEX, AU',
        ],
        'gender' => [
            'label' => 'Gender',
            'required' => false,
            'description' => 'male, female, unisex; required for apparel',
        ],
        'age_group' => [
            'label' => 'Age Group',
            'required' => false,
            'description' => 'newborn, infant, toddler, kids, adult; required for apparel',
        ],
        'material' => [
            'label' => 'Material',
            'required' => false,
            'description' => 'Product material (max 200 chars); required for variants',
        ],
        'pattern' => [
            'label' => 'Pattern',
            'required' => false,
            'description' => 'Product pattern (max 100 chars); required for variant patterns',
        ],
        'item_group_id' => [
            'label' => 'Item Group ID',
            'required' => false,
            'description' => 'Groups variants of the same product (max 50 alphanumeric chars); required for variants',
        ],
        'item_group_title' => [
            'label' => 'Item Group Title',
            'required' => false,
            'description' => 'Parent product name for the variant group (max 150 chars)',
        ],
        // Multipack / bundle
        'multipack' => [
            'label' => 'Multipack',
            'required' => false,
            'description' => 'Number of identical items in a multipack (integer); required in some regions',
        ],
        'is_bundle' => [
            'label' => 'Is Bundle',
            'required' => false,
            'description' => 'yes / no — is this a merchant-defined bundle; required in some regions',
        ],
        // Physical dimensions
        'product_length' => [
            'label' => 'Product Length',
            'required' => false,
            'description' => 'Product length (number + unit cm/in, 1–3000)',
        ],
        'product_width' => [
            'label' => 'Product Width',
            'required' => false,
            'description' => 'Product width (number + unit cm/in, 1–3000)',
        ],
        'product_height' => [
            'label' => 'Product Height',
            'required' => false,
            'description' => 'Product height (number + unit cm/in, 1–3000)',
        ],
        'product_weight' => [
            'label' => 'Product Weight',
            'required' => false,
            'description' => 'Product mass (number + unit lb/oz/g/kg, 0–2000)',
        ],
        // Rich product detail
        'product_detail' => [
            'label' => 'Product Detail',
            'required' => false,
            'description' => 'Technical specifications (section_name/attribute_name/attribute_value, max 1000 chars value)',
        ],
        'product_highlight' => [
            'label' => 'Product Highlight',
            'required' => false,
            'description' => 'Key product benefits (max 150 chars each; 2–100 highlights per product)',
        ],
        'document_link' => [
            'label' => 'Document Link',
            'required' => false,
            'description' => 'PDF documentation URL (up to 5 links, max 2000 chars each)',
        ],
        'related_product' => [
            'label' => 'Related Product',
            'required' => false,
            'description' => 'Related product references (max 30 with type/ID/GTIN)',
        ],
        'question_and_answer' => [
            'label' => 'Question and Answer',
            'required' => false,
            'description' => 'Product Q&A pairs (max 30 pairs, max 1000 chars each)',
        ],
        // Shipping & tax
        'shipping' => [
            'label' => 'Shipping',
            'required' => false,
            'description' => 'Shipping cost and rule details',
        ],
        'shipping_weight' => [
            'label' => 'Shipping Weight',
            'required' => false,
            'description' => 'Weight used for shipping calculations (e.g., 2.5 kg)',
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
        // Energy labels (CH/NO/UK)
        'energy_efficiency_class' => [
            'label' => 'Energy Efficiency Class',
            'required' => false,
            'description' => 'A+++, A++, A+, A, B, C, D, E, F, G',
        ],
        'min_energy_efficiency_class' => [
            'label' => 'Min Energy Efficiency Class',
            'required' => false,
            'description' => 'Minimum energy class on the scale',
        ],
        'max_energy_efficiency_class' => [
            'label' => 'Max Energy Efficiency Class',
            'required' => false,
            'description' => 'Maximum energy class on the scale',
        ],
        // Campaign / promotion
        'promotion_id' => [
            'label' => 'Promotion ID',
            'required' => false,
            'description' => 'Promotion link identifier (max 50 alphanumeric chars, up to 10 IDs)',
        ],
        'ads_redirect' => [
            'label' => 'Ads Redirect',
            'required' => false,
            'description' => 'Campaign tracking URL on the same registered domain (max 2000 chars)',
        ],
        'custom_label_0' => [
            'label' => 'Custom Label 0',
            'required' => false,
            'description' => 'Campaign grouping label (max 100 chars; up to 5 labels, 1000 unique values)',
        ],
        'custom_label_1' => [
            'label' => 'Custom Label 1',
            'required' => false,
            'description' => 'Campaign grouping label (max 100 chars)',
        ],
        'custom_label_2' => [
            'label' => 'Custom Label 2',
            'required' => false,
            'description' => 'Campaign grouping label (max 100 chars)',
        ],
        'custom_label_3' => [
            'label' => 'Custom Label 3',
            'required' => false,
            'description' => 'Campaign grouping label (max 100 chars)',
        ],
        'custom_label_4' => [
            'label' => 'Custom Label 4',
            'required' => false,
            'description' => 'Campaign grouping label (max 100 chars)',
        ],
    ];

    protected array $_defaultMappings = [
        // Required
        'id' => [
            'source_type' => 'attribute',
            'source_value' => 'sku',
            'transformers' => 'truncate:max_length=50',
        ],
        'title' => [
            'source_type' => 'attribute',
            'source_value' => 'name',
            'transformers' => 'truncate:max_length=150',
        ],
        'description' => [
            'source_type' => 'attribute',
            'source_value' => 'description',
            'use_parent' => 'if_empty',
            'transformers' => 'strip_tags|truncate:max_length=5000',
        ],
        'link' => ['source_type' => 'attribute', 'source_value' => 'url', 'use_parent' => 'always'],
        'image_link' => ['source_type' => 'attribute', 'source_value' => 'image', 'use_parent' => 'if_empty'],
        // is_in_stock returns 1 / 0; Google wants "in_stock" / "out_of_stock".
        'availability' => [
            'source_type' => 'attribute',
            'source_value' => 'is_in_stock',
            'transformers' => 'conditional:operator=eq,compare_value=1,true_value=in_stock,false_value=out_of_stock',
        ],
        'price' => ['source_type' => 'attribute', 'source_value' => 'price'],
        'brand' => [
            'source_type' => 'attribute',
            'source_value' => 'manufacturer',
            'use_parent' => 'if_empty',
            'transformers' => 'truncate:max_length=70',
        ],
        'google_product_category' => ['source_type' => 'taxonomy', 'source_value' => 'google', 'use_parent' => 'if_empty'],

        // Identifiers
        'gtin' => [
            'source_type' => 'attribute',
            'source_value' => 'gtin',
            'transformers' => 'truncate:max_length=50',
        ],
        'mpn' => [
            'source_type' => 'attribute',
            'source_value' => 'mpn',
            'transformers' => 'truncate:max_length=70',
        ],
        // WARNING: GTIN-only heuristic. Empty gtin → "no", otherwise → "yes".
        // This MISLABELS brand+MPN-only products (which Google treats as having
        // a valid identifier) as identifier_exists="no", which can suppress them.
        // For catalogs that rely on brand+MPN, replace this with a rule/combined
        // source covering the full "no GTIN AND no MPN AND no brand" condition.
        'identifier_exists' => [
            'source_type' => 'static',
            'source_value' => '',
            'transformers' => 'conditional:condition_field=gtin,operator=empty,true_value=no,false_value=yes',
        ],

        // Condition & state
        'condition' => ['source_type' => 'static', 'source_value' => 'new'],
        'adult' => ['source_type' => 'static', 'source_value' => ''],

        // Pricing
        'sale_price' => ['source_type' => 'attribute', 'source_value' => 'special_price'],
        // Rolling now / +90 days range. Swap source to special_to_date if
        // the catalog has real per-product sale-end dates.
        'sale_price_effective_date' => [
            'source_type' => 'static',
            'source_value' => '',
            'transformers' => 'relative_date_range',
        ],
        'cost_of_goods_sold' => ['source_type' => 'attribute', 'source_value' => 'cost'],
        'expiration_date' => ['source_type' => 'static', 'source_value' => ''],
        'unit_pricing_measure' => ['source_type' => 'static', 'source_value' => ''],
        'unit_pricing_base_measure' => ['source_type' => 'static', 'source_value' => ''],
        'auto_pricing_min_price' => ['source_type' => 'static', 'source_value' => ''],
        'availability_date' => ['source_type' => 'static', 'source_value' => ''],

        // Imagery & media
        'additional_image_link' => [
            'source_type' => 'attribute',
            'source_value' => 'additional_images_csv',
            'use_parent' => 'if_empty',
            'transformers' => 'truncate:max_length=2000',
        ],
        'lifestyle_image_link' => [
            'source_type' => 'static',
            'source_value' => '',
            'transformers' => 'truncate:max_length=2000',
        ],
        'video_link' => ['source_type' => 'static', 'source_value' => ''],
        'mobile_link' => [
            'source_type' => 'static',
            'source_value' => '',
            'transformers' => 'truncate:max_length=2000',
        ],

        // Category
        'product_type' => [
            'source_type' => 'attribute',
            'source_value' => 'category_path',
            'use_parent' => 'if_empty',
            'transformers' => 'truncate:max_length=750',
        ],

        // Apparel / variant
        'color' => [
            'source_type' => 'attribute',
            'source_value' => 'color',
            'transformers' => 'truncate:max_length=100',
        ],
        'size' => [
            'source_type' => 'attribute',
            'source_value' => 'size',
            'transformers' => 'truncate:max_length=100',
        ],
        'size_type' => ['source_type' => 'static', 'source_value' => ''],
        'size_system' => ['source_type' => 'static', 'source_value' => ''],
        'gender' => ['source_type' => 'attribute', 'source_value' => 'gender'],
        'age_group' => ['source_type' => 'static', 'source_value' => ''],
        'material' => [
            'source_type' => 'attribute',
            'source_value' => 'material',
            'transformers' => 'truncate:max_length=200',
        ],
        'pattern' => [
            'source_type' => 'static',
            'source_value' => '',
            'transformers' => 'truncate:max_length=100',
        ],
        'item_group_id' => [
            'source_type' => 'attribute',
            'source_value' => 'sku',
            'use_parent' => 'always',
            'transformers' => 'truncate:max_length=50',
        ],
        'item_group_title' => [
            'source_type' => 'attribute',
            'source_value' => 'name',
            'use_parent' => 'always',
            'transformers' => 'truncate:max_length=150',
        ],

        // Multipack / bundle
        'multipack' => ['source_type' => 'static', 'source_value' => ''],
        'is_bundle' => ['source_type' => 'static', 'source_value' => ''],

        // Physical dimensions
        'product_length' => ['source_type' => 'static', 'source_value' => ''],
        'product_width' => ['source_type' => 'static', 'source_value' => ''],
        'product_height' => ['source_type' => 'static', 'source_value' => ''],
        'product_weight' => ['source_type' => 'attribute', 'source_value' => 'weight'],

        // Rich product detail
        'product_detail' => ['source_type' => 'static', 'source_value' => ''],
        'product_highlight' => [
            'source_type' => 'static',
            'source_value' => '',
            'transformers' => 'truncate:max_length=150',
        ],
        'document_link' => ['source_type' => 'static', 'source_value' => ''],
        'related_product' => ['source_type' => 'static', 'source_value' => ''],
        'question_and_answer' => ['source_type' => 'static', 'source_value' => ''],

        // Shipping & tax
        'shipping' => ['source_type' => 'static', 'source_value' => ''],
        'shipping_weight' => ['source_type' => 'attribute', 'source_value' => 'weight'],
        'shipping_length' => ['source_type' => 'static', 'source_value' => ''],
        'shipping_width' => ['source_type' => 'static', 'source_value' => ''],
        'shipping_height' => ['source_type' => 'static', 'source_value' => ''],
        'tax' => ['source_type' => 'static', 'source_value' => ''],

        // Energy labels
        'energy_efficiency_class' => ['source_type' => 'static', 'source_value' => ''],
        'min_energy_efficiency_class' => ['source_type' => 'static', 'source_value' => ''],
        'max_energy_efficiency_class' => ['source_type' => 'static', 'source_value' => ''],

        // Campaign / promotion
        'promotion_id' => [
            'source_type' => 'static',
            'source_value' => '',
            'transformers' => 'truncate:max_length=50',
        ],
        'ads_redirect' => [
            'source_type' => 'static',
            'source_value' => '',
            'transformers' => 'truncate:max_length=2000',
        ],
        'custom_label_0' => [
            'source_type' => 'static',
            'source_value' => '',
            'transformers' => 'truncate:max_length=100',
        ],
        'custom_label_1' => [
            'source_type' => 'static',
            'source_value' => '',
            'transformers' => 'truncate:max_length=100',
        ],
        'custom_label_2' => [
            'source_type' => 'static',
            'source_value' => '',
            'transformers' => 'truncate:max_length=100',
        ],
        'custom_label_3' => [
            'source_type' => 'static',
            'source_value' => '',
            'transformers' => 'truncate:max_length=100',
        ],
        'custom_label_4' => [
            'source_type' => 'static',
            'source_value' => '',
            'transformers' => 'truncate:max_length=100',
        ],
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

        // Warn if availability_date is missing for preorder/backorder products
        if (isset($productData['availability'])
            && in_array($productData['availability'], ['preorder', 'backorder'])
            && empty($productData['availability_date'])
        ) {
            Mage::log(
                'Warning: availability_date is recommended for ' . $productData['availability'] . ' products'
                    . (isset($productData['id']) ? ' (product: ' . $productData['id'] . ')' : ''),
                Mage::LOG_WARNING,
            );
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
    #[\Override]
    public function getNamespacedAttributes(): array
    {
        // All Google Shopping attributes except id, title, link need g: prefix
        return array_diff(
            array_keys($this->getAllAttributes()),
            ['id', 'title', 'link'],
        );
    }
}
