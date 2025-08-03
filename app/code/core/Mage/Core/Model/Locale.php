<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Locale
{
    /**
     * Default locale name
     */
    public const DEFAULT_LOCALE    = 'en_US';
    public const DEFAULT_TIMEZONE  = 'UTC';
    public const DEFAULT_CURRENCY  = 'USD';

    /**
     * Date format constants
     */
    public const DATETIME_FORMAT = 'Y-m-d H:i:s';
    public const DATE_FORMAT = 'Y-m-d';
    public const HTML5_DATETIME_FORMAT = 'Y-m-d\TH:i';

    /**
     * Weight unit constants
     */
    public const WEIGHT_KILOGRAM = 'kg';
    public const WEIGHT_POUND = 'lb';
    public const WEIGHT_OUNCE = 'oz';
    public const WEIGHT_GRAM = 'g';
    public const WEIGHT_TON = 't';

    /**
     * Length unit constants
     */
    public const LENGTH_METER = 'm';
    public const LENGTH_CENTIMETER = 'cm';
    public const LENGTH_MILLIMETER = 'mm';
    public const LENGTH_INCH = 'in';
    public const LENGTH_FOOT = 'ft';
    public const LENGTH_YARD = 'yd';
    public const LENGTH_MILE = 'mi';
    public const LENGTH_KILOMETER = 'km';

    /**
     * XML path constants
     */
    public const XML_PATH_DEFAULT_LOCALE   = 'general/locale/code';
    public const XML_PATH_DEFAULT_TIMEZONE = 'general/locale/timezone';
    /**
     * @deprecated since 1.4.1.0
     */
    public const XML_PATH_DEFAULT_COUNTRY  = 'general/country/default';
    public const XML_PATH_ALLOW_CODES      = 'global/locale/allow/codes';
    public const XML_PATH_ALLOW_CURRENCIES = 'global/locale/allow/currencies';
    public const XML_PATH_ALLOW_CURRENCIES_INSTALLED = 'system/currency/installed';

    /**
     * Date and time format codes
     */
    public const FORMAT_TYPE_FULL  = 'full';
    public const FORMAT_TYPE_LONG  = 'long';
    public const FORMAT_TYPE_MEDIUM = 'medium';
    public const FORMAT_TYPE_SHORT = 'short';

    /**
     * Default locale code
     *
     * @var string
     */
    protected $_defaultLocale;

    /**
     * Locale code
     *
     * @var string
     */
    protected $_localeCode;

    /**
     * Emulated locales stack
     *
     * @var array
     */
    protected $_emulatedLocales = [];

    protected static $_currencyCache = [];
    /** @var NumberFormatter[] */
    protected static $_numberFormatterCache = [];

    /**
     * Mage_Core_Model_Locale constructor.
     * @param string|null $locale
     */
    public function __construct($locale = null)
    {
        $this->setLocale($locale);
    }

    /**
     * Set default locale code
     *
     * @param   string $locale
     * @return  Mage_Core_Model_Locale
     */
    public function setDefaultLocale($locale)
    {
        $this->_defaultLocale = $locale;
        return $this;
    }

    /**
     * REtrieve default locale code
     *
     * @return string
     */
    public function getDefaultLocale()
    {
        if (!$this->_defaultLocale) {
            $locale = Mage::getStoreConfig(self::XML_PATH_DEFAULT_LOCALE);
            if (!$locale) {
                $locale = self::DEFAULT_LOCALE;
            }
            $this->_defaultLocale = $locale;
        }
        return $this->_defaultLocale;
    }

    /**
     * Set locale
     *
     * @param   string $locale
     * @return  Mage_Core_Model_Locale
     */
    public function setLocale($locale = null)
    {
        if (($locale !== null) && is_string($locale)) {
            $this->_localeCode = $locale;
        } else {
            $this->_localeCode = $this->getDefaultLocale();
        }
        Mage::dispatchEvent('core_locale_set_locale', ['locale' => $this]);
        return $this;
    }

    /**
     * Retrieve timezone code
     *
     * @return string
     */
    public function getTimezone()
    {
        return self::DEFAULT_TIMEZONE;
    }

    /**
     * Retrieve currency code
     *
     * @return string
     */
    public function getCurrency()
    {
        return self::DEFAULT_CURRENCY;
    }

    /**
     * Retrieve locale object (compatibility method - returns locale code instead)
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->getLocaleCode();
    }

    /**
     * Retrieve locale code
     *
     * @return string
     */
    public function getLocaleCode()
    {
        if ($this->_localeCode === null) {
            $this->setLocale();
        }
        return $this->_localeCode;
    }

    /**
     * Specify current locale code
     *
     * @param   string $code
     * @return  Mage_Core_Model_Locale
     */
    public function setLocaleCode($code)
    {
        $this->_localeCode = $code;
        return $this;
    }

    /**
     * Get options array for locale dropdown in currunt locale
     *
     * @return array
     */
    public function getOptionLocales()
    {
        return $this->_getOptionLocales();
    }

    /**
     * Get translated to original locale options array for locale dropdown
     *
     * @return array
     */
    public function getTranslatedOptionLocales()
    {
        return $this->_getOptionLocales(true);
    }

    /**
     * Get options array for locale dropdown
     *
     * @param   bool $translatedName translation flag
     * @return  array
     */
    protected function _getOptionLocales($translatedName = false)
    {
        $options = [];
        $locales = ResourceBundle::getLocales('');
        $currentLocale = $this->getLocaleCode();

        // Get allowed locales
        $allowed = $this->getAllowLocales();

        // Map of locale aliases (if needed)
        $allowedAliases = [];
        foreach ($allowed as $code) {
            $canonicalized = Locale::canonicalize($code);
            if ($canonicalized !== $code) {
                $allowedAliases[$canonicalized] = $code;
            }
        }

        foreach ($locales as $code) {
            // Check if locale is allowed
            if (!in_array($code, $allowed)) {
                // Check if it's an alias
                if (isset($allowedAliases[$code])) {
                    $code = $allowedAliases[$code];
                } else {
                    continue;
                }
            }

            // Only process locales with country code
            if (!strstr($code, '_')) {
                continue;
            }

            $parsed = Locale::parseLocale($code);
            if (!isset($parsed['language']) || !isset($parsed['region'])) {
                continue;
            }

            $language = $parsed['language'];
            $country = $parsed['region'];

            // Get language and country names in English
            $languageNameEn = Locale::getDisplayLanguage($code, 'en');
            $countryNameEn = Locale::getDisplayRegion($code, 'en');

            if ($translatedName) {
                // Get translated names
                $languageNameTranslated = Locale::getDisplayLanguage($code, $code);
                $countryNameTranslated = Locale::getDisplayRegion($code, $code);
                $label = "$languageNameTranslated ($countryNameTranslated) / $languageNameEn ($countryNameEn)";
            } else {
                $label = "$languageNameEn ($countryNameEn)";
            }

            $options[] = [
                'value' => $code,
                'label' => $label,
            ];
        }

        return $this->_sortOptionArray($options);
    }

    /**
     * Retrieve timezone option list
     *
     * @return array
     */
    public function getOptionTimezones()
    {
        $options = [];
        $timezones = DateTimeZone::listIdentifiers();

        foreach ($timezones as $timezone) {
            $options[] = [
                'label' => $timezone,
                'value' => $timezone,
            ];
        }

        return $this->_sortOptionArray($options);
    }

    /**
     * Retrieve days of week option list
     *
     * @param bool $preserveCodes
     * @param bool $ucFirstCode
     *
     * @return array
     */
    public function getOptionWeekdays($preserveCodes = false, $ucFirstCode = false)
    {
        $options = [];
        $locale = $this->getLocaleCode();

        // Create a DateTime object for a known Sunday
        $date = new DateTime('2024-01-07'); // Sunday
        $formatter = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            null,
            IntlDateFormatter::GREGORIAN,
            'EEEE', // Full weekday name
        );

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $dayName = $formatter->format($date);
            $dayCode = strtolower($date->format('l')); // English day name lowercase

            if ($preserveCodes) {
                $days[$dayCode] = $dayName;
            } else {
                $days[] = $dayName;
            }

            $date->modify('+1 day');
        }

        foreach ($days as $code => $name) {
            $options[] = [
                'label' => $name,
                'value' => $preserveCodes ? ($ucFirstCode ? ucfirst($code) : $code) : $code,
            ];
        }

        return $options;
    }

    /**
     * Retrieve country option list
     *
     * @return array
     */
    public function getOptionCountries()
    {
        $options    = [];
        $countries  = $this->getCountryTranslationList();

        foreach ($countries as $code => $name) {
            $options[] = [
                'label' => $name,
                'value' => $code,
            ];
        }
        return $this->_sortOptionArray($options);
    }

    /**
     * Retrieve currency option list
     *
     * @return array
     */
    public function getOptionCurrencies()
    {
        $currencies = $this->_getCurrencyList();
        $options = [];
        $allowed = $this->getAllowCurrencies();

        foreach ($currencies as $code => $name) {
            if (!in_array($code, $allowed)) {
                continue;
            }

            $options[] = [
                'label' => $name,
                'value' => $code,
            ];
        }
        return $this->_sortOptionArray($options);
    }

    /**
     * Retrieve all currency option list
     *
     * @return array
     */
    public function getOptionAllCurrencies()
    {
        $currencies = $this->_getCurrencyList();
        $options = [];
        foreach ($currencies as $code => $name) {
            $options[] = [
                'label' => $name,
                'value' => $code,
            ];
        }
        return $this->_sortOptionArray($options);
    }

    /**
     * Get currency list
     *
     * @return array
     */
    protected function _getCurrencyList()
    {
        $locale = $this->getLocaleCode();
        $currencies = [];

        // Get all available currency codes
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
        $currencyCodes = ResourceBundle::create($locale, 'ICUDATA-curr')->get('Currencies');

        if ($currencyCodes !== null) {
            foreach ($currencyCodes as $code => $data) {
                if (strlen($code) === 3) { // Valid currency codes are 3 characters
                    $name = $formatter->getTextAttribute(NumberFormatter::CURRENCY_CODE) === $code
                        ? $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL)
                        : $code;

                    // Try to get the currency name
                    if (is_array($data) && isset($data[1])) {
                        $name = $data[1] . ' (' . $code . ')';
                    } else {
                        $name = $code;
                    }

                    $currencies[$code] = $name;
                }
            }
        }

        // Add common currencies as fallback
        $commonCurrencies = [
            'USD' => 'US Dollar (USD)',
            'EUR' => 'Euro (EUR)',
            'GBP' => 'British Pound Sterling (GBP)',
            'CAD' => 'Canadian Dollar (CAD)',
            'AUD' => 'Australian Dollar (AUD)',
            'JPY' => 'Japanese Yen (JPY)',
            'CNY' => 'Chinese Yuan (CNY)',
        ];

        foreach ($commonCurrencies as $code => $name) {
            if (!isset($currencies[$code])) {
                $currencies[$code] = $name;
            }
        }

        return $currencies;
    }

    /**
     * @param array $option
     * @return array
     */
    protected function _sortOptionArray($option)
    {
        $data = [];
        foreach ($option as $item) {
            $data[$item['value']] = $item['label'];
        }
        asort($data);
        $option = [];
        foreach ($data as $key => $label) {
            $option[] = [
                'value' => $key,
                'label' => $label,
            ];
        }
        return $option;
    }

    /**
     * Retrieve array of allowed locales
     *
     * @return array
     */
    public function getAllowLocales()
    {
        return Mage::getSingleton('core/locale_config')->getAllowedLocales();
    }

    /**
     * Retrieve array of allowed currencies
     *
     * @return array
     */
    public function getAllowCurrencies()
    {
        $data = [];
        if (Mage::isInstalled()) {
            $data = Mage::app()->getStore()->getConfig(self::XML_PATH_ALLOW_CURRENCIES_INSTALLED);
            return explode(',', $data);
        } else {
            $data = Mage::getSingleton('core/locale_config')->getAllowedCurrencies();
        }
        return $data;
    }

    /**
     * Retrieve date format pattern for display purposes (ICU pattern)
     * Use this for frontend forms, user interfaces, display formatting
     */
    public function getDateFormat(?string $type = null): string
    {
        $dateStyle = match ($type) {
            self::FORMAT_TYPE_SHORT => IntlDateFormatter::SHORT,
            self::FORMAT_TYPE_MEDIUM => IntlDateFormatter::MEDIUM,
            self::FORMAT_TYPE_LONG => IntlDateFormatter::LONG,
            self::FORMAT_TYPE_FULL => IntlDateFormatter::FULL,
            default => IntlDateFormatter::SHORT,
        };

        $formatter = new IntlDateFormatter(
            $this->getLocaleCode(),
            $dateStyle,
            IntlDateFormatter::NONE,
        );

        return $formatter->getPattern();
    }


    /**
     * Retrieve short date format with 4-digit year
     */
    public function getDateFormatWithLongYear(): string
    {
        // Always returns 4-digit year format (same as short format)
        return $this->getDateFormat(self::FORMAT_TYPE_SHORT);
    }


    /**
     * Retrieve date format by period type
     * @param string|null $period Valid values: ["day", "month", "year"]
     */
    public function getDateFormatByPeriodType(?string $period = null): string
    {
        return match ($period) {
            'month' => 'Y-m',     // Simple year-month format
            'year' => 'Y',        // Just year
            default => $this->getDateFormat(self::FORMAT_TYPE_MEDIUM),
        };
    }


    /**
     * Retrieve time format pattern for display purposes (ICU pattern)
     * Use this for frontend forms, user interfaces, display formatting
     *
     * @param   string $type
     * @return  string
     */
    public function getTimeFormat($type = null)
    {
        $timeStyle = match ($type) {
            self::FORMAT_TYPE_SHORT => IntlDateFormatter::SHORT,
            self::FORMAT_TYPE_MEDIUM => IntlDateFormatter::MEDIUM,
            self::FORMAT_TYPE_LONG => IntlDateFormatter::LONG,
            self::FORMAT_TYPE_FULL => IntlDateFormatter::FULL,
            default => IntlDateFormatter::SHORT,
        };

        $formatter = new IntlDateFormatter(
            $this->getLocaleCode(),
            IntlDateFormatter::NONE,
            $timeStyle,
        );

        return $formatter->getPattern();
    }


    /**
     * Retrieve ISO datetime format
     *
     * @param   string $type
     * @return  string
     */
    public function getDateTimeFormat($type)
    {
        return $this->getDateFormat($type) . ' ' . $this->getTimeFormat($type);
    }


    /**
     * Use dateMutable() or dateImmutable() instead of this one.
     *
     * @param mixed              $date
     * @param string             $part
     * @param string|null        $locale
     * @param bool               $useTimezone
     * @return DateTime
     */
    public function date($date = null, $part = null, $locale = null, $useTimezone = true)
    {
        if (!is_int($date) && empty($date)) {
            // $date may be false, but DateTime uses strict compare
            $date = null;
        }
        if ($part === null && is_numeric($date)) {
            $date = new DateTime('@' . $date);
            // DateTime created from timestamp always has +00:00 timezone, convert to UTC
            $date->setTimezone(new DateTimeZone('UTC'));
        } elseif ($part !== null) {
            $date = DateTime::createFromFormat($part, $date) ?: new DateTime($date ?: 'now');
        } else {
            $date = new DateTime($date ?: 'now');
        }
        if ($useTimezone) {
            if ($timezone = Mage::app()->getStore()->getConfig(self::XML_PATH_DEFAULT_TIMEZONE)) {
                $date->setTimezone(new DateTimeZone($timezone));
            }
        }

        return $date;
    }

    /**
     * Create mutable DateTime object for current locale
     * Alias for date() method with explicit name
     */
    public function dateMutable(
        string|int|DateTime|null $date = null,
        ?string $part = null,
        string|null $locale = null,
        bool $useTimezone = true,
    ): DateTime {
        return $this->date($date, $part, $locale, $useTimezone);
    }

    /**
     * Create immutable DateTime object for current locale
     */
    public function dateImmutable(
        string|int|DateTime|null $date = null,
        ?string $part = null,
        string|null $locale = null,
        bool $useTimezone = true,
    ): DateTimeImmutable {
        $dateTime = $this->date($date, $part, $locale, $useTimezone);
        return DateTimeImmutable::createFromMutable($dateTime);
    }

    /**
     * Create DateTime object with date converted to store timezone and store Locale
     *
     * @param   null|string|bool|int|Mage_Core_Model_Store $store Information about store
     * @param   string|int|DateTime|array|null $date date in UTC
     * @param   bool $includeTime flag for including time to date
     * @param   string|null $format Format for date parsing/output:
     *                              - null: Use locale default format (returns DateTime)
     *                              - 'html5': Return HTML5 native input format (returns string):
     *                                * type="date": YYYY-MM-DD (e.g., "2024-12-25")
     *                                * type="datetime-local": YYYY-MM-DDTHH:mm (e.g., "2024-12-25T14:30")
     *                              - PHP format strings: 'Y-m-d H:i:s', etc. (returns DateTime)
     * @return  DateTime|string|null
     */
    public function storeDate($store = null, $date = null, $includeTime = false, $format = null)
    {
        // Special handling for HTML5 format output when format is 'html5'
        if ($format === 'html5') {
            if (empty($date)) {
                return null;
            }

            try {
                $timezone = Mage::app()->getStore($store)->getConfig(self::XML_PATH_DEFAULT_TIMEZONE);
                $dateObj = new DateTime($date);
                $dateObj->setTimezone(new DateTimeZone($timezone));
                if (!$includeTime) {
                    $dateObj->setTime(0, 0, 0);
                }

                if ($includeTime) {
                    return $dateObj->format(self::HTML5_DATETIME_FORMAT);
                } else {
                    return $dateObj->format(self::DATE_FORMAT);
                }
            } catch (Exception $e) {
                return null;
            }
        }

        // Native DateTime handling
        $timezone = Mage::app()->getStore($store)->getConfig(self::XML_PATH_DEFAULT_TIMEZONE);

        if ($date instanceof DateTime) {
            // Date is already a DateTime object, use as-is
        } elseif ($format && !is_numeric($date)) {
            $date = DateTime::createFromFormat($format, $date) ?: new DateTime($date ?: 'now');
        } elseif (is_numeric($date)) {
            $date = new DateTime('@' . $date);
            // DateTime created from timestamp always has +00:00 timezone, convert to UTC
            $date->setTimezone(new DateTimeZone('UTC'));
        } else {
            $date = new DateTime($date ?: 'now');
        }

        $date->setTimezone(new DateTimeZone($timezone));
        if (!$includeTime) {
            $date->setTime(0, 0, 0);
        }
        return $date;
    }

    /**
     * Create DateTime object with date converted from store's timezone
     * to UTC time zone. Date can be passed in format of store's locale
     * or in format which was passed as parameter.
     *
     * @param mixed $store Information about store
     * @param string|int|DateTime|array|null $date date in store's timezone
     * @param bool $includeTime flag for including time to date
     * @param null|string $format Format for date parsing/output:
     *                             - null: Use locale default format (returns DateTime)
     *                             - 'html5': Parse HTML5 native input format (returns string):
     *                               * Accepts: YYYY-MM-DD (from type="date") or YYYY-MM-DDTHH:mm (from type="datetime-local")
     *                               * Returns: YYYY-MM-DD HH:mm:ss (MySQL datetime format)
     *                             - PHP format strings: 'Y-m-d H:i:s', etc. (returns DateTime)
     * @return DateTime|string|null
     */
    public function utcDate($store, $date, $includeTime = false, $format = null)
    {
        // Special handling for HTML5 native input formats
        if ($format === 'html5' && is_string($date)) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $date)) {
                // datetime-local format - validate the datetime
                $dateTime = DateTime::createFromFormat(self::HTML5_DATETIME_FORMAT, substr($date, 0, 16));
                if ($dateTime === false || $dateTime->format(self::HTML5_DATETIME_FORMAT) !== substr($date, 0, 16)) {
                    return null;
                }
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                // date format - validate the date
                $dateTime = DateTime::createFromFormat(self::DATE_FORMAT, $date);
                if ($dateTime === false || $dateTime->format(self::DATE_FORMAT) !== $date) {
                    return null;
                }
                if (!$includeTime) {
                    $dateTime->setTime(0, 0, 0);
                }
            } else {
                return null;
            }

            // Set to store timezone first
            $storeTimezone = Mage::app()->getStore($store)->getConfig(self::XML_PATH_DEFAULT_TIMEZONE);
            $dateTime->setTimezone(new DateTimeZone($storeTimezone));

            // Convert to UTC
            $dateTime->setTimezone(new DateTimeZone('UTC'));

            return $dateTime->format(self::DATETIME_FORMAT);
        }

        // Native DateTime handling
        $dateObj = $this->storeDate($store, $date, $includeTime, $format);
        $dateObj->setTimezone(new DateTimeZone(self::DEFAULT_TIMEZONE));
        return $dateObj;
    }

    /**
     * Get current date and time in standard format
     *
     * @return string Current datetime as 'Y-m-d H:i:s'
     */
    public static function now(): string
    {
        return date(self::DATETIME_FORMAT);
    }

    /**
     * Get current date in standard format (without time)
     *
     * @return string Current date as 'Y-m-d'
     */
    public static function today(): string
    {
        return date(self::DATE_FORMAT);
    }

    /**
     * Get store timestamp
     *
     * Timestamp will be built with store timezone settings
     *
     * @param   mixed $store
     * @return  int
     */
    public function storeTimeStamp($store = null)
    {
        $timezone = Mage::app()->getStore($store)->getConfig(self::XML_PATH_DEFAULT_TIMEZONE);
        $currentTimezone = @date_default_timezone_get();
        @date_default_timezone_set($timezone);
        $date = date(Mage_Core_Model_Locale::DATETIME_FORMAT);
        @date_default_timezone_set($currentTimezone);
        return strtotime($date);
    }

    /**
     * Create NumberFormatter object for current locale configured for currency
     *
     * @param   string $currency
     * @return  NumberFormatter
     */
    public function currency($currency)
    {
        Varien_Profiler::start('locale/currency');
        if (!isset(self::$_currencyCache[$this->getLocaleCode()][$currency])) {
            $formatter = new NumberFormatter($this->getLocaleCode(), NumberFormatter::CURRENCY);

            // Set the currency code on the formatter
            $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currency);

            // Get custom options from event
            $options = new Varien_Object();
            Mage::dispatchEvent('currency_display_options_forming', [
                'currency_options' => $options,
                'base_code' => $currency,
            ]);

            // Apply custom options if any
            if ($options->hasData()) {
                if ($options->hasData('symbol')) {
                    $formatter->setSymbol(NumberFormatter::CURRENCY_SYMBOL, $options->getData('symbol'));
                }
                if ($options->hasData('pattern')) {
                    $formatter->setPattern($options->getData('pattern'));
                }
            }

            self::$_currencyCache[$this->getLocaleCode()][$currency] = $formatter;
        }
        Varien_Profiler::stop('locale/currency');
        return self::$_currencyCache[$this->getLocaleCode()][$currency];
    }

    /**
     * Format currency value using locale-specific formatting
     *
     * @param float|int|string $value
     * @param string $currencyCode
     * @return string
     */
    public function formatCurrency($value, $currencyCode)
    {
        return $this->currency($currencyCode)->format((float) $value);
    }

    /**
     * Format price value using store's base currency
     *
     * @param float|int|string $value
     * @param int|string|null $storeId
     * @return string
     */
    public function formatPrice($value, $storeId = null)
    {
        $store = Mage::app()->getStore($storeId);
        $currencyCode = $store->getBaseCurrencyCode();
        return $this->formatCurrency($value, $currencyCode);
    }

    /**
     * Get currency symbol for a given currency code
     *
     * @param string $currencyCode
     * @return string
     */
    public function getCurrencySymbol($currencyCode)
    {
        return $this->currency($currencyCode)->getSymbol(NumberFormatter::CURRENCY_SYMBOL) ?: $currencyCode;
    }

    /**
     * Normalize a locale-formatted number string to float
     *
     * @param string $value
     * @return float|false
     */
    public function normalizeNumber($value)
    {
        if (!isset(self::$_numberFormatterCache[$this->getLocaleCode()])) {
            self::$_numberFormatterCache[$this->getLocaleCode()] = new NumberFormatter(
                $this->getLocaleCode(),
                NumberFormatter::DECIMAL,
            );
        }
        return self::$_numberFormatterCache[$this->getLocaleCode()]->parse($value);
    }

    /**
     * Returns the first found number from an string
     * Parsing depends on given locale (grouping and decimal)
     *
     * Examples for input:
     * '  2345.4356,1234' = 23455456.1234
     * '+23,3452.123' = 233452.123
     * ' 12343 ' = 12343
     * '-9456km' = -9456
     * '0' = 0
     * '2 054,10' = 2054.1
     * '2'054.52' = 2054.52
     * '2,46 GB' = 2.46
     *
     * @param string|float|int $value
     * @return float|null
     */
    public function getNumber($value)
    {
        if (is_null($value)) {
            return null;
        }

        if (!is_string($value)) {
            return (float) $value;
        }

        //trim spaces and apostrophes
        $value = str_replace(['\'', ' '], '', $value);

        $separatorComa = strpos($value, ',');
        $separatorDot  = strpos($value, '.');

        if ($separatorComa !== false && $separatorDot !== false) {
            if ($separatorComa > $separatorDot) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($separatorComa !== false) {
            $value = str_replace(',', '.', $value);
        }

        return (float) $value;
    }

    /**
     * Functions returns array with price formatting info for js function
     * formatCurrency in js/varien/js.js
     *
     * @return array
     */
    public function getJsPriceFormat()
    {
        $formatter = new NumberFormatter($this->getLocaleCode(), NumberFormatter::DECIMAL);
        $pattern = $formatter->getPattern();

        // Extract decimal and grouping separators
        $decimalSymbol = $formatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
        $groupSymbol = $formatter->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL);

        // Parse the pattern to determine precision
        $pos = strpos($pattern, ';');
        if ($pos !== false) {
            $pattern = substr($pattern, 0, $pos);
        }

        // Count decimal places
        $decimalPos = strpos($pattern, '.');
        $totalPrecision = 0;
        $requiredPrecision = 0;

        if ($decimalPos !== false) {
            $decimalPart = substr($pattern, $decimalPos + 1);
            // Count all decimal pattern characters
            $totalPrecision = strlen(preg_replace('/[^0#]/', '', $decimalPart));
            // Count required decimal places (0s)
            $requiredPrecision = strlen(preg_replace('/[^0]/', '', $decimalPart));
        }

        // Determine grouping length
        $groupLength = 3; // Default
        $integerPart = $decimalPos !== false ? substr($pattern, 0, $decimalPos) : $pattern;
        $lastComma = strrpos($integerPart, ',');
        if ($lastComma !== false) {
            $afterComma = substr($integerPart, $lastComma + 1);
            $groupLength = strlen(preg_replace('/[^0#]/', '', $afterComma));
        }

        // Count required integer digits
        $integerRequired = substr_count($integerPart, '0');

        return [
            'pattern' => Mage::app()->getStore()->getCurrentCurrency()->getOutputFormat(),
            'precision' => $totalPrecision,
            'requiredPrecision' => $requiredPrecision,
            'decimalSymbol' => $decimalSymbol,
            'groupSymbol' => $groupSymbol,
            'groupLength' => $groupLength,
            'integerRequired' => $integerRequired,
        ];
    }

    /**
     * Push current locale to stack and replace with locale from specified store
     * Event is not dispatched.
     *
     * @param int $storeId
     */
    public function emulate($storeId)
    {
        if ($storeId) {
            $this->_emulatedLocales[] = $this->getLocaleCode();
            $this->_localeCode = Mage::getStoreConfig(self::XML_PATH_DEFAULT_LOCALE, $storeId);
            Mage::getSingleton('core/translate')
                ->setLocale($this->_localeCode)
                ->init(Mage_Core_Model_App_Area::AREA_FRONTEND, true);
        } else {
            $this->_emulatedLocales[] = false;
        }
    }

    /**
     * Get last locale, used before last emulation
     *
     */
    public function revert()
    {
        if ($locale = array_pop($this->_emulatedLocales)) {
            $this->_localeCode = $locale;
            Mage::getSingleton('core/translate')->setLocale($this->_localeCode)->init('adminhtml', true);
        }
    }

    /**
     * Returns localized information as array, supported are several
     * types of information.
     * For detailed information about the types look into the documentation
     *
     * @param  string             $path   (Optional) Type of information to return
     * @param  string             $value  (Optional) Value for detail list
     * @return array Array with the wished information in the given language
     */
    public function getTranslationList($path = null, $value = null)
    {
        if ($path === 'country' || $path === 'territory') {
            return $this->getCountryTranslationList();
        }

        $locale = $this->getLocaleCode();

        switch ($path) {
            case 'language':
                $languages = [];
                $bundle = ResourceBundle::create($locale, 'ICUDATA-lang');
                if ($bundle !== null) {
                    $langs = $bundle->get('Languages');
                    if ($langs !== null) {
                        foreach ($langs as $code => $name) {
                            $languages[$code] = $name;
                        }
                    }
                }
                return $languages;

            case 'script':
                $scripts = [];
                $bundle = ResourceBundle::create($locale, 'ICUDATA-lang');
                if ($bundle !== null) {
                    $scriptData = $bundle->get('Scripts');
                    if ($scriptData !== null) {
                        foreach ($scriptData as $code => $name) {
                            $scripts[$code] = $name;
                        }
                    }
                }
                return $scripts;

            case 'territory':
            case 'country':
                return $this->getCountryTranslationList();

            case 'timezone':
                return DateTimeZone::listIdentifiers();

            case 'currency':
            case 'currencytoname':
                return $this->_getCurrencyList();

            case 'currencysymbol':
                $symbols = [];
                $currencies = $this->_getCurrencyList();
                foreach (array_keys($currencies) as $code) {
                    $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
                    $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $code);
                    $symbols[$code] = $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
                }
                return $symbols;

            case 'monthdays':
                return [
                    'sun' => 0,
                    'mon' => 1,
                    'tue' => 2,
                    'wed' => 3,
                    'thu' => 4,
                    'fri' => 5,
                    'sat' => 6,
                ];

            case 'days':
                // Return days in the format expected by getOptionWeekdays
                $date = new DateTime('2024-01-07'); // Sunday
                $formatter = new IntlDateFormatter(
                    $locale,
                    IntlDateFormatter::NONE,
                    IntlDateFormatter::NONE,
                    null,
                    IntlDateFormatter::GREGORIAN,
                    'EEEE', // Full weekday name
                );

                $days = [
                    'format' => [
                        'wide' => [],
                        'abbreviated' => [],
                        'narrow' => [],
                    ],
                ];

                // Wide format (full names)
                for ($i = 0; $i < 7; $i++) {
                    $dayCode = strtolower($date->format('l'));
                    $formatter->setPattern('EEEE');
                    $days['format']['wide'][$dayCode] = $formatter->format($date);

                    // Abbreviated format
                    $formatter->setPattern('EEE');
                    $days['format']['abbreviated'][$dayCode] = $formatter->format($date);

                    // Narrow format
                    $formatter->setPattern('EEEEE');
                    $days['format']['narrow'][$dayCode] = $formatter->format($date);

                    $date->modify('+1 day');
                }

                return $days;

            case 'month':
                $date = new DateTime('2024-01-01');
                $formatter = new IntlDateFormatter(
                    $locale,
                    IntlDateFormatter::NONE,
                    IntlDateFormatter::NONE,
                    null,
                    IntlDateFormatter::GREGORIAN,
                    'MMMM', // Full month name
                );

                $months = [
                    'format' => [
                        'wide' => [],
                        'abbreviated' => [],
                        'narrow' => [],
                    ],
                ];

                for ($i = 1; $i <= 12; $i++) {
                    // Wide format
                    $formatter->setPattern('MMMM');
                    $months['format']['wide'][$i] = $formatter->format($date);

                    // Abbreviated format
                    $formatter->setPattern('MMM');
                    $months['format']['abbreviated'][$i] = $formatter->format($date);

                    // Narrow format
                    $formatter->setPattern('MMMMM');
                    $months['format']['narrow'][$i] = $formatter->format($date);

                    $date->modify('+1 month');
                }

                return $months;

            case 'dateinterval':
                // Common date interval formats
                return [
                    'year' => 'yyyy',
                    'month' => 'MM',
                    'day' => 'dd',
                    'hour' => 'HH',
                    'minute' => 'mm',
                    'second' => 'ss',
                ];

            case 'dateformat':
            case 'date':
                $formatter = new IntlDateFormatter(
                    $locale,
                    IntlDateFormatter::SHORT,
                    IntlDateFormatter::NONE,
                );

                return [
                    'full' => (new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::NONE))->getPattern(),
                    'long' => (new IntlDateFormatter($locale, IntlDateFormatter::LONG, IntlDateFormatter::NONE))->getPattern(),
                    'medium' => (new IntlDateFormatter($locale, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE))->getPattern(),
                    'short' => (new IntlDateFormatter($locale, IntlDateFormatter::SHORT, IntlDateFormatter::NONE))->getPattern(),
                ];

            case 'timeformat':
            case 'time':
                return [
                    'full' => (new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::FULL))->getPattern(),
                    'long' => (new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::LONG))->getPattern(),
                    'medium' => (new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::MEDIUM))->getPattern(),
                    'short' => (new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::SHORT))->getPattern(),
                ];

            case 'localeupgrade':
                // Return empty array - no locale upgrades needed
                return [];

            case 'windowstotimezone':
                // Return a simplified mapping of timezone identifiers
                $zones = [];
                foreach (DateTimeZone::listIdentifiers() as $tz) {
                    $zones[$tz] = $tz;
                }
                return $zones;

            default:
                return [];
        }
    }

    /**
     * Returns a localized information string, supported are several types of information.
     * For detailed information about the types look into the documentation
     *
     * @param  string             $value  Name to get detailed information about
     * @param  string             $path   (Optional) Type of information to return
     * @return string|false The wished information in the given language
     */
    public function getTranslation($value = null, $path = null, ?string $locale = null)
    {
        if ($path === 'country' || $path === 'territory') {
            return $this->getCountryTranslation($value, $locale);
        }

        $useLocale = $locale ?: $this->getLocaleCode();

        return match ($path) {
            'language' => Locale::getDisplayLanguage($value, $useLocale),
            'script' => Locale::getDisplayScript($value, $useLocale),
            'territory', 'country' => Locale::getDisplayRegion('-' . $value, $useLocale),
            'currency', 'currencytoname' => (function () use ($useLocale, $value) {
                $formatter = new NumberFormatter($useLocale, NumberFormatter::CURRENCY);
                $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $value);
                return $formatter->getTextAttribute(NumberFormatter::CURRENCY_CODE);
            })(),
            'currencysymbol' => (function () use ($useLocale, $value) {
                $formatter = new NumberFormatter($useLocale, NumberFormatter::CURRENCY);
                $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $value);
                return $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
            })(),
            'timezone' => $value, // PHP doesn't have built-in timezone name translations
            'date', 'dateformat' => ($this->getTranslationList('dateformat'))[$value] ?? false,
            'time', 'timeformat' => ($this->getTranslationList('timeformat'))[$value] ?? false,
            'monthdays' => ($this->getTranslationList('monthdays'))[$value] ?? false,
            default => false,
        };
    }

    /**
     * Replace all yy date format to yyyy
     *
     * @param string $currentFormat
     * @return string|string[]|null
     */
    protected function _convertYearTwoDigitTo4($currentFormat)
    {
        return preg_replace('/(\byy\b)/', 'yyyy', $currentFormat);
    }

    /**
     * Returns the localized country name
     *
     * @param string $countryId Country to get detailed information about
     * @param string $locale Locale to get translation for, or system locale if null
     * @return false|string
     */
    public function getCountryTranslation($countryId, $locale = null)
    {
        if (!Mage::isInstalled()) {
            // During installation, use native PHP Intl extension for country translation
            return $this->getNativeCountryName($countryId, $locale);
        }

        $country = Mage::getModel('directory/country')->load($countryId);
        if ($country->getId()) {
            if ($locale) {
                $translated = $country->getTranslation($locale);
                if ($translated->getName()) {
                    return $translated->getName();
                }
            }
            return $country->getName();
        }

        return false;
    }

    /**
     * Returns an array with the name of all countries translated to the given language
     *
     * @return array<string, string>
     */
    public function getCountryTranslationList(): array
    {
        if (!Mage::isInstalled()) {
            // During installation, use native PHP Intl extension for country translations
            return $this->getNativeCountryList();
        }

        return Mage::getResourceModel('directory/country_collection')->toOptionHash();
    }

    /**
     * Get native country list using PHP Intl extension (dynamically from ICU data)
     *
     * @return array<string, string>
     */
    protected function getNativeCountryList(): array
    {
        $locale = $this->getLocaleCode();
        $countries = [];
        $countryCodes = [];

        // Dynamically extract country codes from available locales
        $locales = ResourceBundle::getLocales('');
        foreach ($locales as $localeCode) {
            $parsed = Locale::parseLocale($localeCode);
            if (isset($parsed['region']) && strlen($parsed['region']) === 2) {
                $countryCodes[$parsed['region']] = true;
            }
        }

        // Get display names for all discovered country codes
        foreach (array_keys($countryCodes) as $code) {
            try {
                $countryName = Locale::getDisplayRegion('-' . $code, $locale);
                if ($countryName && $countryName !== $code) {
                    $countries[$code] = $countryName;
                } else {
                    // Use country code itself as fallback when translation fails
                    $countries[$code] = $code;
                }
            } catch (IntlException $e) {
                // Use country code itself as fallback for exceptions
                $countries[$code] = $code;
            }
        }

        asort($countries);
        return $countries;
    }

    /**
     * Get native country name using PHP Intl extension
     */
    protected function getNativeCountryName(string $countryId, ?string $locale = null): string
    {
        $displayLocale = $locale ?: $this->getLocaleCode();

        try {
            $countryName = Locale::getDisplayRegion('-' . $countryId, $displayLocale);
            if ($countryName && $countryName !== $countryId) {
                return $countryName;
            } else {
                // Use country code itself as fallback when translation fails
                return $countryId;
            }
        } catch (IntlException $e) {
            // Use country code itself as fallback for exceptions
            return $countryId;
        }
    }

    /**
     * Checks if current date of the given store (in the store timezone) is within the range
     *
     * @param int|string|Mage_Core_Model_Store|null $store
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return bool
     */
    public function isStoreDateInInterval($store, $dateFrom = null, $dateTo = null)
    {
        if (!$store instanceof Mage_Core_Model_Store) {
            $store = Mage::app()->getStore($store);
        }

        $storeTimeStamp = $this->storeTimeStamp($store);
        $fromTimeStamp  = strtotime((string) $dateFrom);
        $toTimeStamp    = strtotime((string) $dateTo);
        if ($dateTo) {
            // fix date YYYY-MM-DD 00:00:00 to YYYY-MM-DD 23:59:59
            $endDate = new DateTime((string) $dateTo . ' 23:59:59');
            $toTimeStamp = $endDate->getTimestamp();
        }

        $result = false;
        if (!is_empty_date((string) $dateFrom) && $storeTimeStamp < $fromTimeStamp) {
        } elseif (!is_empty_date((string) $dateTo) && $storeTimeStamp > $toTimeStamp) {
        } else {
            $result = true;
        }

        return $result;
    }

    /**
     * Validate date string with auto-detection of HTML5/ISO format
     *
     * @param string $date The date string to validate
     */
    public static function isValidDate(string $date): bool
    {
        if (!$date) {
            return false;
        }

        // Auto-detect HTML5 input formats by pattern
        $formats = [
            'Y-m-d\TH:i:s',       // 2025-12-31T23:59:59 (datetime with seconds)
            'Y-m-d\TH:i',         // 2025-12-31T23:59 (datetime-local)
            'Y-m-d',              // 2025-12-31 (date)
            'H:i:s',              // 23:59:59 (time with seconds)
            'H:i',                // 23:59 (time)
        ];

        foreach ($formats as $format) {
            $dateTime = DateTime::createFromFormat($format, $date);
            if ($dateTime !== false && $dateTime->format($format) === $date) {
                return true;
            }
        }

        return false;
    }

}
