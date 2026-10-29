<?php

/**
 * Currency rate import from AllRatesToday: the ECB reference table with no API key, or the official
 * table of one central bank with an API key.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

class Mage_Directory_Model_Currency_Import_Allratestoday extends Mage_Directory_Model_Currency_Import_Eurbased
{
    public const XML_PATH_ALLRATESTODAY_TIMEOUT = 'currency/allratestoday/timeout';
    public const XML_PATH_ALLRATESTODAY_BANK = 'currency/allratestoday/bank';
    public const XML_PATH_ALLRATESTODAY_API_KEY = 'currency/allratestoday/api_key';

    /**
     * URL of the ECB reference table. It needs no API key. The service returns all its currencies,
     * and the parent class keeps only the store currencies.
     *
     * @var string
     */
    protected $_url = 'https://allratestoday.com/api/open/latest/EUR';

    /**
     * URL of the latest official table of one central bank. It needs an API key, because the
     * official-rate endpoint without a key has no license for stores.
     */
    protected string $_bankUrl = 'https://allratestoday.com/api/v1/central-bank/{{BANK}}/latest';

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

    protected function _getApiKey(): string
    {
        return trim((string) Mage::getStoreConfig(self::XML_PATH_ALLRATESTODAY_API_KEY));
    }

    #[\Override]
    protected function _getRequestHeaders(): array
    {
        $apiKey = $this->_getApiKey();
        if ($this->_getBank() === '' || $apiKey === '') {
            return [];
        }

        return ['Authorization' => 'Bearer ' . $apiKey];
    }

    #[\Override]
    protected function _buildRequestUrl(array $symbols): ?string
    {
        $bank = $this->_getBank();
        if ($bank === '') {
            return $this->_url;
        }

        if ($this->_getApiKey() === '') {
            $this->_messages[] = Mage::helper('directory')->__('An API key is required to import a central bank table from AllRatesToday.');
            return null;
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
     * Central bank table: {"bank":"sama","rates":[{"base":"USD","quote":"SAR","type":"reference","value":3.75},...]}
     *
     * Each row means: 1 base = value quote. The home currency is the code that occurs in the
     * most rows. The method converts every rate to a rate from EUR.
     */
    protected function _extractBankRates(array $response): ?array
    {
        $bank = $this->_getBank();

        if (isset($response['error']) || !isset($response['rates']) || !is_array($response['rates'])) {
            Mage::log(
                'AllRatesToday error: ' . (string) ($response['error'] ?? "unexpected response for central bank $bank"),
                Mage::LOG_ERROR,
            );
            $this->_messages[] = Mage::helper('directory')->__('Currency rates can\'t be retrieved.');
            return null;
        }

        // Reject a stale table. The scheduled import then tries the next service.
        if (($response['stale'] ?? false) === true) {
            $rateDate = (string) ($response['rate_date'] ?? 'unknown');
            Mage::log("AllRatesToday error: the $bank table dated $rateDate is stale", Mage::LOG_ERROR);
            $this->_messages[] = Mage::helper('directory')->__('Central bank %s has not published a new table. The last table is dated %s.', $bank, $rateDate);
            return null;
        }

        $valuesByType = [];
        foreach ($response['rates'] as $row) {
            if (!is_array($row) || !isset($row['base'], $row['quote'], $row['value']) || !is_numeric($row['value']) || (float) $row['value'] <= 0) {
                continue;
            }
            $pair = strtoupper((string) $row['base']) . '/' . strtoupper((string) $row['quote']);
            $valuesByType[$pair][(string) ($row['type'] ?? '')] = (float) $row['value'];
        }

        $rows = [];
        $rowCountByCode = [];
        foreach ($valuesByType as $pair => $types) {
            // Without a reference-type row, a pair has one rate or a buy and a sell rate: take the mean
            $value = $types['reference'] ?? $types['middle'] ?? $types['mid'] ?? $types['official']
                ?? (count($types) <= 2 ? array_sum($types) / count($types) : null);
            if ($value === null) {
                continue;
            }
            [$base, $quote] = explode('/', $pair);
            $rows[] = [$base, $quote, $value];
            $rowCountByCode[$base] = ($rowCountByCode[$base] ?? 0) + 1;
            $rowCountByCode[$quote] = ($rowCountByCode[$quote] ?? 0) + 1;
        }

        if ($rows === []) {
            Mage::log("AllRatesToday error: no usable rates for central bank $bank", Mage::LOG_ERROR);
            $this->_messages[] = Mage::helper('directory')->__('Currency rates can\'t be retrieved.');
            return null;
        }

        arsort($rowCountByCode, SORT_NUMERIC);
        $home = (string) array_key_first($rowCountByCode);

        $homeUnits = [$home => 1.0];
        foreach ($rows as [$base, $quote, $value]) {
            if ($quote === $home) {
                $homeUnits[$base] = $value;
            } elseif ($base === $home) {
                $homeUnits[$quote] = 1 / $value;
            }
        }

        if (!isset($homeUnits['EUR'])) {
            Mage::log("AllRatesToday error: central bank $bank publishes no EUR rate", Mage::LOG_ERROR);
            $this->_messages[] = Mage::helper('directory')->__('Central bank %s publishes no EUR rate.', $bank);
            return null;
        }

        $rates = [];
        foreach ($homeUnits as $code => $homeUnit) {
            $rates[$code] = $homeUnits['EUR'] / $homeUnit;
        }

        return $rates;
    }
}
