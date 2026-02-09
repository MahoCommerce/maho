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
 * Trovaprezzi Platform Adapter
 *
 * Implements Trovaprezzi price comparison feed specification for Italy
 * @see https://feedonomics.com/product-feed-specifications/trovaprezzi/
 */
class Maho_FeedManager_Model_Platform_Trovaprezzi extends Maho_FeedManager_Model_Platform_AbstractAdapter
{
    protected string $_code = 'trovaprezzi';
    protected string $_name = 'Trovaprezzi';
    protected array $_supportedFormats = ['xml'];
    protected string $_defaultFormat = 'xml';
    protected string $_rootElement = 'Products';
    protected string $_itemElement = 'Offer';

    protected array $_requiredAttributes = [
        'Name' => [
            'label' => 'Name',
            'required' => true,
            'description' => 'Product name with model. No promotional taglines.',
        ],
        'Code' => [
            'label' => 'Code',
            'required' => true,
            'description' => 'Unique product ID (SKU)',
        ],
        'Brand' => [
            'label' => 'Brand',
            'required' => true,
            'description' => 'Product brand or manufacturer name',
        ],
        'Description' => [
            'label' => 'Description',
            'required' => true,
            'description' => 'Product description. HTML tags allowed.',
        ],
        'Link' => [
            'label' => 'Link',
            'required' => true,
            'description' => 'Product page URL',
        ],
        'Image' => [
            'label' => 'Image',
            'required' => true,
            'description' => 'Main product image URL',
        ],
        'OriginalPrice' => [
            'label' => 'Original Price',
            'required' => true,
            'description' => 'Product price (numeric, no currency)',
        ],
        'ShippingCost' => [
            'label' => 'Shipping Cost',
            'required' => true,
            'description' => 'Shipping cost (numeric, 0 for free shipping)',
        ],
        'EanCode' => [
            'label' => 'EAN Code',
            'required' => true,
            'description' => 'European Article Number (EAN/barcode)',
        ],
        'Stock' => [
            'label' => 'Stock',
            'required' => true,
            'description' => 'Stock availability (Y/N or quantity)',
        ],
    ];

    protected array $_optionalAttributes = [
        'Category' => [
            'label' => 'Category',
            'required' => false,
            'description' => 'Product category path',
        ],
        'PartNumber' => [
            'label' => 'Part Number',
            'required' => false,
            'description' => 'Manufacturer Part Number (MPN)',
        ],
        'Price' => [
            'label' => 'Sale Price',
            'required' => false,
            'description' => 'Discounted/sale price if different from OriginalPrice',
        ],
        'Weight' => [
            'label' => 'Weight',
            'required' => false,
            'description' => 'Product weight in kg',
        ],
        'DeliveryTime' => [
            'label' => 'Delivery Time',
            'required' => false,
            'description' => 'Estimated delivery time (e.g., "2-3 giorni")',
        ],
        'Warranty' => [
            'label' => 'Warranty',
            'required' => false,
            'description' => 'Warranty information',
        ],
        'Size' => [
            'label' => 'Size',
            'required' => false,
            'description' => 'Product size',
        ],
        'Color' => [
            'label' => 'Color',
            'required' => false,
            'description' => 'Product color',
        ],
    ];

    protected array $_defaultMappings = [
        'Name' => ['source_type' => 'attribute', 'source_value' => 'name'],
        'Code' => ['source_type' => 'attribute', 'source_value' => 'sku'],
        'Brand' => ['source_type' => 'attribute', 'source_value' => 'manufacturer'],
        'Description' => ['source_type' => 'attribute', 'source_value' => 'description'],
        'Link' => ['source_type' => 'attribute', 'source_value' => 'url', 'use_parent' => 'always'],
        'Image' => ['source_type' => 'attribute', 'source_value' => 'image', 'use_parent' => 'if_empty'],
        'OriginalPrice' => ['source_type' => 'attribute', 'source_value' => 'price'],
        'Price' => ['source_type' => 'attribute', 'source_value' => 'special_price'],
        'ShippingCost' => ['source_type' => 'static', 'source_value' => '0'],
        'EanCode' => ['source_type' => 'attribute', 'source_value' => 'gtin'],
        'PartNumber' => ['source_type' => 'attribute', 'source_value' => 'mpn'],
        'Stock' => ['source_type' => 'rule', 'source_value' => 'stock_status'],
        'Category' => ['source_type' => 'attribute', 'source_value' => 'category_path'],
        'DeliveryTime' => ['source_type' => 'static', 'source_value' => '2-5 giorni lavorativi'],
        'Color' => ['source_type' => 'attribute', 'source_value' => 'color'],
        'Size' => ['source_type' => 'attribute', 'source_value' => 'size'],
    ];

    #[\Override]
    public function transformProductData(array $productData): array
    {
        // Transform stock to Y/N format
        if (isset($productData['Stock'])) {
            $productData['Stock'] = $this->_transformStock($productData['Stock']);
        }

        // Ensure prices are numeric only
        foreach (['OriginalPrice', 'Price', 'ShippingCost'] as $priceField) {
            if (isset($productData[$priceField]) && $productData[$priceField] !== '') {
                $productData[$priceField] = number_format((float) $productData[$priceField], 2, '.', '');
            }
        }

        // Clean description (allow HTML but sanitize)
        if (isset($productData['Description'])) {
            $productData['Description'] = $this->_sanitizeHtml($productData['Description']);
        }

        // Clean product name (no promotional text)
        if (isset($productData['Name'])) {
            $productData['Name'] = $this->_cleanProductName($productData['Name']);
        }

        return $productData;
    }

    #[\Override]
    public function validateProductData(array $productData): array
    {
        $errors = parent::validateProductData($productData);

        // Validate EAN format
        if (!empty($productData['EanCode']) && !preg_match('/^\d{13}$/', $productData['EanCode'])) {
            $errors[] = 'Invalid EAN code format (must be 13 digits)';
        }

        // Validate stock value
        if (isset($productData['Stock']) && !in_array($productData['Stock'], ['Y', 'N'])) {
            $errors[] = 'Stock must be Y or N';
        }

        return $errors;
    }

    protected function _transformStock(mixed $value): string
    {
        if (is_numeric($value)) {
            return (int) $value > 0 ? 'Y' : 'N';
        }

        $normalized = strtolower(trim((string) $value));
        $inStock = ['y', 'yes', '1', 'true', 'in stock', 'in_stock', 'available'];

        return in_array($normalized, $inStock) ? 'Y' : 'N';
    }

    protected function _sanitizeHtml(string $html): string
    {
        // Allow only basic HTML tags
        $allowed = '<p><br><b><strong><i><em><ul><ol><li><span><div>';
        return strip_tags($html, $allowed);
    }

    protected function _cleanProductName(string $name): string
    {
        // Remove common promotional phrases
        $patterns = [
            '/\b(offerta|sconto|promo|promozione|saldi|vendita|speciale|gratis|free)\b/i',
            '/!+/',
            '/\s+/',
        ];
        $replacements = ['', '', ' '];

        $cleaned = preg_replace($patterns, $replacements, $name);
        return trim($cleaned ?? $name);
    }
}
