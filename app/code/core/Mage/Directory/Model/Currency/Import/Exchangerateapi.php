<?php

/**
 * Currency rate import from ExchangeRate-API's open access endpoint.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

class Mage_Directory_Model_Currency_Import_Exchangerateapi extends Mage_Directory_Model_Currency_Import_Eurbased
{
    public const XML_PATH_EXCHANGERATEAPI_TIMEOUT = 'currency/exchangerateapi/timeout';

    /**
     * URL for currency rates import (the open endpoint needs no credentials and always
     * answers with every currency it knows, so symbols are filtered on our side)
     *
     * @var string
     */
    protected $_url = 'https://open.er-api.com/v6/latest/EUR';

    #[\Override]
    protected function _getTimeout(): int
    {
        return Mage::getStoreConfigAsInt(self::XML_PATH_EXCHANGERATEAPI_TIMEOUT);
    }

    #[\Override]
    protected function _buildRequestUrl(array $symbols): ?string
    {
        return $this->_url;
    }

    #[\Override]
    protected function _extractRates(array $response): ?array
    {
        if (($response['result'] ?? null) !== 'success' || !isset($response['rates']) || !is_array($response['rates'])) {
            Mage::log(
                'ExchangeRate-API error: ' . ($response['error-type'] ?? 'unexpected response'),
                Mage::LOG_ERROR,
            );
            $this->_messages[] = Mage::helper('directory')->__('Currency rates can\'t be retrieved.');
            return null;
        }

        return $response['rates'];
    }
}
