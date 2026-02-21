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

class Mage_Directory_Model_Currency_Import_Currencyconverterapi extends Mage_Directory_Model_Currency_Import_Abstract
{
    /**
     * XML path to Currency Converter timeout setting
     */
    public const XML_PATH_CURRENCY_CONVERTER_TIMEOUT = 'currency/currencyconverterapi/timeout';

    /**
     * XML path to Currency Converter API key setting
     */
    public const XML_PATH_CURRENCY_CONVERTER_API_KEY = 'currency/currencyconverterapi/api_key';

    /**
     * URL template for currency rates import (fetches rates relative to USD)
     *
     * @var string
     */
    protected $_url = 'https://free.currconv.com/api/v8/convert?apiKey={{API_KEY}}&q={{PAIRS}}&compact=ultra';

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
     * Cached USD-based rates
     *
     * @var array|null
     */
    protected $_usdRates = null;

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
     * Uses USD as base currency and calculates cross-rates mathematically.
     * Batches API requests (2 pairs per request) to minimize API calls.
     * Free tier: 100 requests/hour, max 2 pairs per request.
     *
     * @return array
     */
    #[\Override]
    public function fetchRates()
    {
        $data = [];
        $currencies = $this->_getCurrencyCodes();
        $defaultCurrencies = $this->_getDefaultCurrencyCodes();

        // Fetch USD-based rates for all currencies
        $usdRates = $this->_fetchUsdRates($currencies);
        if ($usdRates === null) {
            foreach ($defaultCurrencies as $currencyFrom) {
                $data[$currencyFrom] = $this->_makeEmptyResponse($currencies);
            }
            return $data;
        }

        // Calculate rates for each base currency using USD rates
        foreach ($defaultCurrencies as $currencyFrom) {
            $data[$currencyFrom] = [];
            foreach ($currencies as $currencyTo) {
                if ($currencyFrom === $currencyTo) {
                    $data[$currencyFrom][$currencyTo] = $this->_numberFormat(1);
                } else {
                    $rate = $this->_calculateCrossRate($usdRates, $currencyFrom, $currencyTo);
                    $data[$currencyFrom][$currencyTo] = $rate !== null ? $this->_numberFormat($rate) : null;
                }
            }
            ksort($data[$currencyFrom]);
        }

        return $data;
    }

    /**
     * Fetch USD-based rates for all currencies
     *
     * Makes batched API calls (2 pairs per request) to minimize API usage.
     *
     * @return array|null Returns rates array or null on error
     */
    protected function _fetchUsdRates(array $currencies)
    {
        if ($this->_usdRates !== null) {
            return $this->_usdRates;
        }

        $apiKey = Mage::getStoreConfig(self::XML_PATH_CURRENCY_CONVERTER_API_KEY);
        if (empty($apiKey)) {
            $this->_messages[] = Mage::helper('directory')
                ->__('No API Key was specified or an invalid API Key was specified.');
            return null;
        }

        $this->_usdRates = ['USD' => 1.0];

        // Build list of pairs we need (USD to each non-USD currency)
        $pairs = [];
        foreach ($currencies as $currency) {
            if ($currency !== 'USD') {
                $pairs[] = 'USD_' . $currency;
            }
        }

        if (empty($pairs)) {
            return $this->_usdRates;
        }

        // Batch pairs (max 2 per request for free tier)
        $batches = array_chunk($pairs, 2);

        $timeLimitCalculated = 2 * Mage::getStoreConfigAsInt(self::XML_PATH_CURRENCY_CONVERTER_TIMEOUT)
            + (int) ini_get('max_execution_time');

        foreach ($batches as $batch) {
            $pairsString = implode(',', $batch);
            $url = str_replace(
                ['{{API_KEY}}', '{{PAIRS}}'],
                [$apiKey, $pairsString],
                $this->_url,
            );

            @set_time_limit($timeLimitCalculated);
            try {
                $response = $this->_getServiceResponse($url);
            } catch (Exception $e) {
                Mage::log('CurrencyConverterAPI exception: ' . $e->getMessage(), Mage::LOG_ERROR);
                ini_restore('max_execution_time');
                continue;
            }

            // Check for API error
            if (isset($response['error'])) {
                $errorMessage = is_string($response['error']) ? $response['error'] : json_encode($response['error']);
                Mage::log('CurrencyConverterAPI error: ' . $errorMessage, Mage::LOG_ERROR);
                $this->_messages[] = Mage::helper('directory')
                    ->__('Currency rate service error: %s', $errorMessage);
                continue;
            }

            // Extract rates from response
            foreach ($batch as $pair) {
                if (isset($response[$pair])) {
                    $currency = substr($pair, 4); // Remove "USD_" prefix
                    $this->_usdRates[$currency] = (float) $response[$pair];
                } else {
                    $currency = substr($pair, 4);
                    $this->_messages[] = Mage::helper('directory')
                        ->__('Unable to retrieve rate for USD to %s.', $currency);
                }
            }
        }

        return $this->_usdRates;
    }

    /**
     * Calculate cross-rate from USD-based rates
     *
     * Formula: rate(FROM→TO) = rate(USD→TO) / rate(USD→FROM)
     *
     * @param string $currencyFrom
     * @param string $currencyTo
     * @return float|null
     */
    protected function _calculateCrossRate(array $usdRates, $currencyFrom, $currencyTo)
    {
        $usdToFrom = $usdRates[$currencyFrom] ?? null;
        $usdToTo = $usdRates[$currencyTo] ?? null;

        if ($usdToFrom === null || $usdToTo === null || $usdToFrom == 0) {
            $this->_messages[] = Mage::helper('directory')
                ->__('Unable to calculate rate for %s to %s.', $currencyFrom, $currencyTo);
            return null;
        }

        return (float) $usdToTo / (float) $usdToFrom;
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
                'timeout' => Mage::getStoreConfig(self::XML_PATH_CURRENCY_CONVERTER_TIMEOUT),
            ]);
            $jsonResponse = $httpResponse->getContent(false);

            $response = json_decode($jsonResponse, true) ?? [];
        } catch (Exception $e) {
            if ($retry === 0) {
                $response = $this->_getServiceResponse($url, 1);
            } else {
                Mage::log('CurrencyConverterAPI error: ' . $e->getMessage(), Mage::LOG_ERROR);
            }
        }

        return $response;
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
