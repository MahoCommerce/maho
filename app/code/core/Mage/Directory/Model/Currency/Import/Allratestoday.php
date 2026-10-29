<?php

/**
 * Currency rate import from AllRatesToday: the keyless ECB reference table, or (with an API key)
 * the official table of one central bank.
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
     * Rate types a bank table may carry, most to least preferred. A table that only has buy and
     * sell rows is imported at their midpoint.
     */
    public const RATE_TYPE_PREFERENCE = ['reference', 'middle', 'mid', 'official'];

    /**
     * URL for the ECB reference table (no credentials; the endpoint always answers with every
     * currency it knows, so symbols are filtered on our side)
     *
     * @var string
     */
    protected $_url = 'https://allratestoday.com/api/open/latest/EUR';

    /**
     * URL template for the latest official table of one central bank (needs an API key)
     *
     * @var string
     */
    protected $_bankUrl = 'https://allratestoday.com/api/v1/central-bank/{{BANK}}/latest';

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
            $this->_messages[] = Mage::helper('directory')->__('An API key is required to import central bank tables from AllRatesToday.');
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
     * Central bank table:
     * {"bank":"sama","rate_date":"2026-10-28","stale":false,"rates":[{"base":"USD","quote":"SAR","type":"reference","value":3.75},...]}
     *
     * Every row says "1 base = value quote". Banks publish either foreign-per-home (the quote is
     * the home currency) or home-per-foreign (the base is), so the home currency is the code
     * common to every row. One rate type is selected per table, and the result is re-based on
     * EUR for the EUR-based import.
     */
    protected function _extractBankRates(array $response): ?array
    {
        $bank = $this->_getBank();

        if (isset($response['error']) || !isset($response['rates']) || !is_array($response['rates'])) {
            $this->_bankError($bank, $response['error'] ?? 'unexpected response');
            return null;
        }

        if (($response['stale'] ?? false) === true) {
            $this->_bankError($bank, sprintf('the table dated %s is stale', $response['rate_date'] ?? 'unknown'));
            return null;
        }

        $rows = [];
        $seen = [];
        $types = [];
        foreach ($response['rates'] as $row) {
            if (!is_array($row) || !isset($row['base'], $row['quote'], $row['value']) || !is_numeric($row['value']) || (float) $row['value'] <= 0) {
                continue;
            }
            $base = strtoupper((string) $row['base']);
            $quote = strtoupper((string) $row['quote']);
            $type = strtolower((string) ($row['type'] ?? 'reference'));
            $rows[] = [$base, $quote, (float) $row['value'], $type];
            $seen[$base] = ($seen[$base] ?? 0) + 1;
            $seen[$quote] = ($seen[$quote] ?? 0) + 1;
            $types[$type] = true;
        }

        if ($rows === []) {
            $this->_bankError($bank, 'no rates in the table');
            return null;
        }

        arsort($seen, SORT_NUMERIC);
        $home = (string) array_key_first($seen);

        // Home-currency units per one unit of each currency, for the selected rate type
        $units = [$home => 1.0];
        $selectedType = $this->_selectRateType($types);
        if ($selectedType !== null) {
            foreach ($rows as [$base, $quote, $value, $type]) {
                if ($type === $selectedType) {
                    $this->_addUnit($units, $home, $base, $quote, $value);
                }
            }
        } elseif (isset($types['buy'], $types['sell'])) {
            $sides = [];
            foreach ($rows as [$base, $quote, $value, $type]) {
                if ($type === 'buy' || $type === 'sell') {
                    $sides[$base . '/' . $quote][$type] = $value;
                }
            }
            foreach ($sides as $pair => $side) {
                if (isset($side['buy'], $side['sell'])) {
                    [$base, $quote] = explode('/', $pair);
                    $this->_addUnit($units, $home, $base, $quote, ($side['buy'] + $side['sell']) / 2);
                }
            }
        } else {
            $this->_bankError($bank, 'no usable rate type in the table (' . implode(', ', array_keys($types)) . ')');
            return null;
        }

        if (!isset($units['EUR'])) {
            $this->_bankError($bank, 'the table has no EUR rate');
            return null;
        }

        $rates = [];
        foreach ($units as $code => $unit) {
            $rates[$code] = $units['EUR'] / $unit;
        }

        return $rates;
    }

    /**
     * The most preferred rate type present in the table, or null when there is none
     *
     * @param array<string, true> $types
     */
    protected function _selectRateType(array $types): ?string
    {
        foreach (self::RATE_TYPE_PREFERENCE as $type) {
            if (isset($types[$type])) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Record "1 base = value quote" as home-currency units per unit of the foreign currency
     *
     * @param array<string, float> $units
     */
    protected function _addUnit(array &$units, string $home, string $base, string $quote, float $value): void
    {
        if ($quote === $home) {
            $units[$base] = $value;
        } elseif ($base === $home) {
            $units[$quote] = 1 / $value;
        }
    }

    protected function _bankError(string $bank, string $reason): void
    {
        Mage::log("AllRatesToday error ($bank): $reason", Mage::LOG_ERROR);
        $this->_messages[] = Mage::helper('directory')->__('Currency rate service error: %s', "$bank: $reason");
    }
}
