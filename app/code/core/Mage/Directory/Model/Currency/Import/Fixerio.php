<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

class Mage_Directory_Model_Currency_Import_Fixerio extends Mage_Directory_Model_Currency_Import_Eurbased
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

    #[\Override]
    protected function _getTimeout(): int
    {
        return Mage::getStoreConfigAsInt(self::XML_PATH_FIXERIO_TIMEOUT);
    }

    #[\Override]
    protected function _buildRequestUrl(array $symbols): ?string
    {
        $accessKey = Mage::getStoreConfig(self::XML_PATH_FIXERIO_API_KEY);
        if (empty($accessKey)) {
            $this->_messages[] = Mage::helper('directory')
                ->__('No API Key was specified or an invalid API Key was specified.');
            return null;
        }

        return str_replace(
            ['{{ACCESS_KEY}}', '{{SYMBOLS}}'],
            [rawurlencode((string) $accessKey), implode(',', $symbols)],
            $this->_url,
        );
    }

    #[\Override]
    protected function _extractRates(array $response): ?array
    {
        if (!$this->_validateResponse($response, 'EUR')) {
            return null;
        }

        return $response['rates'] ?? [];
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
}
