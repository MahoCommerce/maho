<?php

/**
 * Currency rate import from AllRatesToday's open endpoints: the ECB reference table, or the
 * official table of one central bank.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

class Mage_Directory_Model_Currency_Import_Allratestoday extends Mage_Directory_Model_Currency_Import_Eurbased
{
    public const XML_PATH_ALLRATESTODAY_TIMEOUT = 'currency/allratestoday/timeout';
    public const XML_PATH_ALLRATESTODAY_BANK = 'currency/allratestoday/bank';

    /**
     * URL for the ECB reference table (no credentials; the endpoint always answers with every
     * currency it knows, so symbols are filtered on our side)
     *
     * @var string
     */
    protected $_url = 'https://allratestoday.com/api/open/latest/EUR';

    /**
     * URL template for the latest official table of one central bank
     *
     * @var string
     */
    protected $_bankUrl = 'https://allratestoday.com/api/open/central-bank/{{BANK}}';

    #[\Override]
    protected function _getTimeout(): int
    {
        return Mage::getStoreConfigAsInt(self::XML_PATH_ALLRATESTODAY_TIMEOUT);
    }

    /**
     * Lower-case code of the configured central bank, or '' for the ECB table
     */
    protected function _getBank(): string
    {
        return strtolower(trim((string) Mage::getStoreConfig(self::XML_PATH_ALLRATESTODAY_BANK)));
    }

    #[\Override]
    protected function _buildRequestUrl(array $symbols): ?string
    {
        $bank = $this->_getBank();
        if ($bank === '') {
            return $this->_url;
        }

        return str_replace('{{BANK}}', rawurlencode($bank), $this->_bankUrl);
    }

    #[\Override]
    protected function _extractRates(array $response): ?array
    {
        return $this->_getBank() === ''
            ? $this->_extractEcbRates($response)
            : $this->_extractBankRates($response);
    }

    /**
     * ECB table: {"result":"success","base_code":"EUR","rates":{"USD":1.08,...}}
     */
    protected function _extractEcbRates(array $response): ?array
    {
        if (($response['result'] ?? null) !== 'success' || !isset($response['rates']) || !is_array($response['rates'])) {
            Mage::log(
                'AllRatesToday error: ' . ($response['error'] ?? 'unexpected response'),
                Mage::LOG_ERROR,
            );
            $this->_messages[] = Mage::helper('directory')->__('Currency rates can\'t be retrieved.');
            return null;
        }

        return $response['rates'];
    }

    /**
     * Central bank table: {"bank":"sama","rates":[{"base":"USD","quote":"SAR","value":3.75},...]}
     *
     * Every row says "1 base = value quote". Banks publish either foreign-per-home (the quote is
     * the home currency) or home-per-foreign (the base is), so the home currency is the code
     * common to every row. The table is then re-based on EUR for the EUR-based import.
     */
    protected function _extractBankRates(array $response): ?array
    {
        $bank = $this->_getBank();
        $rows = [];
        $seen = [];
        foreach ($response['rates'] ?? [] as $row) {
            if (!is_array($row) || !isset($row['base'], $row['quote'], $row['value']) || !is_numeric($row['value']) || (float) $row['value'] <= 0) {
                continue;
            }
            $base = strtoupper((string) $row['base']);
            $quote = strtoupper((string) $row['quote']);
            $rows[] = [$base, $quote, (float) $row['value']];
            $seen[$base] = ($seen[$base] ?? 0) + 1;
            $seen[$quote] = ($seen[$quote] ?? 0) + 1;
        }

        if ($rows === []) {
            Mage::log(
                'AllRatesToday error: ' . ($response['error'] ?? "no rates for central bank $bank"),
                Mage::LOG_ERROR,
            );
            $this->_messages[] = Mage::helper('directory')->__('Currency rates can\'t be retrieved.');
            return null;
        }

        arsort($seen, SORT_NUMERIC);
        $home = (string) array_key_first($seen);

        // Home-currency units per one unit of each currency
        $units = [$home => 1.0];
        foreach ($rows as [$base, $quote, $value]) {
            if ($quote === $home) {
                $units[$base] = $value;
            } elseif ($base === $home) {
                $units[$quote] = 1 / $value;
            }
        }

        if (!isset($units['EUR'])) {
            Mage::log("AllRatesToday error: central bank $bank publishes no EUR rate", Mage::LOG_ERROR);
            $this->_messages[] = Mage::helper('directory')->__('Currency rate service error: %s', "central bank $bank publishes no EUR rate");
            return null;
        }

        $rates = [];
        foreach ($units as $code => $unit) {
            $rates[$code] = $units['EUR'] / $unit;
        }

        return $rates;
    }
}
