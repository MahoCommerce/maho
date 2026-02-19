<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2016-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Mage_Core_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_DEFAULT_COUNTRY              = 'general/country/default';
    public const XML_PATH_PROTECTED_FILE_EXTENSIONS    = 'general/file/protected_extensions';
    public const XML_PATH_ENCRYPTION_MODEL             = 'global/helpers/core/encryption_model';
    public const XML_PATH_DEV_ALLOW_IPS                = 'dev/restrict/allow_ips';
    public const XML_PATH_CACHE_BETA_TYPES             = 'global/cache/betatypes';

    public const CHARS_LOWERS                          = 'abcdefghijklmnopqrstuvwxyz';
    public const CHARS_UPPERS                          = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const CHARS_DIGITS                          = '0123456789';
    public const CHARS_SPECIALS                        = '!$*+-.=?@^_|~';
    public const CHARS_PASSWORD_LOWERS                 = 'abcdefghjkmnpqrstuvwxyz';
    public const CHARS_PASSWORD_UPPERS                 = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    public const CHARS_PASSWORD_DIGITS                 = '23456789';
    public const CHARS_PASSWORD_SPECIALS               = '!$*-.=?@_';

    /**
     * Config paths to merchant country code and merchant VAT number
     */
    public const XML_PATH_MERCHANT_COUNTRY_CODE = 'general/store_information/merchant_country';
    public const XML_PATH_MERCHANT_VAT_NUMBER = 'general/store_information/merchant_vat_number';
    public const XML_PATH_EU_COUNTRIES_LIST = 'general/country/eu_countries';

    /**
     * Const for correct dividing decimal values
     */
    public const DIVIDE_EPSILON = 10000;

    protected $_moduleName = 'Mage_Core';

    /**
     * @var Mage_Core_Model_Encryption
     */
    protected $_encryptor = null;

    protected $_allowedFormats = [
        Mage_Core_Model_Locale::FORMAT_TYPE_FULL,
        Mage_Core_Model_Locale::FORMAT_TYPE_LONG,
        Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM,
        Mage_Core_Model_Locale::FORMAT_TYPE_SHORT,
    ];

    /**
     * @return Mage_Core_Model_Encryption
     */
    public function getEncryptor()
    {
        if ($this->_encryptor === null) {
            $encryptionModel = (string) Mage::getConfig()->getNode(self::XML_PATH_ENCRYPTION_MODEL);
            if ($encryptionModel) {
                $this->_encryptor = new $encryptionModel();
            } else {
                $this->_encryptor = Mage::getModel('core/encryption');
            }

            $this->_encryptor->setHelper($this);
        }
        return $this->_encryptor;
    }

    /**
     * Convert and format price value for current application store
     *
     * @param   float $value
     * @param   bool $format
     * @param   bool $includeContainer
     * @return  mixed
     */
    public static function currency($value, $format = true, $includeContainer = true)
    {
        return self::currencyByStore($value, null, $format, $includeContainer);
    }

    /**
     * Convert and format price value for specified store
     *
     * @param   float $value
     * @param   int|Mage_Core_Model_Store $store
     * @param   bool $format
     * @param   bool $includeContainer
     * @return  mixed
     */
    public static function currencyByStore($value, $store = null, $format = true, $includeContainer = true)
    {
        try {
            if (!($store instanceof Mage_Core_Model_Store)) {
                $store = Mage::app()->getStore($store);
            }

            $value = $store->convertPrice($value, $format, $includeContainer);
        } catch (Exception $e) {
            $value = $e->getMessage();
        }

        return $value;
    }

    /**
     * Format and convert currency using current store option
     *
     * @param   float $value
     * @param   bool $includeContainer
     * @return  string
     */
    public function formatCurrency($value, $includeContainer = true)
    {
        return self::currency($value, true, $includeContainer);
    }

    /**
     * Formats price
     *
     * @param float $price
     * @param bool $includeContainer
     * @return string
     */
    public function formatPrice($price, $includeContainer = true)
    {
        return Mage::app()->getStore()->formatPrice($price, $includeContainer);
    }

    /**
     * Format date using current locale options and time zone.
     *
     * @param   string|int|DateTime|null   $date If empty, return current datetime.
     * @param   string                      $format   See Mage_Core_Model_Locale::FORMAT_TYPE_* constants
     * @param   bool                        $showTime Whether to include time
     * @return  string
     */
    public function formatDate($date = null, $format = Mage_Core_Model_Locale::FORMAT_TYPE_SHORT, $showTime = false)
    {
        return $this->formatTimezoneDate($date, $format, $showTime);
    }

    /**
     * Format date using current locale options and time zone.
     *
     * @param   string|int|DateTime|null   $date The date to format. Can be:
     *                                            - null: Uses current time
     *                                            - int: Unix timestamp (assumes UTC)
     *                                            - string: Date string (e.g., "2025-08-01 09:24:18")
     *                                            - DateTime: Existing DateTime object
     * @param   string                      $format Display format constant:
     *                                            - FORMAT_TYPE_SHORT: Brief format (e.g., "8/1/25")
     *                                            - FORMAT_TYPE_MEDIUM: Standard format (e.g., "Aug 1, 2025")
     *                                            - FORMAT_TYPE_LONG: Detailed format (e.g., "August 1, 2025")
     *                                            - FORMAT_TYPE_FULL: Complete format (e.g., "Thursday, August 1, 2025")
     * @param   bool                        $showTime Whether to include time in the output
     *                                            - true: "Aug 1, 2025, 10:24:18 AM"
     *                                            - false: "Aug 1, 2025"
     * @param   bool                        $useTimezone Whether to convert the date to store timezone
     *                                            - true: Converts from UTC to store timezone before formatting
     *                                            - false: Formats the date in its current timezone (typically UTC)
     * @return  string                      Formatted date string according to locale settings
     */
    public function formatTimezoneDate(
        string|int|DateTime|null $date = null,
        string $format = Mage_Core_Model_Locale::FORMAT_TYPE_SHORT,
        bool $showTime = false,
        bool $useTimezone = true,
    ): string {
        if (!in_array($format, $this->_allowedFormats, true)) {
            return $date;
        }

        $locale = Mage::app()->getLocale();
        if (empty($date)) {
            $date = $locale->date(Mage::getSingleton('core/date')->gmtTimestamp(), null, null, $useTimezone);
        } elseif (is_int($date)) {
            $date = $locale->date($date, null, null, $useTimezone);
        } elseif (!$date instanceof DateTime) {
            if (($time = strtotime($date)) !== false) {
                $date = $locale->date($time, null, null, $useTimezone);
            } else {
                return '';
            }
        }

        $dateStyle = match ($format) {
            Mage_Core_Model_Locale::FORMAT_TYPE_SHORT => IntlDateFormatter::SHORT,
            Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM => IntlDateFormatter::MEDIUM,
            Mage_Core_Model_Locale::FORMAT_TYPE_LONG => IntlDateFormatter::LONG,
            Mage_Core_Model_Locale::FORMAT_TYPE_FULL => IntlDateFormatter::FULL,
            default => IntlDateFormatter::SHORT,
        };
        $timeStyle = $showTime ? $dateStyle : IntlDateFormatter::NONE;

        $formatter = new IntlDateFormatter(
            $locale->getLocaleCode(),
            $dateStyle,
            $timeStyle,
            $date->getTimezone(),
        );

        return $formatter->format($date);
    }

    /**
     * Format time using current locale options
     *
     * @param   string|DateTime|null $time
     * @param   string              $format
     * @param   bool                $showDate
     * @return  string
     */
    public function formatTime($time = null, $format = Mage_Core_Model_Locale::FORMAT_TYPE_SHORT, $showDate = false)
    {
        if (!in_array($format, $this->_allowedFormats, true)) {
            return $time;
        }

        $locale = Mage::app()->getLocale();
        if (is_null($time)) {
            $date = $locale->date(time());
        } elseif ($time instanceof DateTime) {
            $date = $time;
        } else {
            $date = $locale->date(strtotime($time));
        }

        // Use IntlDateFormatter to format with locale-specific patterns
        $dateStyle = $showDate ? match ($format) {
            Mage_Core_Model_Locale::FORMAT_TYPE_SHORT => IntlDateFormatter::SHORT,
            Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM => IntlDateFormatter::MEDIUM,
            Mage_Core_Model_Locale::FORMAT_TYPE_LONG => IntlDateFormatter::LONG,
            Mage_Core_Model_Locale::FORMAT_TYPE_FULL => IntlDateFormatter::FULL,
            default => IntlDateFormatter::SHORT,
        } : IntlDateFormatter::NONE;
        $timeStyle = match ($format) {
            Mage_Core_Model_Locale::FORMAT_TYPE_SHORT => IntlDateFormatter::SHORT,
            Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM => IntlDateFormatter::MEDIUM,
            Mage_Core_Model_Locale::FORMAT_TYPE_LONG => IntlDateFormatter::LONG,
            Mage_Core_Model_Locale::FORMAT_TYPE_FULL => IntlDateFormatter::FULL,
            default => IntlDateFormatter::SHORT,
        };

        $formatter = new IntlDateFormatter(
            $locale->getLocaleCode(),
            $dateStyle,
            $timeStyle,
            $date->getTimezone(),
        );

        return $formatter->format($date);
    }

    /**
     * Encrypt data using application key
     */
    public function encrypt(string $data): string
    {
        if (!Mage::isInstalled()) {
            return $data;
        }
        return $this->getEncryptor()->encrypt($data);
    }

    /**
     * Decrypt data using application key
     */
    public function decrypt(?string $data): string
    {
        if (!Mage::isInstalled()) {
            return $data;
        }
        return $this->getEncryptor()->decrypt($data);
    }

    public function validateKey(string $key): bool
    {
        return $this->getEncryptor()->validateKey($key);
    }

    /**
     * @param int $len
     * @param string|null $chars
     * @return string
     */
    public function getRandomString($len, $chars = null)
    {
        if (is_null($chars)) {
            $chars = self::CHARS_LOWERS . self::CHARS_UPPERS . self::CHARS_DIGITS;
        }
        $str = '';
        for ($i = 0, $lc = strlen($chars) - 1; $i < $len; $i++) {
            $str .= $chars[random_int(0, $lc)];
        }
        return $str;
    }

    /**
     * Generate salted hash from password
     *
     * @param string $password
     * @param string|int|bool $salt
     * @return string
     */
    public function getHash(#[\SensitiveParameter] $password, $salt = false)
    {
        return $this->getEncryptor()->getHash($password, $salt);
    }

    /**
     * Generate password hash for user
     *
     * @param string $password
     * @param mixed $salt
     * @return string
     */
    public function getHashPassword(#[\SensitiveParameter] $password, $salt = false)
    {
        $encryptionModel = $this->getEncryptor();
        $latestVersionHash = $this->getVersionHash($encryptionModel);
        if ($latestVersionHash == $encryptionModel::HASH_VERSION_SHA512) {
            return $this->getEncryptor()->getHashPassword($password, $salt);
        }
        return $this->getEncryptor()->getHashPassword($password, Mage_Admin_Model_User::HASH_SALT_EMPTY);
    }

    /**
     * @param string $password
     * @param string $hash
     * @return bool
     * @throws Exception
     */
    public function validateHash(#[\SensitiveParameter] $password, $hash)
    {
        return $this->getEncryptor()->validateHash($password, $hash);
    }

    /**
     * Get encryption method depending on the presence of the function - password_hash.
     *
     * @return int
     */
    public function getVersionHash(Mage_Core_Model_Encryption $encryptionModel)
    {
        return function_exists('password_hash')
            ? $encryptionModel::HASH_VERSION_LATEST
            : $encryptionModel::HASH_VERSION_SHA512;
    }

    /**
     * Retrieve store identifier
     *
     * @param   bool|int|Mage_Core_Model_Store|null|string $store
     * @return  int
     */
    public function getStoreId($store = null)
    {
        return Mage::app()->getStore($store)->getId();
    }

    /**
     * @param string $string
     * @param bool $german
     * @return false|string
     */
    public function removeAccents($string, $german = false)
    {
        static $replacements;

        if (empty($replacements[$german])) {
            $subst = [
                // single ISO-8859-1 letters
                192 => 'A', 193 => 'A', 194 => 'A', 195 => 'A', 196 => 'A', 197 => 'A', 199 => 'C',
                208 => 'D', 200 => 'E', 201 => 'E', 202 => 'E', 203 => 'E', 204 => 'I', 205 => 'I',
                206 => 'I', 207 => 'I', 209 => 'N', 210 => 'O', 211 => 'O', 212 => 'O', 213 => 'O',
                214 => 'O', 216 => 'O', 138 => 'S', 217 => 'U', 218 => 'U', 219 => 'U', 220 => 'U',
                221 => 'Y', 142 => 'Z', 224 => 'a', 225 => 'a', 226 => 'a', 227 => 'a', 228 => 'a',
                229 => 'a', 231 => 'c', 232 => 'e', 233 => 'e', 234 => 'e', 235 => 'e', 236 => 'i',
                237 => 'i', 238 => 'i', 239 => 'i', 241 => 'n', 240 => 'o', 242 => 'o', 243 => 'o',
                244 => 'o', 245 => 'o', 246 => 'o', 248 => 'o', 154 => 's', 249 => 'u', 250 => 'u',
                251 => 'u', 252 => 'u', 253 => 'y', 255 => 'y', 158 => 'z',
                // HTML entities
                258 => 'A', 260 => 'A', 262 => 'C', 268 => 'C', 270 => 'D', 272 => 'D', 280 => 'E',
                282 => 'E', 286 => 'G', 304 => 'I', 313 => 'L', 317 => 'L', 321 => 'L', 323 => 'N',
                327 => 'N', 336 => 'O', 340 => 'R', 344 => 'R', 346 => 'S', 350 => 'S', 354 => 'T',
                356 => 'T', 366 => 'U', 368 => 'U', 377 => 'Z', 379 => 'Z', 259 => 'a', 261 => 'a',
                263 => 'c', 269 => 'c', 271 => 'd', 273 => 'd', 281 => 'e', 283 => 'e', 287 => 'g',
                305 => 'i', 322 => 'l', 314 => 'l', 318 => 'l', 324 => 'n', 328 => 'n', 337 => 'o',
                341 => 'r', 345 => 'r', 347 => 's', 351 => 's', 357 => 't', 355 => 't', 367 => 'u',
                369 => 'u', 378 => 'z', 380 => 'z',
                // ligatures
                198 => 'Ae', 230 => 'ae', 140 => 'Oe', 156 => 'oe', 223 => 'ss',
            ];

            if ($german) {
                // umlauts
                $subst = [
                    196 => 'Ae', 228 => 'ae', 214 => 'Oe', 246 => 'oe', 220 => 'Ue', 252 => 'ue',
                ] + $subst;
            }

            $replacements[$german] = [];
            foreach ($subst as $k => $v) {
                $replacements[$german][$k < 256 ? chr($k) : '&#' . $k . ';'] = $v;
            }
        }

        // convert string from default database format (UTF-8)
        // to encoding which replacement arrays made with (ISO-8859-1)
        if ($s = @iconv('UTF-8', 'ISO-8859-1', $string)) {
            $string = $s;
        }

        // Replace
        $string = strtr($string, $replacements[$german]);

        return $string;
    }

    /**
     * @param null|string|bool|int|Mage_Core_Model_Store $storeId
     * @return bool
     */
    public function isDevAllowed($storeId = null)
    {
        $allow = true;

        $allowedIps = Mage::getStoreConfig(self::XML_PATH_DEV_ALLOW_IPS, $storeId);
        $remoteAddr = Mage::helper('core/http')->getRemoteAddr();
        if (!empty($allowedIps) && !empty($remoteAddr)) {
            $allowedIps = preg_split('#\s*,\s*#', $allowedIps, -1, PREG_SPLIT_NO_EMPTY);
            if (!in_array($remoteAddr, $allowedIps)
                && !in_array(Mage::helper('core/http')->getHttpHost(), $allowedIps)
            ) {
                $allow = false;
            }
        }

        return $allow;
    }

    /**
     * Get information about available cache types
     *
     * @return array
     */
    public function getCacheTypes()
    {
        $types = [];
        $config = Mage::getConfig()->getNode(Mage_Core_Model_Cache::XML_PATH_TYPES);
        foreach ($config->children() as $type => $node) {
            $types[$type] = (string) $node->label;
        }
        return $types;
    }

    /**
     * Get information about available cache beta types
     *
     * @return array
     */
    public function getCacheBetaTypes()
    {
        $types = [];
        $config = Mage::getConfig()->getNode(self::XML_PATH_CACHE_BETA_TYPES);
        if ($config) {
            foreach ($config->children() as $type => $node) {
                $types[$type] = (string) $node->label;
            }
        }
        return $types;
    }

    /**
     * Copy data from object|array to object|array containing fields
     * from fieldset matching an aspect.
     *
     * Contents of $aspect are a field name in target object or array.
     * If '*' - will be used the same name as in the source object or array.
     *
     * @param string $fieldset
     * @param string $aspect
     * @param array|\Maho\DataObject $source
     * @param array|\Maho\DataObject $target
     * @param string $root
     * @return bool
     */
    public function copyFieldset($fieldset, $aspect, $source, $target, $root = 'global')
    {
        if (!(is_array($source) || $source instanceof \Maho\DataObject)
            || !(is_array($target) || $target instanceof \Maho\DataObject)
        ) {
            return false;
        }
        $fields = Mage::getConfig()->getFieldset($fieldset, $root);
        if (!$fields) {
            return false;
        }

        $sourceIsArray = is_array($source);
        $targetIsArray = is_array($target);

        $result = false;
        foreach ($fields as $code => $node) {
            if (empty($node->$aspect)) {
                continue;
            }

            if ($sourceIsArray) {
                $value = $source[$code] ?? null;
            } else {
                $value = $source->getDataUsingMethod($code);
            }

            $targetCode = (string) $node->$aspect;
            $targetCode = $targetCode == '*' ? $code : $targetCode;

            if ($targetIsArray) {
                $target[$targetCode] = $value;
            } else {
                $target->setDataUsingMethod($targetCode, $value);
            }

            $result = true;
        }

        $eventName = sprintf('core_copy_fieldset_%s_%s', $fieldset, $aspect);
        Mage::dispatchEvent($eventName, [
            'target' => $target,
            'source' => $source,
            'root'   => $root,
        ]);

        return $result;
    }

    /**
     * Transform an assoc array to SimpleXMLElement object
     * Array has some limitations. Appropriate exceptions will be thrown
     *
     * @param string $rootName
     * @return SimpleXMLElement
     * @throws Exception
     */
    public function assocToXml(array $array, $rootName = '_')
    {
        if (empty($rootName) || is_numeric($rootName)) {
            throw new Exception('Root element must not be empty or numeric');
        }

        $xmlstr = <<<XML
<?xml version='1.0' encoding='UTF-8' standalone='yes'?>
<$rootName></$rootName>
XML;
        $xml = new SimpleXMLElement($xmlstr);
        foreach (array_keys($array) as $key) {
            if (is_numeric($key)) {
                throw new Exception('Array root keys must not be numeric.');
            }
        }
        return self::_assocToXml($array, $rootName, $xml);
    }

    /**
     * Function, that actually recursively transforms array to xml
     *
     * @param string $rootName
     * @return SimpleXMLElement
     * @throws Exception
     */
    private function _assocToXml(array $array, $rootName, SimpleXMLElement &$xml)
    {
        $hasNumericKey = false;
        $hasStringKey  = false;
        foreach ($array as $key => $value) {
            if (!is_array($value)) {
                if (is_string($key)) {
                    if ($key === $rootName) {
                        throw new Exception('Associative key must not be the same as its parent associative key.');
                    }
                    $hasStringKey = true;
                    $xml->$key = $value;
                } elseif (is_int($key)) {
                    $hasNumericKey = true;
                    $xml->{$rootName}[$key] = $value;
                }
            } else {
                self::_assocToXml($value, $key, $xml->$key);
            }
        }
        if ($hasNumericKey && $hasStringKey) {
            throw new Exception('Associative and numeric keys must not be mixed at one level.');
        }
        return $xml;
    }

    /**
     * Transform SimpleXMLElement to associative array
     * SimpleXMLElement must be conform structure, generated by assocToXml()
     *
     * @return array
     */
    public function xmlToAssoc(SimpleXMLElement $xml)
    {
        $array = [];
        foreach ($xml as $key => $value) {
            if (isset($value->$key)) {
                $i = 0;
                foreach ($value->$key as $v) {
                    $array[$key][$i++] = (string) $v;
                }
            } else {
                // try to transform it into string value, trimming spaces between elements
                $array[$key] = trim((string) $value);
                if (empty($array[$key]) && !empty($value)) {
                    $array[$key] = self::xmlToAssoc($value);
                } else { // untrim strings values
                    $array[$key] = (string) $value;
                }
            }
        }
        return $array;
    }

    /**
     * Encode the mixed $valueToEncode into the JSON format
     *
     * @param mixed $valueToEncode
     * @param bool $cycleCheck Optional; whether or not to check for object recursion; off by default
     * @param  array $options Additional options used during encoding
     * @return string
     * @throws JsonException
     */
    public function jsonEncode($valueToEncode, $cycleCheck = false, $options = [])
    {
        $json = json_encode($valueToEncode, JSON_THROW_ON_ERROR);

        /** @var Mage_Core_Model_Translate_Inline $inline */
        $inline = Mage::getSingleton('core/translate_inline');
        if ($inline->isAllowed()) {
            $inline->setIsJson(true);
            $inline->processResponseBody($json);
            $inline->setIsJson(false);
        }

        return $json;
    }

    /**
     * Decodes the given $encodedValue string which is
     * encoded in the JSON format
     *
     * switch added to prevent exceptions in json_decode
     *
     * @param string $encodedValue
     * @param bool $associative When true, JSON objects will be returned as associative arrays
     * @return mixed
     * @throws JsonException
     */
    public function jsonDecode($encodedValue, $associative = true)
    {
        $encodedValue = match ($encodedValue) {
            null => 'null',
            true => 'true',
            false => 'false',
            '' => '""',
            default => $encodedValue,
        };

        return json_decode($encodedValue, $associative, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Generate a hash from unique ID
     * @param string $prefix
     * @return string
     */
    public function uniqHash($prefix = '')
    {
        return $prefix . md5(uniqid(microtime() . mt_rand(), true));
    }

    /**
     * Return default country code
     *
     * @param Mage_Core_Model_Store|string|int $store
     * @return string
     */
    public function getDefaultCountry($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_DEFAULT_COUNTRY, $store);
    }

    /**
     * Return list with protected file extensions
     *
     * @param Mage_Core_Model_Store|string|int $store
     * @return array
     */
    public function getProtectedFileExtensions($store = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_PROTECTED_FILE_EXTENSIONS, $store);
    }

    /**
     * Retrieve merchant country code
     *
     * @param Mage_Core_Model_Store|string|int|null $store
     * @return string
     */
    public function getMerchantCountryCode($store = null)
    {
        return (string) Mage::getStoreConfig(self::XML_PATH_MERCHANT_COUNTRY_CODE, $store);
    }

    /**
     * Retrieve merchant VAT number
     *
     * @param Mage_Core_Model_Store|string|int|null $store
     * @return string
     */
    public function getMerchantVatNumber($store = null)
    {
        return (string) Mage::getStoreConfig(self::XML_PATH_MERCHANT_VAT_NUMBER, $store);
    }

    /**
     * Check whether specified country is in EU countries list
     *
     * @param string $countryCode
     * @param null|int $storeId
     * @return bool
     */
    public function isCountryInEU($countryCode, $storeId = null)
    {
        $euCountries = explode(',', Mage::getStoreConfig(self::XML_PATH_EU_COUNTRIES_LIST, $storeId));
        return in_array($countryCode, $euCountries);
    }

    /**
     * Returns the floating point remainder (modulo) of the division of the arguments
     *
     * @param float|int $dividend
     * @param float|int $divisor
     * @return float|int
     */
    public function getExactDivision($dividend, $divisor)
    {
        $epsilon = $divisor / self::DIVIDE_EPSILON;

        $remainder = fmod($dividend, $divisor);
        if (abs($remainder - $divisor) < $epsilon || abs($remainder) < $epsilon) {
            $remainder = 0;
        }

        return $remainder;
    }

    /**
     * Escaping CSV-data
     *
     * Security enhancement for CSV data processing by Excel-like applications.
     * @see https://bugzilla.mozilla.org/show_bug.cgi?id=1054702
     *
     * @return array
     */
    public function getEscapedCSVData(array $data)
    {
        if (Mage::getStoreConfigFlag(Mage_ImportExport_Model_Export_Adapter_Csv::CONFIG_ESCAPING_FLAG)) {
            foreach ($data as $key => $value) {
                $value = (string) $value;

                $firstLetter = substr($value, 0, 1);
                if ($firstLetter && in_array($firstLetter, ['=', '+', '-'])) {
                    $data[$key] = ' ' . $value;
                }
            }
        }
        return $data;
    }

    /**
     * UnEscapes CSV data
     *
     * @param mixed $data
     * @return mixed array
     */
    public function unEscapeCSVData($data)
    {
        if (is_array($data) && Mage::getStoreConfigFlag(Mage_ImportExport_Model_Export_Adapter_Csv::CONFIG_ESCAPING_FLAG)) {
            foreach ($data as $key => $value) {
                $value = (string) $value;

                if (preg_match("/^ [=\-+]/", $value)) {
                    $data[$key] = ltrim($value);
                }
            }
        }
        return $data;
    }

    /**
     * @deprecated since 25.5.0
     */
    public function isFormKeyEnabled(): bool
    {
        return true;
    }

    /**
     * Returns true if the rate limit of the current client is exceeded
     * @param bool $setErrorMessage Adds a predefined error message to the 'core/session' object
     * @return bool is rate limit exceeded
     */
    public function isRateLimitExceeded(bool $setErrorMessage = true, bool $recordRateLimitHit = true): bool
    {
        $active = Mage::getStoreConfigFlag('system/rate_limit/active');
        if ($active && $remoteAddr = Mage::helper('core/http')->getRemoteAddr()) {
            $cacheTag = 'rate_limit_' . $remoteAddr;
            if (Mage::app()->testCache($cacheTag)) {
                if ($setErrorMessage) {
                    $errorMessage = $this->__('Too Soon: You are trying to perform this operation too frequently. Please wait a few seconds and try again.');
                    Mage::getSingleton('core/session')->addError($errorMessage);
                }
                return true;
            }

            if ($recordRateLimitHit) {
                $this->recordRateLimitHit();
            }
        }

        return false;
    }

    /**
     * Save the client rate limit hit to the cache
     */
    public function recordRateLimitHit(): void
    {
        $active = Mage::getStoreConfigFlag('system/rate_limit/active');
        if ($active && $remoteAddr = Mage::helper('core/http')->getRemoteAddr()) {
            $cacheTag = 'rate_limit_' . $remoteAddr;
            Mage::app()->saveCache(1, $cacheTag, ['brute_force'], Mage::getStoreConfig('system/rate_limit/timeframe'));
        }
    }

    /**
     * Filter value using specified filter type
     */
    public function filter(mixed $value, string $filter, mixed ...$args): mixed
    {
        return match ($filter) {
            'email' => $this->filterEmail($value),
            'url' => $this->filterUrl($value),
            'int' => $this->filterInt($value),
            'float' => $this->filterFloat($value),
            'alnum' => $this->filterAlnum($value, $args[0] ?? false),
            'alpha' => $this->filterAlpha($value),
            'digits' => $this->filterDigits($value),
            'striptags' => $this->filterStripTags($value, $args[0] ?? null),
            default => $value,
        };
    }

    /**
     * Sanitize email address by removing invalid characters
     */
    public function filterEmail(mixed $value): string
    {
        return filter_var($value, FILTER_SANITIZE_EMAIL) ?: '';
    }

    /**
     * Sanitize URL by removing invalid characters
     */
    public function filterUrl(mixed $value): string
    {
        return filter_var($value, FILTER_SANITIZE_URL) ?: '';
    }

    /**
     * Extract integer from value, removing all non-digit characters except +/-
     */
    public function filterInt(mixed $value): int
    {
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Extract float from value, keeping digits, +/-, and decimal point
     */
    public function filterFloat(mixed $value): float
    {
        return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Keep only alphanumeric characters (a-z, A-Z, 0-9)
     */
    public function filterAlnum(mixed $value, bool $allowWhitespace = false): string
    {
        $pattern = $allowWhitespace ? '/[^a-zA-Z0-9\s]/' : '/[^a-zA-Z0-9]/';
        return preg_replace($pattern, '', (string) $value);
    }

    /**
     * Keep only alphabetic characters (a-z, A-Z)
     */
    public function filterAlpha(mixed $value): string
    {
        return preg_replace('/[^a-zA-Z]/', '', (string) $value);
    }

    /**
     * Keep only digits (0-9)
     */
    public function filterDigits(mixed $value): string
    {
        return preg_replace('/[^0-9]/', '', (string) $value);
    }

    /**
     * Remove HTML and PHP tags, optionally allowing specific tags
     */
    public function filterStripTags(mixed $value, array|string|null $allowedTags = null): string
    {
        return strip_tags((string) $value, $allowedTags);
    }


    /**
     * Check if value is a valid IP address (IPv4 or IPv6)
     */
    public function isValidIp(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    public function getMailerDsn(): string
    {
        $coreHelper = Mage::helper('core');
        $emailTransport = Mage::getStoreConfig('system/smtp/enabled');
        $user = $coreHelper->decrypt(Mage::getStoreConfig('system/smtp/username'));
        $pass = $coreHelper->decrypt(Mage::getStoreConfig('system/smtp/password'));
        $host = Mage::getStoreConfig('system/smtp/host');
        $port = Mage::getStoreConfig('system/smtp/port');
        $region = Mage::getStoreConfig('system/smtp/region');

        $dsn = match ($emailTransport) {
            'smtp' => "$emailTransport://$user:$pass@$host:$port",
            'ses+smtp' => "$emailTransport://$user:$pass@default",
            'ses+https' => "$emailTransport://$user:$pass@default",
            'ses+api' => "$emailTransport://$user:$pass@default",
            'azure+api' => "$emailTransport://$user:$pass@default",
            'brevo+smtp' => "$emailTransport://$user:$pass@default",
            'brevo+api' => "$emailTransport://$pass@default",
            'infobip+smtp' => "$emailTransport://$pass@$host",
            'infobip+api' => "$emailTransport://$pass@default",
            'mailgun+smtp' => "$emailTransport://$user:$pass@default",
            'mailgun+https' => "$emailTransport://$pass:$host@default",
            'mailgun+api' => "$emailTransport://$pass:$host@default",
            'mailjet+smtp' => "$emailTransport://$user:$pass@default",
            'mailjet+api' => "$emailTransport://$user:$pass@default",
            'mailomat+smtp' => "$emailTransport://$user:$pass@default",
            'mailomat+api' => "$emailTransport://$pass@default",
            'mailpace+api' => "$emailTransport://$pass@default",
            'mailersend+smtp' => "$emailTransport://$user:$pass@default",
            'mailersend+api' => "$emailTransport://$pass@default",
            'mailtrap+smtp' => "$emailTransport://$pass@default",
            'mailtrap+api' => "$emailTransport://$pass@default",
            'mandrill+smtp' => "$emailTransport://$user:$pass@default",
            'mandrill+https' => "$emailTransport://$pass@default",
            'mandrill+api' => "$emailTransport://$pass@default",
            'postal+api' => "$emailTransport://$pass@$host",
            'postmark+smtp' => "$emailTransport://$pass@default",
            'postmark+api' => "$emailTransport://$pass@default",
            'resend+smtp' => "$emailTransport://resend:$pass@default",
            'resend+api' => "$emailTransport://$pass@default",
            'scaleway+smtp' => "$emailTransport://$user:$pass@default",
            'scaleway+api' => "$emailTransport://$user:$pass@default",
            'sendgrid+smtp' => "$emailTransport://$pass@default",
            'sendgrid+api' => "$emailTransport://$pass@default",
            'sweego+smtp' => "$emailTransport://$user:$pass@$host:$port",
            'sweego+api' => "$emailTransport://$pass@default",
            'sendmail' => "$emailTransport://default",
            default => '',
        };

        if ($region) {
            $dsn .= "?region=$region";
        }

        return $dsn;
    }

    /**
     * Symfony validator instance
     */
    private static ?ValidatorInterface $symfonyValidator = null;

    /**
     * Get Symfony validator instance
     */
    private function getSymfonyValidator(): ValidatorInterface
    {
        if (self::$symfonyValidator === null) {
            self::$symfonyValidator = Validation::createValidator();
        }
        return self::$symfonyValidator;
    }

    /**
     * Check if email address is valid using Symfony Email constraint
     */
    public function isValidEmail(#[\SensitiveParameter] mixed $value): bool
    {
        $violations = $this->getSymfonyValidator()->validate((string) $value, new Assert\Email());
        return count($violations) === 0;
    }

    /**
     * Check if value is not blank using Symfony NotBlank constraint
     */
    public function isValidNotBlank(mixed $value): bool
    {
        $violations = $this->getSymfonyValidator()->validate($value, new Assert\NotBlank());
        return count($violations) === 0;
    }

    /**
     * Check if string matches regex pattern using Symfony Regex constraint
     */
    public function isValidRegex(string $value, string $pattern): bool
    {
        $violations = $this->getSymfonyValidator()->validate($value, new Assert\Regex(['pattern' => $pattern]));
        return count($violations) === 0;
    }

    /**
     * Check if string length is valid using Symfony Length constraint
     */
    public function isValidLength(string $value, ?int $min = null, ?int $max = null): bool
    {
        $options = [];
        if ($min !== null) {
            $options['min'] = $min;
        }
        if ($max !== null) {
            $options['max'] = $max;
        }

        $violations = $this->getSymfonyValidator()->validate($value, new Assert\Length($options));
        return count($violations) === 0;
    }

    /**
     * Check if numeric value is in valid range using Symfony Range constraint
     */
    public function isValidRange(mixed $value, int|float|null $min = null, int|float|null $max = null): bool
    {
        $options = [];
        if ($min !== null) {
            $options['min'] = $min;
        }
        if ($max !== null) {
            $options['max'] = $max;
        }

        $violations = $this->getSymfonyValidator()->validate($value, new Assert\Range($options));
        return count($violations) === 0;
    }

    /**
     * Check if URL format is valid using Symfony Url constraint
     */
    public function isValidUrl(mixed $value): bool
    {
        $violations = $this->getSymfonyValidator()->validate((string) $value, new Assert\Url());
        return count($violations) === 0;
    }


    /**
     * Check if date format is valid using Symfony Date constraint
     */
    public function isValidDate(string $date): bool
    {
        $violations = $this->getSymfonyValidator()->validate($date, new Assert\Date());
        return count($violations) === 0;
    }

    /**
     * Check if datetime format is valid using Symfony DateTime constraint
     */
    public function isValidDateTime(string $datetime): bool
    {
        $violations = $this->getSymfonyValidator()->validate($datetime, new Assert\DateTime());
        return count($violations) === 0;
    }

    /**
     * Generic validation method that returns boolean result using Symfony constraints
     */
    public function isValid(mixed $value, mixed $constraint): bool
    {
        $violations = $this->getSymfonyValidator()->validate($value, $constraint);
        return count($violations) === 0;
    }

    /**
     * Get SVG icon content
     *
     * @param string $name Icon name (e.g., 'circle-x', 'plus', etc.)
     * @param string $variant Icon variant: 'outline' or 'filled'
     * @param string $role ARIA role, see https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Reference/Roles
     * @return string SVG icon HTML or empty string if not found
     */
    public function getIconSvg(string $name, string $variant = 'outline', string $role = 'none'): string
    {
        $name = basename(strtolower($name));
        $variant = in_array($variant, ['outline', 'filled']) ? $variant : 'outline';

        $cache = Mage::app()->getCache();
        $cacheId = "MAHO_ICON_{$variant}_{$name}";
        $useCache = Mage::app()->useCache('icons');

        if ($useCache && $cachedIcon = $cache->load($cacheId)) {
            $cachedIcon = str_replace('<svg ', '<svg role="' . $role . '" ', $cachedIcon);
            return $cachedIcon;
        }

        $installPath = null;
        $packageName = 'mahocommerce/icons';
        try {
            $installPath = \Composer\InstalledVersions::getInstallPath($packageName);
        } catch (OutOfBoundsException $e) {
            return '';
        }
        if ($installPath === null) {
            return '';
        }

        $iconSvg = file_get_contents("$installPath/icons/$variant/$name.svg", false);
        if ($iconSvg === false) {
            return '';
        }

        if ($useCache) {
            $cache->save($iconSvg, $cacheId, ['ICONS']);
        }

        $iconSvg = str_replace('<svg ', '<svg role="' . $role . '" ', $iconSvg);
        return $iconSvg;
    }

    /**
     * Re-encrypt columns in a table using batched queries to avoid memory exhaustion.
     *
     * @param string[] $columns
     */
    public function recryptTable(
        string $table,
        string $primaryKey,
        array $columns,
        callable $encryptCallback,
        callable $decryptCallback,
        int $batchSize = 1000,
    ): void {
        $readConnection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $writeConnection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $lastId = 0;

        while (true) {
            $select = $readConnection->select()
                ->from($table, array_merge([$primaryKey], $columns))
                ->where("$primaryKey > ?", $lastId)
                ->order("$primaryKey ASC")
                ->limit($batchSize);

            $conditions = [];
            foreach ($columns as $column) {
                $conditions[] = "$column IS NOT NULL";
            }
            $select->where(implode(' OR ', $conditions));

            $rows = $readConnection->fetchAll($select);
            if (empty($rows)) {
                break;
            }

            foreach ($rows as $row) {
                $updateData = [];
                foreach ($columns as $column) {
                    if ($row[$column] !== null) {
                        $updateData[$column] = $encryptCallback($decryptCallback($row[$column]));
                    }
                }
                if (!empty($updateData)) {
                    $writeConnection->update(
                        $table,
                        $updateData,
                        ["$primaryKey = ?" => $row[$primaryKey]],
                    );
                }
                $lastId = $row[$primaryKey];
            }

            unset($rows);
        }
    }
}
