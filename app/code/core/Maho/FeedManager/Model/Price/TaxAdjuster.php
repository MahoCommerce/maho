<?php

/**
 * Converts a catalog price into the tax mode a feed asks for.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

final class Maho_FeedManager_Model_Price_TaxAdjuster
{
    private Mage_Core_Model_Store $_store;
    private bool $_wantIncludingTax;
    private bool $_storedIncludingTax;

    /** @var array<int, float> Resolved destination rate per tax class id */
    private array $_destinationRates = [];

    /** @var array<int, float> Resolved rate baked into a stored tax inclusive price, per tax class id */
    private array $_includedRates = [];

    public function __construct(Maho_FeedManager_Model_Feed $feed)
    {
        $this->_store = Mage::app()->getStore($feed->getStoreId());
        $this->_wantIncludingTax = ($feed->getTaxMode() ?: 'incl') === 'incl';
        $this->_storedIncludingTax = (bool) Mage::helper('tax')->priceIncludesTax($this->_store);
    }

    public function adjust(Mage_Catalog_Model_Product $product, float|string|null $price): ?float
    {
        if ($price === null || $price === '' || !is_numeric($price)) {
            return null;
        }

        $price = (float) $price;
        if ($price === 0.0 || $this->_wantIncludingTax === $this->_storedIncludingTax) {
            return $price;
        }

        $taxClassId = (int) $product->getTaxClassId();
        if ($taxClassId === 0) {
            return $price;
        }

        $calculation = Mage::getSingleton('tax/calculation');

        if ($this->_wantIncludingTax) {
            $rate = $this->_resolveDestinationRate($product, $taxClassId);
            $adjusted = $price + $calculation->calcTaxAmount($price, $rate, false, false);
        } else {
            $rate = $this->_getIncludedRate($product, $taxClassId);
            $adjusted = $price - $calculation->calcTaxAmount($price, $rate, true, false);
        }

        return (float) $this->_store->roundPrice($adjusted);
    }

    /**
     * A rate already resolved on the product wins, as in Mage_Tax_Helper_Data::getPrice().
     */
    private function _resolveDestinationRate(Mage_Catalog_Model_Product $product, int $taxClassId): float
    {
        $percent = $product->getTaxPercent();

        return $percent === null || $percent === '' ? $this->_getDestinationRate($taxClassId) : (float) $percent;
    }

    /**
     * Rate a customer pays at the store's default tax destination.
     *
     * Mage_Tax_Model_Calculation::getRateRequest() is avoided on purpose: it reads the customer
     * session, which a cron worker must not start. Its address selection is reproduced here:
     * "Based on" = Origin uses the shipping origin, every other value uses the tax defaults,
     * because no customer address exists during feed generation.
     */
    private function _getDestinationRate(int $taxClassId): float
    {
        if (!isset($this->_destinationRates[$taxClassId])) {
            $calculation = Mage::getSingleton('tax/calculation');

            if (Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_BASED_ON, $this->_store) === 'origin') {
                $request = $calculation->getRateOriginRequest($this->_store);
            } else {
                $request = new \Maho\DataObject();
                $request
                    ->setCountryId(Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_DEFAULT_COUNTRY, $this->_store))
                    ->setRegionId(Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_DEFAULT_REGION, $this->_store))
                    ->setPostcode(Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_DEFAULT_POSTCODE, $this->_store))
                    ->setCustomerClassId($calculation->getDefaultCustomerTaxClass($this->_store))
                    ->setStore($this->_store);
            }

            $this->_destinationRates[$taxClassId] = (float) $calculation->getRate($request->setProductClassId($taxClassId));
        }

        return $this->_destinationRates[$taxClassId];
    }

    /**
     * Rate already baked into a stored tax inclusive price.
     */
    private function _getIncludedRate(Mage_Catalog_Model_Product $product, int $taxClassId): float
    {
        if (Mage::helper('tax')->isCrossBorderTradeEnabled($this->_store)) {
            return $this->_resolveDestinationRate($product, $taxClassId);
        }

        if (!isset($this->_includedRates[$taxClassId])) {
            $request = Mage::getSingleton('tax/calculation')->getRateOriginRequest($this->_store);
            $this->_includedRates[$taxClassId] = (float) Mage::getSingleton('tax/calculation')
                ->getRate($request->setProductClassId($taxClassId));
        }

        return $this->_includedRates[$taxClassId];
    }
}
