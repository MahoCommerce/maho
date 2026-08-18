<?php

/**
 * Base for rate services quoted against EUR, from which every other pair is derived.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

abstract class Mage_Directory_Model_Currency_Import_Eurbased extends Mage_Directory_Model_Currency_Import_Abstract
{
    /**
     * @var \Symfony\Contracts\HttpClient\HttpClientInterface
     */
    protected $_httpClient;

    /**
     * Cached EUR-based rates from the service
     *
     * @var array|null
     */
    protected $_eurRates = null;

    public function __construct()
    {
        $this->_httpClient = \Maho\Http\Client::create();
    }

    /**
     * Request URL for the given symbols, or null when the service is not usable (the reason
     * having been pushed onto the message stack).
     */
    abstract protected function _buildRequestUrl(array $symbols): ?string;

    /**
     * EUR-based rates from a decoded service response, or null when the response reports an
     * error (the reason having been pushed onto the message stack).
     */
    abstract protected function _extractRates(array $response): ?array;

    abstract protected function _getTimeout(): int;

    #[\Override]
    protected function _convert($currencyFrom, $currencyTo)
    {
        return 1;
    }

    /**
     * Fetching of the currency rates data
     *
     * Uses EUR as base currency and derives cross-rates mathematically, so a single request
     * covers every configured base currency.
     *
     * @return array
     */
    #[\Override]
    public function fetchRates()
    {
        $data = [];
        $currencies = $this->_getCurrencyCodes();
        $defaultCurrencies = $this->_getDefaultCurrencyCodes();

        $eurRates = $this->_fetchEurRates($currencies);
        if ($eurRates === null) {
            foreach ($defaultCurrencies as $currencyFrom) {
                $data[$currencyFrom] = $this->_makeEmptyResponse($currencies);
            }
            return $data;
        }

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
     * Fetch EUR-based rates for all currencies in one request
     *
     * @return array|null Returns rates array or null on error
     */
    protected function _fetchEurRates(array $currencies)
    {
        if ($this->_eurRates !== null) {
            return $this->_eurRates;
        }

        $symbols = array_values(array_unique(array_merge(['EUR'], $currencies)));
        if ($symbols === ['EUR']) {
            $this->_eurRates = ['EUR' => 1.0];
            return $this->_eurRates;
        }

        $url = $this->_buildRequestUrl($symbols);
        if ($url === null) {
            return null;
        }

        $timeLimitCalculated = 2 * $this->_getTimeout() + (int) ini_get('max_execution_time');

        @set_time_limit($timeLimitCalculated);
        try {
            $response = $this->_getServiceResponse($url);
        } finally {
            ini_restore('max_execution_time');
        }

        $rates = $this->_extractRates($response);
        if ($rates === null) {
            return null;
        }

        $rates['EUR'] = 1.0;
        $this->_eurRates = $rates;

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
                'timeout' => $this->_getTimeout(),
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

        return is_array($response) ? $response : [];
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
