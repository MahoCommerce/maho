<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Service;

/**
 * Gives the default country and the base currency of a store.
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
}
