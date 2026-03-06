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
        return [
            'firstname' => 'POS',
            'lastname' => 'Customer',
            'street' => \Mage::getStoreConfig('general/store_information/address', $storeId) ?: 'In-Store Pickup',
            'city' => \Mage::getStoreConfig('general/store_information/city', $storeId) ?: 'Store',
            'region' => \Mage::getStoreConfig('general/store_information/region', $storeId) ?: '',
            'region_id' => (int) (\Mage::getStoreConfig('general/store_information/region_id', $storeId) ?: 0),
            'postcode' => \Mage::getStoreConfig('general/store_information/postcode', $storeId) ?: '0000',
            'country_id' => self::getCountryId($storeId),
            'telephone' => \Mage::getStoreConfig('general/store_information/phone', $storeId) ?: '0000000000',
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
