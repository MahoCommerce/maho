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
 * Idealo Platform Adapter
 *
 * Implements Idealo price comparison feed specification for Germany, Italy, Spain, France, UK
 * @see https://idealo.github.io/csv-importer/en/csv/
 */
class Maho_FeedManager_Model_Platform_Idealo extends Maho_FeedManager_Model_Platform_AbstractAdapter
{
    protected string $_code = 'idealo';
    protected string $_name = 'Idealo';
    protected array $_supportedFormats = ['csv'];
    protected string $_defaultFormat = 'csv';

    protected array $_requiredAttributes = [
        'sku' => [
            'label' => 'SKU',
            'required' => true,
            'description' => 'Unique product identifier',
        ],
        'brand' => [
            'label' => 'Brand',
            'required' => true,
            'description' => 'Product brand/manufacturer name',
        ],
        'title' => [
            'label' => 'Title',
            'required' => true,
            'description' => 'Product title/name (max 255 characters)',
        ],
        'categoryPath' => [
            'label' => 'Category Path',
            'required' => true,
            'description' => 'Product category path (e.g., Electronics > Computers > Laptops)',
        ],
        'eans' => [
            'label' => 'EAN',
            'required' => true,
            'description' => 'European Article Number (8, 12, 13, or 14 digits). Multiple values separated by semicolon',
        ],
        'price' => [
            'label' => 'Price',
            'required' => true,
            'description' => 'Product price (decimal with dot, no currency symbol)',
        ],
        'url' => [
            'label' => 'Product URL',
            'required' => true,
            'description' => 'Direct link to the product page',
        ],
        'imageUrls' => [
            'label' => 'Image URL',
            'required' => true,
            'description' => 'Main product image URL (max 1MB)',
        ],
        'delivery' => [
            'label' => 'Delivery Time',
            'required' => true,
            'description' => 'Delivery timeframe in working days (e.g., "1-3 Werktage")',
        ],
    ];

    protected array $_optionalAttributes = [
        'fulfillmentType' => [
            'label' => 'Fulfillment Type',
            'required' => false,
            'description' => 'Fulfillment type: PAKETDIENST, Spedition, Download, or Briefversand',
        ],
        'checkoutLimitPerPeriod' => [
            'label' => 'Checkout Limit',
            'required' => false,
            'description' => 'Maximum quantity per order',
        ],
        'hans' => [
            'label' => 'HAN',
            'required' => false,
            'description' => 'Manufacturer Article Number (HAN/MPN)',
        ],
        'description' => [
            'label' => 'Description',
            'required' => false,
            'description' => 'Product description (max 1000 characters)',
        ],
        'basePrice' => [
            'label' => 'Base Price',
            'required' => false,
            'description' => 'Price per unit for comparison (e.g., price per kg)',
        ],
        'packagingUnit' => [
            'label' => 'Packaging Unit',
            'required' => false,
            'description' => 'Unit of the packaging (e.g., kg, l, piece)',
        ],
        'deliveryComment' => [
            'label' => 'Delivery Comment',
            'required' => false,
            'description' => 'Additional delivery information',
        ],
        'deliveryCosts' => [
            'label' => 'Delivery Costs',
            'required' => false,
            'description' => 'Shipping cost. For carrier-specific costs, create additional columns named deliveryCosts_dhl, deliveryCosts_hermes, etc.',
        ],
        'paymentCosts' => [
            'label' => 'Payment Costs',
            'required' => false,
            'description' => 'Additional payment method costs',
        ],
        'used' => [
            'label' => 'Used',
            'required' => false,
            'description' => 'Is product used/refurbished (true/false)',
        ],
        'merchantName' => [
            'label' => 'Merchant Name',
            'required' => false,
            'description' => 'Name of the selling merchant',
        ],
        'voucherCode' => [
            'label' => 'Voucher Code',
            'required' => false,
            'description' => 'Discount voucher code',
        ],
        'size' => [
            'label' => 'Size',
            'required' => false,
            'description' => 'Product size',
        ],
        'colour' => [
            'label' => 'Colour',
            'required' => false,
            'description' => 'Product colour',
        ],
        'gender' => [
            'label' => 'Gender',
            'required' => false,
            'description' => 'Target gender (MALE, FEMALE, UNISEX)',
        ],
        'material' => [
            'label' => 'Material',
            'required' => false,
            'description' => 'Product material',
        ],
        'replica' => [
            'label' => 'Replica',
            'required' => false,
            'description' => 'Is product a replica (true/false)',
        ],
        'download' => [
            'label' => 'Download',
            'required' => false,
            'description' => 'Is product a digital download (true/false)',
        ],
        'eec' => [
            'label' => 'Energy Efficiency Class',
            'required' => false,
            'description' => 'EU energy label (A+++, A++, A+, A, B, C, D, E, F, G)',
        ],
        'energyLabelUrl' => [
            'label' => 'Energy Label URL',
            'required' => false,
            'description' => 'URL to energy label image',
        ],
        'checkout' => [
            'label' => 'Checkout Enabled',
            'required' => false,
            'description' => 'Enable Idealo Direktkauf for this product (true/false)',
        ],
        'formerPrice' => [
            'label' => 'Former Price',
            'required' => false,
            'description' => 'Strikethrough price for promotional display',
        ],
        'deposit' => [
            'label' => 'Deposit',
            'required' => false,
            'description' => 'Deposit amount (Pfand) for applicable products',
        ],
        'maxOrderProcessingTime' => [
            'label' => 'Max Order Processing Time',
            'required' => false,
            'description' => 'Maximum processing time before shipment (in days)',
        ],
        'freeReturnDays' => [
            'label' => 'Free Return Days',
            'required' => false,
            'description' => 'Number of days for free returns',
        ],
    ];

