<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

class Mage_Directory_Model_Resource_Currency extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Scale of the rate column, DECIMAL(24,12)
     */
    public const RATE_SCALE = 12;

    /**
     * Currency rate table
     *
     * @var string
     */
    protected $_currencyRateTable;

    /**
     * Currency rate cache array, keyed by the uppercased codes
     *
     * @var array<string, array<string, float|null>>|null
     */
    protected static ?array $_rateCache = null;

    /**
     * Define main and currency rate tables
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('directory/currency', 'currency_code');
        $this->_currencyRateTable   = $this->getTable('directory/currency_rate');
    }

    /**
     * Retrieve currency rate (only base=>allowed)
     *
     * Null means there is no rate to convert with, never a rate of one.
     *
     * @param Mage_Directory_Model_Currency|string $currencyFrom
     * @param Mage_Directory_Model_Currency|string $currencyTo
     */
    public function getRate($currencyFrom, $currencyTo): ?float
    {
        $currencyFrom = $this->_currencyCode($currencyFrom);
        $currencyTo   = $this->_currencyCode($currencyTo);

        if ($currencyFrom === $currencyTo) {
            return 1.0;
        }

        if (!array_key_exists($currencyTo, self::$_rateCache[$currencyFrom] ?? [])) {
            $read = $this->_getReadAdapter();
            $bind = [
                ':currency_from' => $currencyFrom,
                ':currency_to'   => $currencyTo,
            ];
            $select = $read->select()
                ->from($this->_currencyRateTable, 'rate')
                ->where('currency_from = :currency_from')
                ->where('currency_to = :currency_to');

            self::$_rateCache[$currencyFrom][$currencyTo] = $this->_rateValue($read->fetchOne($select, $bind));
        }

        return self::$_rateCache[$currencyFrom][$currencyTo];
    }

    /**
     * Retrieve currency rate (base=>allowed or allowed=>base)
     *
     * Null means there is no rate to convert with, never a rate of one.
     *
     * @param Mage_Directory_Model_Currency|string $currencyFrom
     * @param Mage_Directory_Model_Currency|string $currencyTo
     */
    public function getAnyRate($currencyFrom, $currencyTo): ?float
    {
        $rate = $this->getRate($currencyFrom, $currencyTo);
        if ($rate !== null) {
            return $rate;
        }

        // Inverted here, not as SQL "1/rate": a stored zero yields NULL on
        // MySQL and an error on PostgreSQL.
        $reverseRate = $this->getRate($currencyTo, $currencyFrom);

        return $reverseRate === null ? null : 1 / $reverseRate;
    }

    /**
     * Uppercased, so one pair cannot occupy two cache entries and go stale independently.
     */
    protected function _currencyCode(mixed $currency): string
    {
        if ($currency instanceof Mage_Directory_Model_Currency) {
            $currency = $currency->getCode();
        }

        return strtoupper(trim((string) $currency));
    }

    /**
     * A missing row, a zero and a negative are the same answer: there is no rate.
     */
    protected function _rateValue(mixed $rate): ?float
    {
        if (!is_numeric($rate)) {
            return null;
        }

        $rate = (float) $rate;

        return $rate > 0 ? $rate : null;
    }

    /**
     * Whether the rate column can hold this value: anything below its scale lands as a zero,
     * which is not a rate. The one definition, so the admin warning and the write agree.
     */
    public static function isStorableRate(mixed $rate): bool
    {
        return is_numeric($rate) && round(abs((float) $rate), self::RATE_SCALE) > 0;
    }

    /**
     * Drop the memoised rates, for a process that writes the table without saveRates().
     */
    public static function clearRateCache(): void
    {
        self::$_rateCache = null;
    }

    /**
     * Saving currency rates
     *
     * @param array $rates
     */
    public function saveRates($rates)
    {
        if (is_array($rates) && count($rates)) {
            $adapter = $this->_getWriteAdapter();
            $data    = [];
            foreach ($rates as $currencyCode => $rate) {
                foreach ($rate as $currencyTo => $value) {
                    // A custom importer can report a missing currency as null, or a rate the
                    // column cannot hold; neither is a rate to write.
                    if (!self::isStorableRate($value)) {
                        continue;
                    }
                    $data[] = [
                        'currency_from' => $this->_currencyCode($currencyCode),
                        'currency_to'   => $this->_currencyCode($currencyTo),
                        'rate'          => abs((float) $value),
                    ];
                }
            }
            if ($data) {
                $adapter->insertOnDuplicate($this->_currencyRateTable, $data, ['rate']);
                self::clearRateCache();
            }
        } else {
            Mage::throwException(Mage::helper('directory')->__('Invalid rates received'));
        }
    }

    /**
     * Retrieve config currency data by config path
     *
     * @param Mage_Directory_Model_Currency $model
     * @param string $path
     *
     * @return array
     */
    public function getConfigCurrencies($model, $path)
    {
        $result  = [];
        $config = Mage::app()->getConfig();

        // default
        $result = array_merge($result, explode(',', trim($config->getNode($path, 'default'))));

        // stores
        foreach (Mage::app()->getStores(true) as $store) {
            $result = array_merge($result, explode(',', trim($config->getNode($path, 'stores', $store->getCode()))));
        }

        // websites
        foreach (Mage::app()->getWebsites(true) as $website) {
            $result = array_merge($result, explode(',', trim($config->getNode($path, 'websites', $website->getCode()))));
        }

        sort($result);

        return array_unique($result);
    }

    /**
     * Return currency rates
     *
     * @param string|array $currency
     * @param array $toCurrencies
     *
     * @return array
     */
    public function getCurrencyRates($currency, $toCurrencies = null)
    {
        $rates = [];
        if (is_array($currency)) {
            foreach ($currency as $code) {
                $rates[$code] = $this->_getRatesByCode($code, $toCurrencies);
            }
        } else {
            $rates = $this->_getRatesByCode($currency, $toCurrencies);
        }

        return $rates;
    }

    /**
     * Protected method used by getCurrencyRates() method
     *
     * @param string $code
     * @param array $toCurrencies
     * @return array<string, float>
     */
    protected function _getRatesByCode($code, $toCurrencies = null): array
    {
        $adapter = $this->_getReadAdapter();
        $bind    = [
            ':currency_from' => $this->_currencyCode($code),
        ];
        $select  = $adapter->select()
            ->from($this->getTable('directory/currency_rate'), ['currency_to', 'rate'])
            ->where('currency_from = :currency_from')
            ->where('currency_to IN(?)', is_array($toCurrencies)
                ? array_map($this->_currencyCode(...), $toCurrencies)
                : $toCurrencies);
        $rowSet  = $adapter->fetchAll($select, $bind);
        $result  = [];

        foreach ($rowSet as $row) {
            $result[$row['currency_to']] = (float) $row['rate'];
        }

        return $result;
    }
}
