<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

/**
 * Currency model
 *
 * @package    Mage_Directory
 *
 * @method Mage_Directory_Model_Resource_Currency _getResource()
 */
class Mage_Directory_Model_Currency extends Mage_Core_Model_Abstract
{
    /**
     * CONFIG path constant: ALLOW
     */
    public const XML_PATH_CURRENCY_ALLOW   = 'currency/options/allow';
    /**
     * CONFIG path constant: DEFAULT
     */
    public const XML_PATH_CURRENCY_DEFAULT = 'currency/options/default';
    /**
     * CONFIG path constant: BASE
     */
    public const XML_PATH_CURRENCY_BASE    = 'currency/options/base';

    /**
     * @var Mage_Directory_Model_Currency_Filter - currency filter
     */
    protected $_filter;

    /**
     * Currency rates a caller has set, which win over the ones in the table
     *
     * @var array<string, mixed>
     */
    protected array $_rates = [];

    /**
     * Class constructor
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('directory/currency');
    }

    /**
     * Get currency code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->_getData('currency_code');
    }

    /**
     * Get currency code
     *
     * @return string
     */
    public function getCurrencyCode()
    {
        return $this->_getData('currency_code');
    }

    /**
     * The rates a caller set with setRates(); lookups are never memoised back here.
     *
     * @return array<string, mixed>
     */
    public function getRates(): array
    {
        return $this->_rates;
    }

    /**
     * Currency Rates setter
     *
     * @param array<string, mixed> $rates Currency Rates
     * @return $this
     */
    public function setRates(array $rates)
    {
        $this->_rates = [];
        foreach ($rates as $code => $rate) {
            $this->_rates[$this->_currencyCode((string) $code)] = $rate;
        }

        return $this;
    }

    /**
     * Loading currency data
     *
     * @param   string $id
     * @param   string $field
     * @return  $this
     */
    #[\Override]
    public function load($id, $field = null)
    {
        $this->_rates = [];
        $this->setData('currency_code', Mage::helper('directory')->normalizeCurrencyCode((string) $id));
        return $this;
    }

    /**
     * Caller-set rates belong to the currency being replaced, so they do not carry over.
     *
     * @param string $id
     * @return $this
     */
    #[\Override]
    public function setId($id)
    {
        $this->_rates = [];
        return parent::setId($id);
    }

    /**
     * Get currency rate (only base=>allowed)
     *
     * Null means there is no rate to convert with, never a rate of one.
     *
     * @param string|Mage_Directory_Model_Currency $toCurrency
     * @throws Mage_Core_Exception
     */
    #[\Deprecated(message: 'use Mage_Directory_Helper_Data::getRate(), which names both currencies')]
    public function getRate($toCurrency): ?float
    {
        $code = $this->_currencyCode($toCurrency);

        return array_key_exists($code, $this->_rates)
            ? $this->_callerRate($code)
            : $this->_getResource()->getRate($this->getCode(), $code);
    }

    /**
     * Get currency rate (base=>allowed or allowed=>base)
     *
     * Null means there is no rate to convert with, never a rate of one.
     *
     * @param string|Mage_Directory_Model_Currency $toCurrency
     * @throws Mage_Core_Exception
     */
    #[\Deprecated(message: 'use Mage_Directory_Helper_Data::getAnyRate(), which names both currencies')]
    public function getAnyRate($toCurrency): ?float
    {
        $code = $this->_currencyCode($toCurrency);

        return array_key_exists($code, $this->_rates)
            ? $this->_callerRate($code)
            : $this->_getResource()->getAnyRate($this->getCode(), $code);
    }

    /**
     * A caller-set rate answers even when unusable: an explicit "no rate" is an answer.
     */
    protected function _callerRate(string $code): ?float
    {
        $rate = $this->_rates[$code];

        return is_numeric($rate) && (float) $rate > 0 ? (float) $rate : null;
    }

    /**
     * @throws Mage_Core_Exception
     */
    protected function _currencyCode(mixed $toCurrency): string
    {
        if ($toCurrency instanceof self) {
            $toCurrency = $toCurrency->getCurrencyCode();
        } elseif (!is_string($toCurrency)) {
            throw Mage::exception('Mage_Directory', Mage::helper('directory')->__('Invalid target currency.'));
        }

        return Mage::helper('directory')->normalizeCurrencyCode($toCurrency);
    }

    /**
     * Convert price to currency format
     *
     * @param float $price
     * @param null|string|Mage_Directory_Model_Currency $toCurrency
     * @throws Mage_Core_Exception
     */
    #[\Deprecated(message: 'use Mage_Directory_Helper_Data::convert(), which names both currencies')]
    public function convert($price, $toCurrency = null): float
    {
        if ($toCurrency === null) {
            return (float) $price;
        }

        $rate = $this->getRate($toCurrency);
        if ($rate === null) {
            Mage::throwException(Mage::helper('directory')->__(
                'Undefined rate from "%s-%s".',
                $this->getCode(),
                $this->_currencyCode($toCurrency),
            ));
        }

        return (float) $price * $rate;
    }

