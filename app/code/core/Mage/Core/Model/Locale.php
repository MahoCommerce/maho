<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Locale extends \Maho\DataObject
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
     * Set default locale code
     */
    public function setDefaultLocale(string $locale): self
    {
        $this->_defaultLocale = $locale;
        return $this;
    }

    /**
     * REtrieve default locale code
     */
    public function getDefaultLocale(): string
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
     */
    public function setLocale(?string $locale = null): self
    {
        if ($locale !== null) {
            $this->_localeCode = $locale;
        } else {
            $this->_localeCode = $this->getDefaultLocale();
        }
        Mage::dispatchEvent('core_locale_set_locale', ['locale' => $this]);
        return $this;
    }

    /**
     * Retrieve timezone code
     */
    public function getTimezone(): string
    {
        return self::DEFAULT_TIMEZONE;
    }

    /**
     * Retrieve currency code
     */
    public function getCurrency(): string
    {
        return self::DEFAULT_CURRENCY;
    }

    /**
     * Retrieve locale object (compatibility method - returns locale code instead)
     */
    public function getLocale(): string
    {
        return $this->getLocaleCode();
    }

    /**
     * Retrieve locale code
     */
    public function getLocaleCode(): string
    {
        if ($this->_localeCode === null) {
            $this->setLocale();
        }
        return $this->_localeCode;
    }

    /**
     * Specify current locale code
     */
    public function setLocaleCode(string $code): self
    {
        $this->_localeCode = $code;
        return $this;
    }

    /**
     * Get options array for locale dropdown in current locale
     */
    public function getOptionLocales(): array
    {
        return $this->_getOptionLocales();
    }

    /**
     * Get translated to original locale options array for locale dropdown
     */
    public function getTranslatedOptionLocales(): array
    {
        return $this->_getOptionLocales(true);
    }

    /**
     * Get options array for locale dropdown
     *
     * @param   bool $translatedName translation flag
     */
    protected function _getOptionLocales(bool $translatedName = false): array
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
     */
    public function getOptionTimezones(): array
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
     */
    public function getOptionWeekdays(bool $preserveCodes = false, bool $ucFirstCode = false): array
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
     */
    public function getOptionCountries(): array
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
     */
    public function getOptionCurrencies(): array
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
     */
    public function getOptionAllCurrencies(): array
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
     */
    protected function _getCurrencyList(): array
    {
        $locale = $this->getLocaleCode();
        $currencies = [];

        // Get all available currencies from ICU data using ResourceBundle
        $bundle = ResourceBundle::create($locale, 'ICUDATA-curr');
        if ($bundle !== null) {
            $currencyData = $bundle->get('Currencies');

            if ($currencyData !== null) {
                // Get list of all currency codes
                $allCodes = [];
                foreach ($currencyData as $code => $data) {
                    if (strlen($code) === 3 && ctype_alpha($code)) {
                        $allCodes[] = $code;
                    }
                }

                // Now get the data for each code
                foreach ($allCodes as $code) {
                    $currInfo = $currencyData->get($code);
                    if ($currInfo !== null) {
                        // Get the display name (at index 1)
                        $displayName = $currInfo->get(1);
                        if ($displayName !== null) {
                            $currencies[$code] = $displayName;
                        } else {
                            // Fallback to code
                            $currencies[$code] = $code;
                        }
                    }
                }
            }
        }

        return $currencies;
    }

    protected function _sortOptionArray(array $option): array
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
     */
    public function getAllowLocales(): array
    {
        return Mage::getSingleton('core/locale_config')->getAllowedLocales();
    }

    /**
     * Retrieve array of allowed currencies
     */
    public function getAllowCurrencies(): array
    {
        $data = [];
        if (Mage::isInstalled()) {
            $data = Mage::app()->getStore()->getConfig(self::XML_PATH_ALLOW_CURRENCIES_INSTALLED);
            return explode(',', $data);
        }
        $data = Mage::getSingleton('core/locale_config')->getAllowedCurrencies();
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
     */
    public function getTimeFormat(?string $type = null): string
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
     */
    public function getDateTimeFormat(string $type): string
    {
        return $this->getDateFormat($type) . ' ' . $this->getTimeFormat($type);
    }


    /**
     * Use dateMutable() or dateImmutable() instead of this one.
     *
     * @param string             $part
     */
    public function date(string|int|DateTime|DateTimeImmutable|null $date = null, ?string $part = null, ?string $locale = null, bool $useTimezone = true): DateTime
    {
        if (!is_int($date) && empty($date)) {
            // $date may be false, but DateTime uses strict compare
            $date = null;
        }
        if ($date instanceof DateTimeInterface) {
            // If already a DateTime/DateTimeImmutable, convert to mutable DateTime
            $date = $date instanceof DateTime ? clone $date : DateTime::createFromInterface($date);
        } elseif ($part === null && is_numeric($date)) {
            $date = new DateTime('@' . $date);
            // DateTime created from timestamp always has +00:00 timezone, convert to UTC
            $date->setTimezone(new DateTimeZone(self::DEFAULT_TIMEZONE));
        } elseif ($part !== null) {
            $date = DateTime::createFromFormat($part, $date ?: 'now') ?: new DateTime($date ?: 'now');
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
     * @param   string|int|DateTime|DateTimeImmutable|null $date date in UTC
     * @param   bool $includeTime flag for including time to date
     * @param   string|null $format Format for date parsing/output:
     *                              - null: Use locale default format (returns DateTime)
     *                              - 'html5': Return HTML5 native input format (returns string):
     *                                * type="date": YYYY-MM-DD (e.g., "2024-12-25")
     *                                * type="datetime-local": YYYY-MM-DDTHH:mm (e.g., "2024-12-25T14:30")
     *                              - PHP format strings: 'Y-m-d H:i:s', etc. (returns DateTime)
     */
    public function storeDate(mixed $store = null, string|int|DateTime|DateTimeImmutable|null $date = null, bool $includeTime = false, ?string $format = null): DateTime|string|null
    {
        // Special handling for HTML5 format output when format is 'html5'
        if ($format === 'html5') {
            if (empty($date)) {
                return null;
            }

            try {
                $timezone = Mage::app()->getStore($store)->getConfig(self::XML_PATH_DEFAULT_TIMEZONE);
                $dateObj = $date instanceof DateTimeInterface ? DateTime::createFromInterface($date) : new DateTime($date);
                $dateObj->setTimezone(new DateTimeZone($timezone));
                if (!$includeTime) {
                    $dateObj->setTime(0, 0, 0);
                }

                if ($includeTime) {
                    return $dateObj->format(self::HTML5_DATETIME_FORMAT);
                }
                return $dateObj->format(self::DATE_FORMAT);
            } catch (Exception $e) {
                return null;
            }
        }

        // Native DateTime handling
        $timezone = Mage::app()->getStore($store)->getConfig(self::XML_PATH_DEFAULT_TIMEZONE);
        if (empty($timezone)) {
            $timezone = self::DEFAULT_TIMEZONE;
        }

        if ($date instanceof DateTime) {
            // Date is already a DateTime object, use as-is
        } elseif ($date instanceof DateTimeImmutable) {
            // Convert DateTimeImmutable to DateTime
            $date = DateTime::createFromInterface($date);
        } elseif ($format && !is_numeric($date)) {
            $date = DateTime::createFromFormat($format, $date) ?: new DateTime($date ?: 'now');
        } elseif (is_numeric($date)) {
            $date = new DateTime('@' . $date);
            // DateTime created from timestamp always has +00:00 timezone, convert to UTC
            $date->setTimezone(new DateTimeZone(self::DEFAULT_TIMEZONE));
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
     * @param string|int|DateTime|DateTimeImmutable|null $date date in store's timezone
     * @param bool $includeTime flag for including time to date
     * @param null|string $format Format for date parsing/output:
     *                             - null: Use locale default format (returns DateTime)
     *                             - 'html5': Parse HTML5 native input format (returns string):
     *                               * Accepts: YYYY-MM-DD (from type="date") or YYYY-MM-DDTHH:mm (from type="datetime-local")
     *                               * Returns: YYYY-MM-DD HH:mm:ss (MySQL datetime format)
     *                             - PHP format strings: 'Y-m-d H:i:s', etc. (returns DateTime)
     */
    public function utcDate(mixed $store, string|int|DateTime|DateTimeImmutable|null $date = null, bool $includeTime = false, ?string $format = null): DateTime|string|null
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
            $dateTime->setTimezone(new DateTimeZone(self::DEFAULT_TIMEZONE));

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
     */
    public function storeTimeStamp(mixed $store = null): int
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
     */
    public function currency(string $currency): NumberFormatter
    {
        \Maho\Profiler::start('locale/currency');
        if (!isset(self::$_currencyCache[$this->getLocaleCode()][$currency])) {
            $formatter = new NumberFormatter($this->getLocaleCode(), NumberFormatter::CURRENCY);

            // Set the currency code on the formatter
            $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currency);

            // Check for custom currency symbol from CurrencySymbol model
            $customSymbol = false;
            try {
                $currencySymbolModel = Mage::getSingleton('currencysymbol/system_currencysymbol');
                $customSymbol = $currencySymbolModel->getCurrencySymbol($currency, $this->getLocaleCode());
            } catch (Exception $e) {
                // CurrencySymbol module may not be available, continue with default behavior
            }

            // Get custom options from event
            $options = new \Maho\DataObject();
            if ($customSymbol) {
                $options->setData('symbol', $customSymbol);
            }

            Mage::dispatchEvent('currency_display_options_forming', [
                'currency_options' => $options,
                'base_code' => $currency,
                'locale_code' => $this->getLocaleCode(),
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
        \Maho\Profiler::stop('locale/currency');
        return self::$_currencyCache[$this->getLocaleCode()][$currency];
    }

    /**
     * Format currency value using locale-specific formatting
     */
    public function formatCurrency(float|int|string|null $value, string $currencyCode): string
    {
        return $this->currency($currencyCode)->format((float) $value);
    }

    /**
     * Format price value using store's base currency
     */
    public function formatPrice(float|int|string|null $value, int|string|null $storeId = null): string
    {
        $store = Mage::app()->getStore($storeId);
        $currencyCode = $store->getBaseCurrencyCode();
        return $this->formatCurrency($value, $currencyCode);
    }

    /**
     * Get currency symbol for a given currency code
     */
    public function getCurrencySymbol(string $currencyCode): string
    {
        return $this->currency($currencyCode)->getSymbol(NumberFormatter::CURRENCY_SYMBOL) ?: $currencyCode;
    }

    /**
     * Normalize a locale-formatted number string to float
     */
    public function normalizeNumber(string $value): float|false
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
     */
    public function getNumber(mixed $value): ?float
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
     */
    public function getJsPriceFormat(): array
    {
        $formatter = new NumberFormatter($this->getLocaleCode(), NumberFormatter::DECIMAL);
        $pattern = $formatter->getPattern();

        // Extract decimal and grouping separators
        $decimalSymbol = $formatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
        $groupSymbol = $formatter->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL);

        // Get currency pattern with actual symbol (not generic ¤)
        $currency = Mage::app()->getStore()->getCurrentCurrency();
        $currencyFormatter = $this->currency($currency->getCode());
        $currencyPattern = $currencyFormatter->getPattern();
        $currencySymbol = $currencyFormatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL);

        // Parse the CURRENCY pattern to determine precision (not decimal pattern)
        $pos = strpos($currencyPattern, ';');
        if ($pos !== false) {
            $currencyPattern = substr($currencyPattern, 0, $pos);
        }

        // Count decimal places from currency pattern
        $decimalPos = strpos($currencyPattern, '.');
        $totalPrecision = 0;
        $requiredPrecision = 0;

        if ($decimalPos !== false) {
            $decimalPart = substr($currencyPattern, $decimalPos + 1);
            // Count all decimal pattern characters
            $totalPrecision = strlen(preg_replace('/[^0#]/', '', $decimalPart));
            // Count required decimal places (0s)
            $requiredPrecision = strlen(preg_replace('/[^0]/', '', $decimalPart));
        }

        // Determine grouping length from currency pattern
        $groupLength = 3; // Default
        $integerPart = $decimalPos !== false ? substr($currencyPattern, 0, $decimalPos) : $currencyPattern;
        $lastComma = strrpos($integerPart, ',');
        if ($lastComma !== false) {
            $afterComma = substr($integerPart, $lastComma + 1);
            $groupLength = strlen(preg_replace('/[^0#¤]/', '', $afterComma));
        }

        // Count required integer digits from currency pattern
        $integerRequired = substr_count($integerPart, '0');

        // Replace generic currency symbol ¤ with actual currency symbol
        $jsPattern = preg_replace('/[#0,\.]+/', '%s', $currencyPattern);
        $jsPattern = str_replace('¤', $currencySymbol, $jsPattern);

        return [
            'pattern' => $jsPattern,
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
     */
    public function emulate(mixed $store): void
    {
        if ($store) {
            $this->_emulatedLocales[] = $this->getLocaleCode();
            $storeId = $store instanceof Mage_Core_Model_Store ? $store->getId() : $store;
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
     */
    public function revert(): void
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
    public function getTranslationList(?string $path = null, mixed $value = null): array
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
                $bundle = ResourceBundle::create($locale, 'ICUDATA-curr');
                if ($bundle !== null) {
                    $currencyData = $bundle->get('Currencies');

                    if ($currencyData !== null) {
                        // Get list of all currency codes
                        $allCodes = [];
                        foreach ($currencyData as $code => $data) {
                            if (strlen($code) === 3 && ctype_alpha($code)) {
                                $allCodes[] = $code;
                            }
                        }

                        // Now get the symbol for each code
                        foreach ($allCodes as $code) {
                            $currInfo = $currencyData->get($code);
                            if ($currInfo !== null) {
                                // Get the symbol (at index 0)
                                $symbol = $currInfo->get(0);
                                if ($symbol !== null) {
                                    $symbols[$code] = $symbol;
                                } else {
                                    // Fallback to code
                                    $symbols[$code] = $code;
                                }
                            }
                        }
                    }
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
    public function getTranslation(mixed $value = null, ?string $path = null, ?string $locale = null): string|false
    {
        if ($path === 'country' || $path === 'territory') {
            // If no locale specified, use this locale model's locale
            return $this->getCountryTranslation($value, $locale ?: $this->getLocaleCode());
        }

        $useLocale = $locale ?: $this->getLocaleCode();

        return match ($path) {
            'language' => Locale::getDisplayLanguage($value, $useLocale),
            'script' => Locale::getDisplayScript($value, $useLocale),
            'territory', 'country' => Locale::getDisplayRegion('-' . $value, $useLocale),
            'currency', 'currencytoname' => (function () use ($useLocale, $value) {
                $bundle = ResourceBundle::create($useLocale, 'ICUDATA-curr');
                if ($bundle !== null) {
                    $currencyData = $bundle->get('Currencies');
                    if ($currencyData !== null) {
                        $currInfo = $currencyData->get($value);
                        if ($currInfo !== null) {
                            $displayName = $currInfo->get(1);
                            if ($displayName !== null) {
                                return $displayName;
                            }
                        }
                    }
                }
                return $value; // Fallback
            })(),
            'currencysymbol' => (function () use ($useLocale, $value) {
                $bundle = ResourceBundle::create($useLocale, 'ICUDATA-curr');
                if ($bundle !== null) {
                    $currencyData = $bundle->get('Currencies');
                    if ($currencyData !== null) {
                        $currInfo = $currencyData->get($value);
                        if ($currInfo !== null) {
                            $symbol = $currInfo->get(0);
                            if ($symbol !== null) {
                                return $symbol;
                            }
                        }
                    }
                }
                return $value; // Fallback
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
     */
    protected function _convertYearTwoDigitTo4(string $currentFormat): string
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
    public function getCountryTranslation(string $countryId, ?string $locale = null): string|false
    {
        // Always use native ICU data for country translations to ensure proper locale support
        return $this->getNativeCountryName($countryId, $locale);
    }

    /**
     * Returns an array with the name of all countries translated to the given language
     *
     * @return array<string, string>
     */
    public function getCountryTranslationList(): array
    {
        // Always use native ICU data for country translations to ensure proper locale support
        return $this->getNativeCountryList();
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
            }
            // Use country code itself as fallback when translation fails
            return $countryId;
        } catch (IntlException $e) {
            // Use country code itself as fallback for exceptions
            return $countryId;
        }
    }

    /**
     * Checks if current date of the given store (in the store timezone) is within the range
     *
     * @param int|string|Mage_Core_Model_Store|null $store
     */
    public function isStoreDateInInterval(mixed $store, ?string $dateFrom = null, ?string $dateTo = null): bool
    {
        if (!$store instanceof Mage_Core_Model_Store) {
            $store = Mage::app()->getStore($store);
        }

        $storeTimeStamp = $this->storeTimeStamp($store);
        $fromTimeStamp  = strtotime((string) $dateFrom);
        $toTimeStamp    = strtotime((string) $dateTo);
        if ($dateTo) {
            // fix date YYYY-MM-DD 00:00:00 to YYYY-MM-DD 23:59:59
            $endDate = new DateTime((string) $dateTo);
            $endDate->setTime(23, 59, 59);
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
