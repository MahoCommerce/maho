<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Model_Currency_Import_Fixerio extends Mage_Directory_Model_Currency_Import_Abstract
{
    /**
     * XML path to Fixer.IO timeout setting
     */
    public const XML_PATH_FIXERIO_TIMEOUT = 'currency/fixerio/timeout';

    /**
     * XML path to Fixer.IO API key setting
     */
    public const XML_PATH_FIXERIO_API_KEY = 'currency/fixerio/api_key';

    /**
     * URL template for currency rates import (always uses EUR as base for free tier compatibility)
     *
     * @var string
     */
    protected $_url = 'https://data.fixer.io/api/latest?access_key={{ACCESS_KEY}}&symbols={{SYMBOLS}}';

    /**
     * Information messages stack
     *
     * @var array
     */
    protected $_messages = [];

    /**
     * HTTP client
     *
     * @var \Symfony\Contracts\HttpClient\HttpClientInterface
     */
    protected $_httpClient;

    /**
     * Cached EUR-based rates from API
     *
     * @var array|null
     */
    protected $_eurRates = null;

    /**
     * Create and set HTTP Client
     */
    public function __construct()
    {
        $this->_httpClient = \Maho\Http\Client::create();
    }

    #[\Override]
    protected function _convert($currencyFrom, $currencyTo)
    {
        return 1;
    }

    /**
     * Fetching of the currency rates data
     *
     * Uses EUR as base currency (free tier limitation) and calculates cross-rates mathematically.
     * This uses only ONE API call regardless of how many base currencies are configured.
     *
     * @return array
     */
    #[\Override]
    public function fetchRates()
    {
        $data = [];
        $currencies = $this->_getCurrencyCodes();
        $defaultCurrencies = $this->_getDefaultCurrencyCodes();

        // Fetch EUR-based rates for all currencies in one API call
        $eurRates = $this->_fetchEurRates($currencies);
        if ($eurRates === null) {
            // Error already logged, return empty data
            foreach ($defaultCurrencies as $currencyFrom) {
                $data[$currencyFrom] = $this->_makeEmptyResponse($currencies);
            }
            return $data;
        }

        // Calculate rates for each base currency using EUR rates
        foreach ($defaultCurrencies as $currencyFrom) {
            $data[$currencyFrom] = [];
            foreach ($currencies as $currencyTo) {
                if ($currencyFrom === $currencyTo) {
                    $data[$currencyFrom][$currencyTo] = $this->_numberFormat(1);
                } else {
                    $rate = $this->_calculateCrossRate($eurRates, $currencyFrom, $currencyTo);
                    $data[$currencyFrom][$currencyTo] = $rate !== null ? $this->_numberFormat($rate) : null;
                }
            }
            ksort($data[$currencyFrom]);
        }

        return $data;
    }

    /**
     * Fetch EUR-based rates for all currencies in one API call
     *
     * @return array|null Returns rates array or null on error
     */
    protected function _fetchEurRates(array $currencies)
    {
        if ($this->_eurRates !== null) {
            return $this->_eurRates;
        }

        $accessKey = Mage::getStoreConfig(self::XML_PATH_FIXERIO_API_KEY);
        if (empty($accessKey)) {
            $this->_messages[] = Mage::helper('directory')
                ->__('No API Key was specified or an invalid API Key was specified.');
            return null;
        }

        // Always include EUR in the symbols list
        $allCurrencies = array_unique(array_merge(['EUR'], $currencies));
        $symbols = implode(',', $allCurrencies);

        $url = str_replace(
            ['{{ACCESS_KEY}}', '{{SYMBOLS}}'],
            [$accessKey, $symbols],
            $this->_url,
        );

        $timeLimitCalculated = 2 * Mage::getStoreConfigAsInt(self::XML_PATH_FIXERIO_TIMEOUT)
            + (int) ini_get('max_execution_time');

        @set_time_limit($timeLimitCalculated);
        try {
            $response = $this->_getServiceResponse($url);
        } catch (Exception $e) {
            Mage::log('Fixer.io exception: ' . $e->getMessage(), Mage::LOG_ERROR);
            ini_restore('max_execution_time');
            return null;
        }

        if (!$this->_validateResponse($response, 'EUR')) {
            return null;
        }

        // EUR rate is always 1
        $this->_eurRates = $response['rates'] ?? [];
        $this->_eurRates['EUR'] = 1.0;

        return $this->_eurRates;
    }

    /**
     * Calculate cross-rate from EUR-based rates
     *
     * Formula: rate(FROM→TO) = rate(EUR→TO) / rate(EUR→FROM)
     *
     * @param string $currencyFrom
     * @param string $currencyTo
     * @return float|null
     */
    protected function _calculateCrossRate(array $eurRates, $currencyFrom, $currencyTo)
    {
        $eurToFrom = $eurRates[$currencyFrom] ?? null;
        $eurToTo = $eurRates[$currencyTo] ?? null;

        if ($eurToFrom === null || $eurToTo === null || $eurToFrom == 0) {
            $this->_messages[] = Mage::helper('directory')
                ->__('Unable to calculate rate for %s to %s.', $currencyFrom, $currencyTo);
            return null;
        }

        return (float) $eurToTo / (float) $eurToFrom;
    }

    /**
     * Get response from external service
     *
     * @param string $url
     * @param int $retry
     * @return array
     */
    protected function _getServiceResponse($url, $retry = 0)
    {
        $response = [];
        try {
            $httpResponse = $this->_httpClient->request('GET', $url, [
                'timeout' => Mage::getStoreConfig(self::XML_PATH_FIXERIO_TIMEOUT),
            ]);
            // Use false to not throw on HTTP errors, allowing us to read error response body
            $jsonResponse = $httpResponse->getContent(false);

            $response = json_decode($jsonResponse, true) ?? [];
        } catch (Exception $e) {
            if ($retry === 0) {
                $response = $this->_getServiceResponse($url, 1);
            } else {
                Mage::log('Currency import error: ' . $e->getMessage(), Mage::LOG_ERROR);
            }
        }

        return $response;
    }

    /**
     * Validate response from external service
     *
     * @param string $baseCurrency
     * @return bool
     */
    protected function _validateResponse(array $response, $baseCurrency)
    {
        if (!isset($response['success']) || !$response['success']) {
            $errorCodes = [
                101 => Mage::helper('directory')
                    ->__('No API Key was specified or an invalid API Key was specified.'),
                102 => Mage::helper('directory')
                    ->__('The account this API request is coming from is inactive.'),
                104 => Mage::helper('directory')
                    ->__('The maximum allowed API amount of monthly API requests has been reached.'),
                105 => Mage::helper('directory')
                    ->__('The "%s" is not allowed as base currency for your subscription plan.', $baseCurrency),
                106 => Mage::helper('directory')
                    ->__('The current request did not return any results.'),
                201 => Mage::helper('directory')
                    ->__('An invalid base currency has been entered.'),
                202 => Mage::helper('directory')
                    ->__('One or more invalid symbols have been specified.'),
            ];

            $errorCode = $response['error']['code'] ?? null;
            $this->_messages[] = ($errorCode !== null && isset($errorCodes[$errorCode]))
                ? $errorCodes[$errorCode]
                : Mage::helper('directory')->__('Currency rates can\'t be retrieved.');

            return false;
        }

        return true;
    }

    /**
     * Fill simulated response with empty data
     *
     * @return array
     */
    protected function _makeEmptyResponse(array $currenciesTo)
    {
        return array_fill_keys($currenciesTo, null);
    }
}