    /**
     * Get currency filter
     *
     * @return Mage_Directory_Model_Currency_Filter
     */
    public function getFilter()
    {
        if (!$this->_filter) {
            $this->_filter = new Mage_Directory_Model_Currency_Filter($this->getCode());
        }

        return $this->_filter;
    }

    /**
     * Format price to currency format
     *
     * @param float $price
     * @param array $options
     * @param bool $includeContainer
     * @param bool $addBrackets
     * @return string
     */
    public function format($price, $options = [], $includeContainer = true, $addBrackets = false)
    {
        return $this->formatPrecision($price, 2, $options, $includeContainer, $addBrackets);
    }

    /**
     * Apply currency format to number with specific rounding precision
     *
     * @param   float $price
     * @param   int $precision
     * @param   array $options
     * @param   bool $includeContainer
     * @param   bool $addBrackets
     * @return  string
     */
    public function formatPrecision(
        $price,
        $precision,
        $options = [],
        $includeContainer = true,
        $addBrackets = false,
    ) {
        if (!isset($options['precision'])) {
            $options['precision'] = $precision;
        }
        if ($includeContainer) {
            return '<span class="price">' . ($addBrackets ? '[' : '') . $this->formatTxt($price, $options) .
                ($addBrackets ? ']' : '') . '</span>';
        }
        return $this->formatTxt($price, $options);
    }

    /**
     * Returns the formatted price
     *
     * @param float $price
     * @param null|array $options
     * @return string
     */
    public function formatTxt($price, $options = [])
    {
        if (!is_numeric($price)) {
            $price = Mage::app()->getLocale()->getNumber($price);
        }

        return Mage::app()->getLocale()->formatCurrency($price, $this->getCode());
    }

    /**
     * Returns the formatting template for numbers
     *
     * @return string
     */
    public function getOutputFormat()
    {
        return Mage::app()->getLocale()->getCurrencyOutputFormat($this->getCode());
    }

    /**
     * Returns the localized symbol for this currency, falling back to its code
     */
    public function getCurrencySymbol(): string
    {
        return Mage::app()->getLocale()->getCurrencySymbol($this->getCode());
    }

    /**
     * Retrieve allowed currencies according to config
     *
     * @return array
     */
    public function getConfigAllowCurrencies()
    {
        $allowedCurrencies = $this->_getResource()->getConfigCurrencies($this, self::XML_PATH_CURRENCY_ALLOW);
        $appBaseCurrencyCode = Mage::app()->getBaseCurrencyCode();
        if (!in_array($appBaseCurrencyCode, $allowedCurrencies)) {
            $allowedCurrencies[] = $appBaseCurrencyCode;
        }
        foreach (Mage::app()->getStores() as $store) {
            $code = $store->getBaseCurrencyCode();
            if (!in_array($code, $allowedCurrencies)) {
                $allowedCurrencies[] = $code;
            }
        }

        return $allowedCurrencies;
    }

    /**
     * Retrieve default currencies according to config
     *
     * @return array
     */
    public function getConfigDefaultCurrencies()
    {
        return $this->_getResource()->getConfigCurrencies($this, self::XML_PATH_CURRENCY_DEFAULT);
    }

    /**
     * Retrieve base currencies according to config
     *
     * @return array
     */
    public function getConfigBaseCurrencies()
    {
        return $this->_getResource()->getConfigCurrencies($this, self::XML_PATH_CURRENCY_BASE);
    }

    /**
     * Retrieve currency rates to other currencies
     *
     * @param array|string|Mage_Directory_Model_Currency $currency
     * @param array $toCurrencies
     * @return array
     */
    public function getCurrencyRates($currency, $toCurrencies = null)
    {
        if ($currency instanceof Mage_Directory_Model_Currency) {
            $currency = $currency->getCode();
        }
        return $this->_getResource()->getCurrencyRates($currency, $toCurrencies);
    }

    /**
     * Save currency rates
     *
     * @param array $rates
     * @return object
     */
    public function saveRates($rates)
    {
        // Dispatched only when something was stored: every listener drops caches on it
        if ($this->_getResource()->saveRates($rates) > 0) {
            Mage::dispatchEvent('directory_currency_rates_save_after', ['rates' => $this->_storedRates($rates)]);
        }
        return $this;
    }

    /**
     * The pairs the resource kept, so a listener is never told about a cell it rejected.
     *
     * @param array<string, array<string, mixed>> $rates
     * @return array<string, array<string, mixed>>
     */
    protected function _storedRates(array $rates): array
    {
        $stored = [];
        foreach ($rates as $currencyFrom => $currencies) {
            foreach ((array) $currencies as $currencyTo => $value) {
                if (Mage_Directory_Model_Resource_Currency::isStorableRate($value)) {
                    $stored[$currencyFrom][$currencyTo] = $value;
                }
            }
        }

        return $stored;
    }
}
