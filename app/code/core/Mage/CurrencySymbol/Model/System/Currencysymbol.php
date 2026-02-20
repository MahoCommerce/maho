<?php

/**
 * Maho
 *
 * @package    Mage_CurrencySymbol
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method $this resetValues()
 */
class Mage_CurrencySymbol_Model_System_Currencysymbol
{
    /**
     * Custom currency symbol properties
     *
     * @var array
     */
    protected $_symbolsData = [];

    /**
     * Store id
     *
     * @var int|null
     */
    protected $_storeId;

    /**
     * Website id
     *
     * @var int|null
     */
    protected $_websiteId;
    /**
     * Cache types which should be invalidated
     *
     * @var array
     */
    protected $_cacheTypes = [
        'config',
        'block_html',
        'layout',
    ];

    /**
     * Config path to custom currency symbol value
     */
    public const XML_PATH_CUSTOM_CURRENCY_SYMBOL = 'currency/options/customsymbol';
    public const XML_PATH_ALLOWED_CURRENCIES     = 'currency/options/allow';

    /**
     * Separator used in config in allowed currencies list
     */
    public const ALLOWED_CURRENCIES_CONFIG_SEPARATOR = ',';

    /**
     * Config currency section
     */
    public const CONFIG_SECTION = 'currency';

    /**
     * Sets store Id
     *
     * @param  int $storeId
     * @return $this
     */
    public function setStoreId($storeId = null)
    {
        $this->_storeId = $storeId;
        $this->_symbolsData = [];

        return $this;
    }

    /**
     * Sets website Id
     *
     * @param  int $websiteId
     * @return $this
     */
    public function setWebsiteId($websiteId = null)
    {
        $this->_websiteId = $websiteId;
        $this->_symbolsData = [];

        return $this;
    }

