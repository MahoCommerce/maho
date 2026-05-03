<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @deprecated since 26.5 Replaced by the cohesive locale date API (Mage_Core_Model_Locale + native PHP).
 *             Per-method replacements are documented on each public method below.
 */
class Mage_Core_Model_Date
{
    /**
     * Current config offset in seconds
     *
     * @var int
     */
    private $_offset = 0;

    /**
     * Init offset
     */
    public function __construct()
    {
        $this->_offset = $this->calculateOffset($this->_getConfigTimezone());
    }

    /**
     * Gets the store config timezone
     *
     * @return string
     */
    protected function _getConfigTimezone()
    {
        return Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
    }

    /**
     * Calculates timezone offset
     *
     * @param  string $timezone
     * @return int offset between timezone and gmt
     */
    public function calculateOffset($timezone = null)
    {
        $result = true;
        $offset = 0;

        if (!is_null($timezone)) {
            $oldzone = @date_default_timezone_get();
            $result = date_default_timezone_set($timezone);
        }

        if ($result === true) {
            $offset = (int) date('Z');
        }

        if (!is_null($timezone)) {
            date_default_timezone_set($oldzone);
        }

        return $offset;
    }

    /**
     * Forms GMT date
     *
     * @param  string $format
     * @param  int|string $input date in current timezone
     * @return false|string
     *
     * @deprecated since 26.5 For DB-bound strings use Mage::app()->getLocale()->formatDateForDb('now')
     *             (or formatDateForDb('now', withTime: false) for date-only). For non-DB use, use
     *             Mage::app()->getLocale()->nowUtc() / todayUtc(). When converting a store-TZ input
     *             to a UTC string, use Mage::app()->getLocale()->storeToUtc($store, $input)->format($format).
     */
    public function gmtDate($format = null, $input = null)
    {
        if (is_null($format)) {
            $format = Mage_Core_Model_Locale::DATETIME_FORMAT;
        }

        $date = $this->gmtTimestamp($input);

        if ($date === false) {
            return false;
        }

        return date($format, (int) $date);
    }

    /**
     * Converts input date into date with timezone offset
     * Input date must be in GMT timezone
     *
     * @param  string $format
     * @param  int|string $input date in GMT timezone
     * @return string
     *
     * @deprecated since 26.5 Use Mage::app()->getLocale()->utcToStore($store, $input)->format($format).
     *             For "now" in store TZ, omit the second argument: utcToStore()->format($format).
     */
    public function date($format = null, $input = null)
    {
        if (is_null($format)) {
            $format = Mage_Core_Model_Locale::DATETIME_FORMAT;
        }

        return date($format, $this->timestamp($input));
    }

    /**
     * Forms GMT timestamp
     *
     * @param  int|string $input date in current timezone
     * @return string|false|int
     *
     * @deprecated since 26.5 For "now" use the native time() — Unix epochs are timezone-agnostic.
     *             For converting a store-TZ input to a UTC epoch, use
     *             Mage::app()->getLocale()->storeToUtc($store, $input)->getTimestamp().
     */
    public function gmtTimestamp($input = null)
    {
        if (is_null($input)) {
            return gmdate('U');
        }
        if (is_numeric($input)) {
            $result = $input;
        } else {
            $result = strtotime($input);
        }

        if ($result === false) {
            // strtotime() unable to parse string (it's not a date or has incorrect format)
            return false;
        }

        $date = new DateTime('@' . $result);
        $date->setTimezone(
            new DateTimeZone(
                Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE),
            ),
        );
        $timestamp = $date->getTimestamp() - $date->getOffset();

        unset($date);
        return $timestamp;
    }

    /**
     * Converts input date into timestamp with timezone offset
     * Input date must be in GMT timezone
     *
     * @param  int|string $input date in GMT timezone
     * @return int
     *
     * @deprecated since 26.5 The "store-shifted timestamp" this method returns is not a real epoch
     *             and was used by legacy code as a stepping stone to extract store-local components.
     *             Prefer Mage::app()->getLocale()->utcToStore($store, $input) and call ->format(...) /
     *             ->getTimestamp() on the result. For "now" use time().
     */
    public function timestamp($input = null)
    {
        if (is_null($input)) {
            $result = $this->gmtTimestamp();
        } elseif (is_numeric($input)) {
            $result = $input;
        } else {
            $result = strtotime($input);
        }

        $date = new DateTime('@' . $result);
        $date->setTimezone(
            new DateTimeZone(
                Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE),
            ),
        );
        $timestamp = $date->getTimestamp() + $date->getOffset();

        unset($date);
        return $timestamp;
    }

    /**
     * Get current timezone offset in seconds/minutes/hours
     *
     * @param  string $type
     * @return int
     *
     * @deprecated since 26.5 Use a DateTimeZone directly:
     *             (new DateTimeZone($store->getConfig('general/locale/timezone')))->getOffset(new DateTimeImmutable())
     */
    public function getGmtOffset($type = 'seconds')
    {
        $result = $this->_offset;
        switch ($type) {
            case 'seconds':
            default:
                break;

            case 'minutes':
                $result = $result / 60;
                break;

            case 'hours':
                $result = $result / 60 / 60;
                break;
        }
        return $result;
    }
}
