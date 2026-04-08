<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Service;

/**
 * Store-aware defaults for country, currency, region, and POS address.
 * Replaces hardcoded AU/AUD/Melbourne values throughout the API.
 */
class StoreDefaults
{
    /**
     * Allowed address fields for addData() whitelisting
     */
    public const ADDRESS_FIELDS = [
        'firstname', 'lastname', 'company', 'street', 'city',
        'region', 'region_id', 'postcode', 'country_id',
        'telephone', 'fax', 'email',
    ];

    /**
     * Get the default country for the given store
     */
    public static function getCountryId(?int $storeId = null): string
    {
        return \Mage::getStoreConfig('general/country/default', $storeId) ?: 'US';
    }

    /**
     * Get the base currency code for the given store
     */
    public static function getCurrencyCode(?int $storeId = null): string
    {
        return \Mage::app()->getStore($storeId ?: \Mage::app()->getDefaultStoreView()?->getId() ?: 0)
            ->getBaseCurrencyCode() ?: 'USD';
    }

    /**
     * Get POS/walk-in default address from store config
     */
    public static function getPosAddress(?int $storeId = null): array
    {
        // Read from POS module config (structured address fields), fall back to core store info
        $posPrefix = 'mageaustralia_pos/store_address/';
        $corePrefix = 'general/store_information/';

        return [
            'firstname' => 'POS',
            'lastname' => 'Customer',
            'street' => \Mage::getStoreConfig($posPrefix . 'street', $storeId)
                ?: \Mage::getStoreConfig($corePrefix . 'address', $storeId) ?: 'In-Store Pickup',
            'city' => \Mage::getStoreConfig($posPrefix . 'city', $storeId) ?: 'Store',
            'region' => \Mage::getStoreConfig($posPrefix . 'region', $storeId) ?: 'N/A',
            'postcode' => \Mage::getStoreConfig($posPrefix . 'postcode', $storeId) ?: '0000',
            'country_id' => \Mage::getStoreConfig($posPrefix . 'country_id', $storeId)
                ?: \Mage::getStoreConfig($corePrefix . 'merchant_country', $storeId)
                ?: 'AU',
            'telephone' => \Mage::getStoreConfig($posPrefix . 'telephone', $storeId)
                ?: \Mage::getStoreConfig($corePrefix . 'phone', $storeId) ?: '0000000000',
        ];
    }

    /**
     * Filter address data to only allowed keys
     */
    public static function filterAddressKeys(array $data): array
    {
        return array_intersect_key($data, array_flip(self::ADDRESS_FIELDS));
    }

    /**
     * Pick only address fields from a model's getData() (for sameAsShipping etc.)
     */
    public static function extractAddressFields(\Mage_Customer_Model_Address_Abstract $address): array
    {
        $data = [];
        foreach (self::ADDRESS_FIELDS as $key) {
            $value = $address->getData($key);
            if ($value !== null) {
                $data[$key] = $value;
            }
        }
        return $data;
    }
}
