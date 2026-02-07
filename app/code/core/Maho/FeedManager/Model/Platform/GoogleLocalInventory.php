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
 * Google Local Inventory Ads Platform Adapter
 *
 * Implements Google Local Product Inventory feed specification
 * This feed provides store-level inventory data for Local Inventory Ads
 * @see https://support.google.com/merchants/answer/14819809
 */
class Maho_FeedManager_Model_Platform_GoogleLocalInventory extends Maho_FeedManager_Model_Platform_AbstractAdapter
{
    protected string $_code = 'google_local_inventory';
    protected string $_name = 'Google Local Inventory';
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
            'description' => 'Product ID matching Google Shopping feed (max 50 characters)',
        ],
        'store_code' => [
            'label' => 'Store Code',
            'required' => true,
            'description' => 'Store identifier from Google Business Profile (case-sensitive, max 64 characters)',
        ],
        'availability' => [
            'label' => 'Availability',
            'required' => true,
            'description' => 'in_stock, out_of_stock, limited_availability, on_display_to_order',
        ],
    ];

    protected array $_optionalAttributes = [
        'price' => [
            'label' => 'Price',
            'required' => false,
            'description' => 'Local store price with currency (e.g., 25.00 AUD). Only needed if different from Shopping feed.',
        ],
        'sale_price' => [
            'label' => 'Sale Price',
            'required' => false,
            'description' => 'Local sale price with currency',
        ],
        'sale_price_effective_date' => [
            'label' => 'Sale Price Effective Date',
            'required' => false,
            'description' => 'Date range for sale price (ISO 8601)',
        ],
        'quantity' => [
            'label' => 'Quantity',
            'required' => false,
            'description' => 'Number of items in stock (3+ = in stock, 1-2 = limited, 0 = out of stock)',
        ],
        'pickup_method' => [
            'label' => 'Pickup Method',
            'required' => false,
            'description' => 'buy, reserve, ship to store, not supported',
        ],
        'pickup_sla' => [
            'label' => 'Pickup SLA',
            'required' => false,
            'description' => 'same day, next day, 2-day, 3-day, 4-day, 5-day, 6-day, multi-week',
        ],
        'instoreproduct_location' => [
            'label' => 'In-Store Product Location',
            'required' => false,
            'description' => 'Location of product in store (e.g., aisle, shelf)',
        ],
    ];

    protected array $_defaultMappings = [
        'id' => ['source_type' => 'attribute', 'source_value' => 'sku'],
        'store_code' => ['source_type' => 'static', 'source_value' => ''],
        'availability' => ['source_type' => 'rule', 'source_value' => 'stock_status'],
        'price' => ['source_type' => 'attribute', 'source_value' => 'price'],
        'sale_price' => ['source_type' => 'attribute', 'source_value' => 'special_price'],
        'sale_price_effective_date' => ['source_type' => 'static', 'source_value' => ''],
        'quantity' => ['source_type' => 'attribute', 'source_value' => 'qty'],
        'pickup_method' => ['source_type' => 'static', 'source_value' => ''],
        'pickup_sla' => ['source_type' => 'static', 'source_value' => ''],
        'instoreproduct_location' => ['source_type' => 'static', 'source_value' => ''],
    ];

    #[\Override]
    public function transformProductData(array $productData): array
    {
        // Transform availability
        if (isset($productData['availability'])) {
            $productData['availability'] = $this->_transformAvailability($productData['availability']);
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

        // Ensure quantity is integer
        if (isset($productData['quantity'])) {
            $productData['quantity'] = (string) max(0, (int) $productData['quantity']);
        }

        // Transform pickup_method
        if (isset($productData['pickup_method'])) {
            $productData['pickup_method'] = $this->_transformPickupMethod($productData['pickup_method']);
        }

        // Transform pickup_sla
        if (isset($productData['pickup_sla'])) {
            $productData['pickup_sla'] = $this->_transformPickupSla($productData['pickup_sla']);
        }

        return $productData;
    }

    #[\Override]
    public function validateProductData(array $productData): array
    {
        $errors = parent::validateProductData($productData);

        // Validate availability values
        $validAvailability = ['in_stock', 'out_of_stock', 'limited_availability', 'on_display_to_order'];
        if (isset($productData['availability']) && !in_array($productData['availability'], $validAvailability)) {
            $errors[] = 'Invalid availability value: ' . $productData['availability'];
        }

        // Validate price format if provided
        if (!empty($productData['price']) && !preg_match('/^\d+\.\d{2}\s[A-Z]{3}$/', $productData['price'])) {
            $errors[] = 'Price must be in format "XX.XX CUR" (e.g., 25.00 AUD)';
        }

        // Validate pickup_method
        $validPickupMethods = ['buy', 'reserve', 'ship to store', 'not supported'];
        if (!empty($productData['pickup_method']) && !in_array($productData['pickup_method'], $validPickupMethods)) {
            $errors[] = 'Invalid pickup_method value: ' . $productData['pickup_method'];
        }

        // Validate pickup_sla
        $validPickupSla = ['same day', 'next day', '2-day', '3-day', '4-day', '5-day', '6-day', 'multi-week'];
        if (!empty($productData['pickup_sla']) && !in_array($productData['pickup_sla'], $validPickupSla)) {
            $errors[] = 'Invalid pickup_sla value: ' . $productData['pickup_sla'];
        }

        // If pickup_method is set and not "not supported", pickup_sla is required
        if (!empty($productData['pickup_method']) && $productData['pickup_method'] !== 'not supported' && empty($productData['pickup_sla'])) {
            $errors[] = 'pickup_sla is required when pickup_method is set';
        }

        return $errors;
    }

    /**
     * Transform availability - Google Local Inventory supports on_display_to_order
     */
    #[\Override]
    protected function _transformAvailability(mixed $value, bool $useUnderscore = true): string
    {
        $normalized = strtolower(trim((string) $value));

        // Handle local inventory specific values
        if ($normalized === 'limited_availability' || $normalized === 'limited availability') {
            return 'limited_availability';
        }
        if ($normalized === 'on_display_to_order' || $normalized === 'on display to order') {
            return 'on_display_to_order';
        }

        return parent::_transformAvailability($value, true);
    }

    /**
     * Transform pickup method value to Google format (uses spaces)
     */
    protected function _transformPickupMethod(mixed $value): string
    {
        $map = [
            'buy' => 'buy',
            'reserve' => 'reserve',
            'ship_to_store' => 'ship to store',
            'ship to store' => 'ship to store',
            'not_supported' => 'not supported',
            'not supported' => 'not supported',
        ];

        $normalized = strtolower(trim((string) $value));
        return $map[$normalized] ?? '';
    }

    /**
     * Transform pickup SLA value to Google format (uses spaces for day values)
     */
    protected function _transformPickupSla(mixed $value): string
    {
        $valid = ['same day', 'next day', '2-day', '3-day', '4-day', '5-day', '6-day', 'multi-week'];
        $normalized = strtolower(trim((string) $value));

        // Handle common variations
        $map = [
            'same_day' => 'same day',
            'sameday' => 'same day',
            'same day' => 'same day',
            'next_day' => 'next day',
            'nextday' => 'next day',
            'next day' => 'next day',
            'multi_week' => 'multi-week',
            'multiweek' => 'multi-week',
            'multi-week' => 'multi-week',
            'multi week' => 'multi-week',
        ];

        $transformed = $map[$normalized] ?? $normalized;
        return in_array($transformed, $valid) ? $transformed : '';
    }

    /**
     * Get attributes that need the g: namespace prefix in XML
     */
    public function getNamespacedAttributes(): array
    {
        return array_diff(
            array_keys($this->getAllAttributes()),
            ['id'],
        );
    }
}
