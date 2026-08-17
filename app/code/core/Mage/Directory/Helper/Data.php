<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

class Mage_Directory_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Config value that lists ISO2 country codes which have optional Zip/Postal pre-configured
     */
    public const OPTIONAL_ZIP_COUNTRIES_CONFIG_PATH = 'general/country/optional_zip_countries';

    /**
     * Path to config value, which lists countries, for which state is required.
     */
    public const XML_PATH_STATES_REQUIRED = 'general/region/state_required';

    /**
     * Path to config value, which detects whether or not display the state for the country, if it is not required
     */
    public const XML_PATH_DISPLAY_ALL_STATES = 'general/region/display_all';

    protected $_moduleName = 'Mage_Directory';

    /**
     * Country collection
     *
     * @var Mage_Directory_Model_Resource_Country_Collection
     */
    protected $_countryCollection;

    /**
     * Region collection
     *
     * @var Mage_Directory_Model_Resource_Region_Collection
     */
    protected $_regionCollection;

    /**
     * Json representation of regions data
     *
     * @var string
     */
    protected $_regionJson;

    /**
     * What warnMissingRate() has already reported this process, keyed "FROM/TO/subject"
     *
     * @var array<string, true>
     */
    protected array $_warnedPairs = [];

    /**
     * ISO2 country codes which have optional Zip/Postal pre-configured
     *
     * @var array
     */
    protected $_optionalZipCountries = null;

    /**
     * Factory instance
     *
     * @var Mage_Core_Model_Factory
     */
    protected $_factory;

    /**
     * Application instance
     *
     * @var Mage_Core_Model_App
     */
    protected $_app;

    public function __construct(array $args = [])
    {
        $this->_factory = empty($args['factory']) ? Mage::getSingleton('core/factory') : $args['factory'];
        $this->_app = empty($args['app']) ? Mage::app() : $args['app'];
    }

    /**
     * Retrieve region collection
     * @param string|array|null $countryFilter If string, accepts iso2_code; if array, accepts iso2_code[].
     * @return Mage_Directory_Model_Resource_Region_Collection
     * @throws Mage_Core_Exception
     */
    public function getRegionCollection($countryFilter = null)
    {
        if (!$this->_regionCollection) {
            $this->_regionCollection = Mage::getModel('directory/region')->getResourceCollection()
                ->addCountryFilter($countryFilter)
                ->load();
        }
        return $this->_regionCollection;
    }

    /**
     * Retrieve country collection
     *
     * @return Mage_Directory_Model_Resource_Country_Collection
     * @throws Mage_Core_Exception
     */
    public function getCountryCollection()
    {
        if (!$this->_countryCollection) {
            $this->_countryCollection = $this->_factory->getModel('directory/country')->getResourceCollection();
        }
        return $this->_countryCollection;
    }

    /**
     * Retrieve regions data json
     *
     * @param int|null $storeId
     * @return string
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getRegionJsonByStore($storeId = null)
    {
        if (!$this->_regionJson) {
            $store = $this->_app->getStore($storeId);
            $cacheKey = 'DIRECTORY_REGIONS_JSON_STORE' . (string) $store->getId();
            if ($this->_app->useCache('config')) {
                $json = $this->_app->loadCache($cacheKey);
            }
            if (empty($json)) {
                $regions = $this->_getRegions($storeId);
                /** @var Mage_Core_Helper_Data $helper */
                $helper = $this->_factory->getHelper('core');
                $json = $helper->jsonEncode($regions);

                if ($this->_app->useCache('config')) {
                    $this->_app->saveCache($json, $cacheKey, ['config']);
                }
            }
            $this->_regionJson = $json;
        }

        return $this->_regionJson;
    }

    /**
     * Get Regions for specific Countries
     * @param string|int|null $storeId
     * @return array|null
     * @throws Mage_Core_Exception
     */
    protected function _getRegions($storeId)
    {
        $countryIds = [];

        $countryCollection = $this->getCountryCollection()->loadByStore($storeId);
        /** @var Mage_Directory_Model_Country $country */
        foreach ($countryCollection as $country) {
            $countryIds[] = $country->getCountryId();
        }

        /** @var Mage_Directory_Model_Region $regionModel */
        $regionModel = $this->_factory->getModel('directory/region');
        $collection = $regionModel->getResourceCollection()
            ->addCountryFilter($countryIds)
            ->load();

        $regions = [
            'config' => [
                'show_all_regions' => $this->getShowNonRequiredState(),
                'regions_required' => $this->getCountriesWithStatesRequired(),
            ],
        ];
        foreach ($collection as $region) {
            if (!$region->getRegionId()) {
                continue;
            }
            $regions[$region->getCountryId()][$region->getRegionId()] = [
                'code' => $region->getCode(),
                'name' => $this->__($region->getName()),
            ];
        }
        return $regions;
    }

    /**
     * A currency code in the one spelling everything here compares on. Codes reach the system from
     * configuration, from a request and from the rate table, and a code spelled two ways is two
     * answers to one question.
     */
    public function normalizeCurrencyCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    /**
     * The rate between two named currencies, or null when there is none.
     */
    public function getRate(string $from, string $to): ?float
    {
        return $this->_rateResource()->getRate($from, $to);
    }

    /**
     * The rate between two named currencies, taking the pair in either direction, or null when
     * neither exists. For a caller holding an amount in a currency it did not choose, such as a
     * carrier quoting in its own.
     */
    public function getAnyRate(string $from, string $to): ?float
    {
        return $this->_rateResource()->getAnyRate($from, $to);
    }

    /**
     * Answered from the resource rather than the currency model: the model's rate methods are
     * deprecated in favour of this helper, and PHP 8.4 raises E_USER_DEPRECATED on every call
     * to one, which is no way to resolve a price.
     */
    protected function _rateResource(): Mage_Directory_Model_Resource_Currency
    {
        /** @var Mage_Directory_Model_Resource_Currency $resource */
        $resource = Mage::getResourceSingleton('directory/currency');

        return $resource;
    }

    /**
     * An amount in another currency, or null when there is no rate to convert it with. Both
     * currencies are the caller's to name: there is no default here to be wrong about.
     */
    public function convert(float $amount, string $from, string $to): ?float
    {
        $rate = $this->getRate($from, $to);

        return $rate === null ? null : $amount * $rate;
    }

    /**
     * The rate a caller needs to price something, or null with the miss on the record. A price
     * converted at a rate of one ships orders, so the caller declines to write one and this says
     * so. What the caller then does differs, so the operator is told the fact and the log is
     * told the scope.
     *
     * Reported once per pair and subject for the life of the process, so $subject has to name a
     * scope, never a row: a subject carrying a row id reports once per row.
     *
     * @param string $subject the scope that could not be priced, for the log
     */
    public function getRateOrWarn(string $from, string $to, string $subject): ?float
    {
        $rate = $this->getRate($from, $to);
        if ($rate === null) {
            $this->warnMissingRate($from, $to, $subject);
        }

        return $rate;
    }

    /**
     * Put a missing rate on the record without asking for one, for a caller that has already
     * looked, or looked in both directions, and has its own answer for what to do next. Reported
     * once per pair and subject, like the rate above.
     */
    public function warnMissingRate(string $from, string $to, string $subject): void
    {
        // Keyed on the canonical spelling: two spellings of one pair are one missing rate, and
        // reporting it twice is the same defect this branch removed from the lookups themselves.
        $key = $this->normalizeCurrencyCode($from) . '/' . $this->normalizeCurrencyCode($to) . '/' . $subject;
        if (isset($this->_warnedPairs[$key])) {
            return;
        }
        $this->_warnedPairs[$key] = true;

        // Forced: a price that silently went missing is exactly what an install with logging
        // switched off would never hear about.
        Mage::log(
            sprintf('No exchange rate from %s to %s for %s.', $from, $to, $subject),
            Mage::LOG_WARNING,
            '',
            true,
        );

        // Only an admin request that already has a session, so this cannot be what starts one:
        // under the CLI the current store is the admin store too, and an admin-scoped API
        // request has no session to put a message in.
        $adminSession = Mage::registry('_singleton/adminhtml/session');
        if ($adminSession instanceof Mage_Adminhtml_Model_Session) {
            $adminSession->addUniqueMessages([
                Mage::getSingleton('core/message')->warning(
                    $this->__('There is no exchange rate from %s to %s.', $from, $to),
                ),
            ]);
        }
    }

    /**
     * Convert currency
     *
     * Prefer convert(), which names both currencies and answers null instead of throwing.
     *
     * @param float $amount
     * @param string $from
     * @param string $to
     * @return float
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     */
    #[\Deprecated(message: 'use convert(), which names both currencies and answers null instead of throwing')]
    public function currencyConvert($amount, $from, $to = null)
    {
        if ($to === null) {
            $to = Mage::app()->getStore()->getCurrentCurrencyCode();
        }

        return Mage::getModel('directory/currency')->load($from)->convert($amount, $to);
    }

    /**
     * Return ISO2 country codes, which have optional Zip/Postal pre-configured
     *
     * @param bool $asJson
     * @return array|string
     */
    public function getCountriesWithOptionalZip($asJson = false)
    {
        if ($this->_optionalZipCountries === null) {
            $this->_optionalZipCountries = preg_split(
                '/\,/',
                Mage::getStoreConfig(self::OPTIONAL_ZIP_COUNTRIES_CONFIG_PATH),
                0,
                PREG_SPLIT_NO_EMPTY,
            );
        }
        if ($asJson) {
            return Mage::helper('core')->jsonEncode($this->_optionalZipCountries);
        }
        return $this->_optionalZipCountries;
    }

    /**
     * Check whether zip code is optional for specified country code
     *
     * @param string $countryCode
     * @return bool
     */
    public function isZipCodeOptional($countryCode)
    {
        $this->getCountriesWithOptionalZip();
        return in_array($countryCode, $this->_optionalZipCountries);
    }

    /**
     * Returns the list of countries, for which region is required
     *
     * @param bool $asJson
     * @return array|string
     */
    public function getCountriesWithStatesRequired($asJson = false)
    {
        $countryList = explode(',', Mage::getStoreConfig(self::XML_PATH_STATES_REQUIRED));
        if ($asJson) {
            return Mage::helper('core')->jsonEncode($countryList);
        }
        return $countryList;
    }

    /**
     * Return flag, which indicates whether or not non required state should be shown
     *
     * @return bool
     */
    public function getShowNonRequiredState()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_DISPLAY_ALL_STATES);
    }

    /**
     * Returns flag, which indicates whether region is required for specified country
     *
     * @param string $countryId
     * @return bool
     */
    public function isRegionRequired($countryId)
    {
        $countyList = $this->getCountriesWithStatesRequired();
        if (!is_array($countyList)) {
            return false;
        }
        return in_array($countryId, $countyList);
    }

    public static function getConfigCurrencyBase(): string
    {
        return (string) Mage::getStoreConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE);
    }

    /**
     * Rate import services, in the order the store manager put them in.
     *
     * A service with no `active` flag of its own counts as available: third-party services are
     * not obliged to add one.
     *
     * @param bool $enabledOnly Skip services the store has switched off
     * @return array<string, array{name: string, model: string, sort_order: int}>
     */
    public function getCurrencyImportServices(bool $enabledOnly = true): array
    {
        $services = Mage::getConfig()->getNode('global/currency/import/services');
        if (!$services) {
            return [];
        }

        $result = [];
        foreach ($services->asArray() as $code => $options) {
            if (empty($options['model'])) {
                continue;
            }
            if ($enabledOnly
                && Mage::getStoreConfig("currency/{$code}/active") !== null
                && !Mage::getStoreConfigFlag("currency/{$code}/active")
            ) {
                continue;
            }
            $result[$code] = [
                'name' => (string) ($options['name'] ?? $code),
                'model' => (string) $options['model'],
                'sort_order' => Mage::getStoreConfigAsInt("currency/{$code}/sort_order"),
            ];
        }

        uasort($result, fn(array $a, array $b): int => [$a['sort_order'], $a['name']] <=> [$b['sort_order'], $b['name']]);

        return $result;
    }

    /** @return list<string> */
    public function getTopCountryCodes(): array
    {
        $topCountries = array_filter(explode(',', (string) Mage::getStoreConfig('general/country/top_countries')));

        $transportObject = new \Maho\DataObject();
        $transportObject->setData('top_countries', $topCountries);
        Mage::dispatchEvent('directory_get_top_countries', ['topCountries' => $transportObject]);

        return $transportObject->getData('top_countries');
    }
}