    /**
     * Returns currency symbol properties array based on config values
     * Now returns currency/locale pairs instead of just currencies
     *
     * @return array
     */
    public function getCurrencySymbolsData()
    {
        if ($this->_symbolsData) {
            return $this->_symbolsData;
        }

        $this->_symbolsData = [];

        $allowedCurrencies = explode(
            self::ALLOWED_CURRENCIES_CONFIG_SEPARATOR,
            Mage::getStoreConfig(self::XML_PATH_ALLOWED_CURRENCIES),
        );

        $storeModel = Mage::getSingleton('adminhtml/system_store');
        foreach ($storeModel->getWebsiteCollection() as $website) {
            $websiteShow = false;
            foreach ($storeModel->getGroupCollection() as $group) {
                if ($group->getWebsiteId() != $website->getId()) {
                    continue;
                }
                foreach ($storeModel->getStoreCollection() as $store) {
                    if ($store->getGroupId() != $group->getId()) {
                        continue;
                    }
                    if (!$websiteShow) {
                        $websiteShow = true;
                        $websiteSymbols  = $website->getConfig(self::XML_PATH_ALLOWED_CURRENCIES);
                        $allowedCurrencies = array_merge($allowedCurrencies, explode(
                            self::ALLOWED_CURRENCIES_CONFIG_SEPARATOR,
                            $websiteSymbols,
                        ));
                    }
                    $storeSymbols = Mage::getStoreConfig(self::XML_PATH_ALLOWED_CURRENCIES, $store);
                    $allowedCurrencies = array_merge($allowedCurrencies, explode(
                        self::ALLOWED_CURRENCIES_CONFIG_SEPARATOR,
                        $storeSymbols,
                    ));
                }
            }
        }
        $allowedCurrencies = array_unique($allowedCurrencies);
        sort($allowedCurrencies);

        // Get all used locales from stores
        $usedLocales = [];
        foreach ($storeModel->getStoreCollection() as $store) {
            $localeCode = Mage::getStoreConfig('general/locale/code', $store);
            if ($localeCode && !in_array($localeCode, $usedLocales)) {
                $usedLocales[] = $localeCode;
            }
        }
        sort($usedLocales);

        $currentSymbols = $this->_unserializeStoreConfig(self::XML_PATH_CUSTOM_CURRENCY_SYMBOL);

        // Generate currency/locale pairs
        foreach ($allowedCurrencies as $currencyCode) {
            if (empty($currencyCode)) {
                continue;
            }

            foreach ($usedLocales as $localeCode) {
                $locale = Mage::getModel('core/locale', $localeCode);

                // Get the actual currency symbol as it appears in this locale using NumberFormatter
                $formatter = new NumberFormatter($localeCode, NumberFormatter::CURRENCY);
                $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currencyCode);
                $symbol = $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL);

                if (!$symbol) {
                    $symbol = $currencyCode;
                }

                $name = $locale->getTranslation($currencyCode, 'nametocurrency');
                if (!$name) {
                    $name = $currencyCode;
                }

                $key = $currencyCode . '_' . $localeCode;

                $this->_symbolsData[$key] = [
                    'currencyCode' => $currencyCode,
                    'localeCode' => $localeCode,
                    'parentSymbol' => $symbol,
                    'displayName' => $name,
                    'localeName' => $locale->getTranslation($localeCode, 'language'),
                ];

                // Check if there's a custom symbol for this currency/locale pair
                if (isset($currentSymbols[$key]) && !empty($currentSymbols[$key])) {
                    $this->_symbolsData[$key]['displaySymbol'] = $currentSymbols[$key];
                    $this->_symbolsData[$key]['inherited'] = false;
                } else {
                    $this->_symbolsData[$key]['displaySymbol'] = $symbol;
                    $this->_symbolsData[$key]['inherited'] = true;
                }
            }
        }

        return $this->_symbolsData;
    }

    /**
     * Saves currency symbol to config
     *
     * @param array $symbols
     * @return $this
     */
    public function setCurrencySymbolsData($symbols = [])
    {
        foreach ($this->getCurrencySymbolsData() as $key => $values) {
            if (isset($symbols[$key])) {
                // Only remove if empty, allow overriding even when matching parentSymbol
                if (empty($symbols[$key])) {
                    unset($symbols[$key]);
                }
            }
        }
        if ($symbols) {
            $value['options']['fields']['customsymbol']['value'] = Mage::helper('core')->jsonEncode($symbols);
        } else {
            $value['options']['fields']['customsymbol']['inherit'] = 1;
        }

        Mage::getModel('adminhtml/config_data')
            ->setSection(self::CONFIG_SECTION)
            ->setWebsite(null)
            ->setStore(null)
            ->setGroups($value)
            ->save();

        Mage::dispatchEvent(
            'admin_system_config_changed_section_currency_before_reinit',
            ['website' => $this->_websiteId, 'store' => $this->_storeId],
        );

        // reinit configuration
        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();

        $this->clearCache();

        Mage::dispatchEvent(
            'admin_system_config_changed_section_currency',
            ['website' => $this->_websiteId, 'store' => $this->_storeId],
        );

        return $this;
    }

    /**
     * Returns custom currency symbol by currency code and locale
     *
     * @param  string $currencyCode
     * @param  string $localeCode
     * @return false|string
     */
    public function getCurrencySymbol($currencyCode, $localeCode = null)
    {
        $customSymbols = $this->_unserializeStoreConfig(self::XML_PATH_CUSTOM_CURRENCY_SYMBOL);

        // If no locale provided, get current locale
        if ($localeCode === null) {
            $localeCode = Mage::app()->getLocale()->getLocaleCode();
        }

        $key = $currencyCode . '_' . $localeCode;
        if (array_key_exists($key, $customSymbols)) {
            return $customSymbols[$key];
        }

        // Fallback: check for old format (just currency code) for backwards compatibility
        if (array_key_exists($currencyCode, $customSymbols)) {
            return $customSymbols[$currencyCode];
        }

        return false;
    }

    /**
     * Clear translate cache
     *
     * @return $this
     */
    public function clearCache()
    {
        // clear cache for frontend
        foreach ($this->_cacheTypes as $cacheType) {
            Mage::app()->getCache()->invalidateType($cacheType);
        }
        return $this;
    }

    /**
     * Unserialize data from Store Config.
     *
     * @param string $configPath
     * @param int $storeId
     * @return array
     */
    protected function _unserializeStoreConfig($configPath, $storeId = null)
    {
        $result = [];
        $configData = (string) Mage::getStoreConfig($configPath, $storeId);
        if ($configData) {
            try {
                $result = Mage::helper('core/unserializeArray')->unserialize($configData);
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        return is_array($result) ? $result : [];
    }
}
