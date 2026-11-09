<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Helper_Product_Type_Composite extends Mage_Core_Helper_Abstract
{
    #[\Override]
    protected $_moduleName = 'Mage_Catalog';

    /**
     * Calculation real price
     *
     * @param Mage_Catalog_Model_Product $product
     * @param float $price
     * @param bool $isPercent
     * @param null|int $storeId
     * @return string
     */
    public function preparePrice($product, $price, $isPercent = false, $storeId = null)
    {
        if ($isPercent && !empty($price)) {
            $price = $product->getFinalPrice() * $price / 100;
        }

        return $this->registerJsPrice($this->convertPrice($price, true, $storeId));
    }

    /**
     * Calculation price before special price
     *
     * @param Mage_Catalog_Model_Product $product
     * @param float $price
     * @param bool $isPercent
     * @param null|int $storeId
     * @return string
     */
    public function prepareOldPrice($product, $price, $isPercent = false, $storeId = null)
    {
        if ($isPercent && !empty($price)) {
            $price = $product->getPrice() * $price / 100;
        }

        return $this->registerJsPrice($this->convertPrice($price, true, $storeId));
    }

    /**
     * Replace ',' on '.' for js
     *
     * @param float $price
     * @return string
     */
    public function registerJsPrice($price)
    {
        return str_replace(',', '.', (string) $price);
    }

    /**
     * Convert price from default currency to current currency
     *
     * @param float $price
     * @param bool $round
     * @param null|int $storeId
     * @return int|float
     */
    public function convertPrice($price, $round = false, $storeId = null)
    {
        if (empty($price)) {
            return 0;
        }

        $price = $this->getCurrentStore($storeId)->convertPrice($price);
        if ($round) {
            $price = $this->getCurrentStore($storeId)->roundPrice($price);
        }

        return $price;
    }

    /**
     * Retrieve current store
     *
     * @return Mage_Core_Model_Store
     */
    public function getCurrentStore($storeId = null)
    {
        return Mage::app()->getStore($storeId);
    }

    /**
     * Prepare general params for product to be used in getJsonConfig()
     * @see Mage_Catalog_Block_Product_View::getJsonConfig()
     * @see Mage_ConfigurableSwatches_Block_Catalog_Product_List_Price::getJsonConfig()
     *
     * @return array
     */
    public function prepareJsonGeneralConfig()
    {
        return [
            'priceFormat'       => Mage::app()->getLocale()->getJsPriceFormat(),
            'includeTax'        => Mage::helper('tax')->priceIncludesTax() ? 'true' : 'false',
            'showIncludeTax'    => Mage::helper('tax')->displayPriceIncludingTax(),
            'showBothPrices'    => Mage::helper('tax')->displayBothPrices(),
            'idSuffix'            => '_clone',
            'oldPlusDisposition'  => 0,
            'plusDisposition'     => 0,
            'plusDispositionTax'  => 0,
            'oldMinusDisposition' => 0,
            'minusDisposition'    => 0,
        ];
    }

    /**
     * Prepare product specific params to be used in getJsonConfig()
     * @see Mage_Catalog_Block_Product_View::getJsonConfig()
     * @see Mage_ConfigurableSwatches_Block_Catalog_Product_List_Price::getJsonConfig()
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function prepareJsonProductConfig($product)
    {
        $_request = Mage::getSingleton('tax/calculation')->getDefaultRateRequest();
        $_request->setProductClassId($product->getTaxClassId());
        $defaultTax = Mage::getSingleton('tax/calculation')->getRate($_request);

        $_request = Mage::getSingleton('tax/calculation')->getRateRequest();
        $_request->setProductClassId($product->getTaxClassId());
        $currentTax = Mage::getSingleton('tax/calculation')->getRate($_request);

        $_regularPrice = $product->getPrice();
        $_finalPrice = $product->getFinalPrice();
        $isBundle = $product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE;
        $store = Mage::app()->getStore();
        // The cart rounds the price to cents before it adds the tax, as the price template does. A bundle
        // calculates in the base currency, like its selections, and converts at the end.
        $toCurrentCurrency = static fn($price) => $isBundle ? Mage::helper('core')->currency($price, false, false) : $price;
        if ($isBundle) {
            $_shownFinalPrice = $product->getStore()->roundPrice($_finalPrice);
            $_shownRegularPrice = $product->getStore()->roundPrice($_regularPrice);
            $_priceInclTax = Mage::helper('tax')->getPrice(
                $product,
                $_shownFinalPrice,
                true,
                null,
                null,
                null,
                null,
                null,
                false,
            );
            $_priceExclTax = Mage::helper('tax')->getPrice(
                $product,
                $_shownFinalPrice,
                false,
                null,
                null,
                null,
                null,
                null,
                false,
            );
        } else {
            $_shownFinalPrice = $store->roundPrice($store->convertPrice($_finalPrice));
            $_shownRegularPrice = $store->roundPrice($store->convertPrice($_regularPrice));
            $_priceInclTax = Mage::helper('tax')->getPrice($product, $_shownFinalPrice, true);
            $_priceExclTax = Mage::helper('tax')->getPrice($product, $_shownFinalPrice);
        }
        $_tierPrices = [];
        $_tierPricesInclTax = [];
        foreach ($product->getTierPrice() as $tierPrice) {
            // Skip tier prices >= lower of final price or group price
            $comparePrice = $_finalPrice;
            $groupPrice = $product->getGroupPrice();
            if ($groupPrice !== null && $groupPrice < $comparePrice) {
                $comparePrice = $groupPrice;
            }

            if ((float) $tierPrice['website_price'] >= $comparePrice) {
                continue;
            }

            $_tierPrice = $isBundle
                ? (float) $tierPrice['website_price']
                : $store->roundPrice($store->convertPrice($tierPrice['website_price']));
            $_tierPrices[] = $toCurrentCurrency(
                Mage::helper('tax')->getPrice($product, $_tierPrice, false) - $_priceExclTax,
            );
            $_tierPricesInclTax[] = $toCurrentCurrency(
                Mage::helper('tax')->getPrice($product, $_tierPrice, true) - $_priceInclTax,
            );
        }

        return [
            'productId'           => $product->getId(),
            'productPrice'        => $toCurrentCurrency($_shownFinalPrice),
            'productOldPrice'     => $toCurrentCurrency($_shownRegularPrice),
            'priceInclTax'        => $toCurrentCurrency($_priceInclTax),
            'priceExclTax'        => $toCurrentCurrency($_priceExclTax),
            'defaultTax'          => $defaultTax,
            'currentTax'          => $currentTax,
            'tierPrices'          => $_tierPrices,
            'tierPricesInclTax'   => $_tierPricesInclTax,
            'swatchPrices'        => $product->getSwatchPrices(),
        ];
    }
}
