<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Data extends Mage_Adminhtml_Block_Sales_Order_Create_Abstract
{
    /**
     * Retrieve available currency codes
     *
     * @return array
     */
    public function getAvailableCurrencies()
    {
        // The order's store, not the admin store
        $store = $this->getStore();
        $codes = array_keys($store->getServeableCurrencyRates());

        // The base currency needs no rate to record an order in, even when kept off the storefront
        $baseCode = (string) $store->getBaseCurrencyCode();
        if (!in_array($baseCode, $codes, true)) {
            $codes[] = $baseCode;
        }

        return $codes;
    }

    /**
     * Retrieve curency name by code
     *
     * @param   string $code
     * @return  string
     */
    #[\Deprecated(message: 'since 25.9.0')]
    public function getCurrencyName($code)
    {
        return $code;
    }

    /**
     * Retrieve curency symbol by code
     *
     * @param   string $code
     * @return  string
     */
    public function getCurrencySymbol($code)
    {
        return Mage::app()->getLocale()->getCurrencySymbol($code);
    }

    /**
     * Retrieve current order currency code
     *
     * @return string
     */
    public function getCurrentCurrencyCode()
    {
        return $this->getStore()->getCurrentCurrencyCode();
    }
}
