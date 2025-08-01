<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
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
     * URL template for currency rates import
     *
     * @var string
     */
    protected $_url = 'https://free.currconv.com/api/v7/convert?apiKey={{API_KEY}}&q={{CURRENCY_FROM}}_{{CURRENCY_TO}}&compact=ultra';

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
     * Create and set HTTP Client
     */
    public function __construct()
    {
        $this->_httpClient = \Symfony\Component\HttpClient\HttpClient::create();
    }

    #[\Override]
    protected function _convert($currencyFrom, $currencyTo)
    {
        return 1;
    }

    /**
     * Fetching of the currency rates data
     *
     * @return array
     */
    #[\Override]
    public function fetchRates()
    {
        $data = [];
        $currencies = $this->_getCurrencyCodes();
        $defaultCurrencies = $this->_getDefaultCurrencyCodes();

        foreach ($defaultCurrencies as $currencyFrom) {
            if (!isset($data[$currencyFrom])) {
                $data[$currencyFrom] = [];
            }

            $data = $this->_convertBatch($data, $currencyFrom, $currencies);
            ksort($data[$currencyFrom]);
        }

        return $data;
    }

    /**
     * Batch import of currency rates
     *
     * @param string $currencyFrom
     * @return array
     */
    protected function _convertBatch(array $data, $currencyFrom, array $currenciesTo)
    {
        $apiKey = Mage::getStoreConfig(self::XML_PATH_CURRENCY_CONVERTER_API_KEY);
        if (empty($apiKey)) {
            $this->_messages[] = Mage::helper('directory')
                ->__('No API Key was specified or an invalid API Key was specified.');
            $data[$currencyFrom] = $this->_makeEmptyResponse($currenciesTo);
            return $data;
        }

        foreach ($currenciesTo as $currencyTo) {
            $currenciesCombined = $currencyFrom . '_' . $currencyTo;
            $url = str_replace(
                ['{{API_KEY}}', '{{CURRENCY_FROM}}_{{CURRENCY_TO}}'],
                [$apiKey, $currenciesCombined],
                $this->_url,
            );

            $timeLimitCalculated = 2 * Mage::getStoreConfigAsInt(self::XML_PATH_CURRENCY_CONVERTER_TIMEOUT)
                + (int) ini_get('max_execution_time');

            @set_time_limit($timeLimitCalculated);
            try {
                $response = $this->_getServiceResponse($url);
            } catch (Exception $e) {
                ini_restore('max_execution_time');
            }

            if ($currencyFrom == $currencyTo) {
                $data[$currencyFrom][$currencyTo] = $this->_numberFormat(1);
            } else {
                if (empty($response)) {
                    $this->_messages[] = Mage::helper('directory')
                        ->__('We can\'t retrieve a rate from %s for %s.', $url, $currencyTo);
                    $data[$currencyFrom][$currencyTo] = null;
                } else {
                    if (isset($response[$currenciesCombined])) {
                        $data[$currencyFrom][$currencyTo] = $this->_numberFormat((float) $response[$currenciesCombined]);
                    } else {
                        $this->_messages[] = Mage::helper('directory')
                            ->__('We can\'t retrieve a rate from %s for %s.', $url, $currencyTo);
                        $data[$currencyFrom][$currencyTo] = null;
                    }
                }
            }
        }

        return $data;
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
            $jsonResponse = $httpResponse->getContent();

            $response = json_decode($jsonResponse, true);
        } catch (Exception $e) {
            if ($retry === 0) {
                $response = $this->_getServiceResponse($url, 1);
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
