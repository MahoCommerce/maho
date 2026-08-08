<?php

/**
 * Currency rate import from Frankfurter, which aggregates central bank reference rates.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

class Mage_Directory_Model_Currency_Import_Frankfurter extends Mage_Directory_Model_Currency_Import_Eurbased
{
    public const XML_PATH_FRANKFURTER_TIMEOUT = 'currency/frankfurter/timeout';

    /**
     * URL template for currency rates import (the service needs no credentials)
     *
     * @var string
     */
    protected $_url = 'https://api.frankfurter.dev/v2/rates?base=EUR&quotes={{SYMBOLS}}';

    #[\Override]
    protected function _getTimeout(): int
    {
        return Mage::getStoreConfigAsInt(self::XML_PATH_FRANKFURTER_TIMEOUT);
    }

    #[\Override]
    protected function _buildRequestUrl(array $symbols): ?string
    {
        return str_replace('{{SYMBOLS}}', implode(',', $symbols), $this->_url);
    }

    #[\Override]
    protected function _extractRates(array $response): ?array
    {
        // Success is a non-empty flat list of {date, base, quote, rate}; anything else is an
        // error object, or the empty array a transport failure decodes to.
        if ($response === [] || !array_is_list($response)) {
            $message = isset($response['message']) ? (string) $response['message'] : null;
            Mage::log('Frankfurter error: ' . ($message ?? 'unexpected response'), Mage::LOG_ERROR);
            $this->_messages[] = $message !== null
                ? Mage::helper('directory')->__('Currency rate service error: %s', $message)
                : Mage::helper('directory')->__('Currency rates can\'t be retrieved.');
            return null;
        }

        $rates = [];
        foreach ($response as $quote) {
            if (isset($quote['quote'], $quote['rate'])) {
                $rates[(string) $quote['quote']] = (float) $quote['rate'];
            }
        }

        return $rates;
    }
}
