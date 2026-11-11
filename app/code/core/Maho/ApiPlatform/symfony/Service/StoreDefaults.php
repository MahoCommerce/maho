<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Service;

/**
 * Store-aware defaults for country, currency, region, and POS address.
 * Replaces hardcoded AU/AUD/Melbourne values throughout the API.
 */
class StoreDefaults
{
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
            'firstname' => 'Store',
            'lastname' => 'Customer',
            'street' => \Mage::getStoreConfig('general/store_information/address', $storeId) ?: 'In-Store',
            'city' => \Mage::getStoreConfig('general/store_information/city', $storeId) ?: 'Store',
            'region' => \Mage::getStoreConfig('general/store_information/region', $storeId) ?: 'N/A',
            'postcode' => \Mage::getStoreConfig('general/store_information/postcode', $storeId) ?: '0000',
            'country_id' => \Mage::getStoreConfig('general/store_information/merchant_country', $storeId)
                ?: self::getCountryId($storeId),
            'telephone' => \Mage::getStoreConfig('general/store_information/phone', $storeId) ?: '0000000000',
        ];
    }
}