    protected array $_defaultMappings = [
        'sku' => ['source_type' => 'attribute', 'source_value' => 'sku'],
        'brand' => ['source_type' => 'attribute', 'source_value' => 'manufacturer'],
        'title' => ['source_type' => 'attribute', 'source_value' => 'name'],
        'categoryPath' => ['source_type' => 'attribute', 'source_value' => 'category_path'],
        'eans' => ['source_type' => 'attribute', 'source_value' => 'gtin'],
        'hans' => ['source_type' => 'attribute', 'source_value' => 'mpn'],
        'price' => ['source_type' => 'attribute', 'source_value' => 'price'],
        'url' => ['source_type' => 'attribute', 'source_value' => 'url', 'use_parent' => 'always'],
        'imageUrls' => ['source_type' => 'attribute', 'source_value' => 'image', 'use_parent' => 'if_empty'],
        'description' => ['source_type' => 'attribute', 'source_value' => 'short_description'],
        'delivery' => ['source_type' => 'static', 'source_value' => '2-4 business days'],
        'deliveryCosts' => ['source_type' => 'static', 'source_value' => '0'],
        'used' => ['source_type' => 'static', 'source_value' => 'false'],
        'merchantName' => ['source_type' => 'attribute', 'source_value' => 'store_name'],
        'colour' => ['source_type' => 'attribute', 'source_value' => 'color'],
        'size' => ['source_type' => 'attribute', 'source_value' => 'size'],
        'fulfillmentType' => ['source_type' => 'static', 'source_value' => 'Parcel_Service'],
        'checkoutLimitPerPeriod' => ['source_type' => 'static', 'source_value' => '5'],
        'checkout' => ['source_type' => 'static', 'source_value' => 'true'],
        'formerPrice' => ['source_type' => 'static', 'source_value' => ''],
        'deposit' => ['source_type' => 'static', 'source_value' => ''],
        'maxOrderProcessingTime' => ['source_type' => 'static', 'source_value' => ''],
        'freeReturnDays' => ['source_type' => 'static', 'source_value' => ''],
    ];

    #[\Override]
    public function transformProductData(array $productData): array
    {
        // Truncate title to 255 characters
        if (isset($productData['title'])) {
            $productData['title'] = $this->_truncateText(
                $this->_sanitizeText($productData['title']),
                255,
            );
        }

        // Truncate description to 1000 characters
        if (isset($productData['description'])) {
            $productData['description'] = $this->_truncateText(
                $this->_sanitizeText($productData['description']),
                1000,
            );
        }

        // Ensure price is numeric only (no currency symbol)
        if (isset($productData['price'])) {
            $productData['price'] = number_format((float) $productData['price'], 2, '.', '');
        }

        // Transform used/replica/download to true/false string
        foreach (['used', 'replica', 'download'] as $boolField) {
            if (isset($productData[$boolField])) {
                $productData[$boolField] = $this->_transformBoolean($productData[$boolField]);
            }
        }

        // Transform gender
        if (isset($productData['gender'])) {
            $productData['gender'] = $this->_transformGender($productData['gender']);
        }

        return $productData;
    }

    #[\Override]
    public function validateProductData(array $productData): array
    {
        $errors = parent::validateProductData($productData);

        // Validate EAN/GTIN format (8, 12, 13, or 14 digits)
        if (!empty($productData['eans'])) {
            $eans = explode(';', $productData['eans']);
            foreach ($eans as $ean) {
                $ean = trim($ean);
                if ($ean && !preg_match('/^\d{8}$|^\d{12,14}$/', $ean)) {
                    $errors[] = 'Invalid EAN/GTIN format for "' . $ean . '" (must be 8, 12, 13, or 14 digits)';
                }
            }
        }

        // Validate price format
        if (isset($productData['price']) && !is_numeric($productData['price'])) {
            $errors[] = 'Price must be numeric';
        }

        return $errors;
    }

    /**
     * Idealo uses 'true'/'false' instead of 'yes'/'no'
     */
    #[\Override]
    protected function _transformBoolean(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes']) ? 'true' : 'false';
    }

    /**
     * Idealo uses uppercase gender values and includes German terms
     */
    #[\Override]
    protected function _transformGender(mixed $value): string
    {
        $map = [
            'male' => 'MALE',
            'men' => 'MALE',
            'm' => 'MALE',
            'herren' => 'MALE',
            'female' => 'FEMALE',
            'women' => 'FEMALE',
            'f' => 'FEMALE',
            'damen' => 'FEMALE',
            'unisex' => 'UNISEX',
            'both' => 'UNISEX',
        ];

        $normalized = strtolower(trim((string) $value));
        return $map[$normalized] ?? 'UNISEX';
    }
}
