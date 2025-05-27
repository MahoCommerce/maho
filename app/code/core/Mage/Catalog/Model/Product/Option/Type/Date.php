<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method array|null getUserValue()
 * @method $this setUserValue(array|null $userValue)
 */
class Mage_Catalog_Model_Product_Option_Type_Date extends Mage_Catalog_Model_Product_Option_Type_Default
{
    /**
     * Validate user input for option
     *
     * @throws Mage_Core_Exception
     * @param array $values All product option values, i.e. array (option_id => mixed, option_id => mixed...)
     * @return Mage_Catalog_Model_Product_Option_Type_Default
     */
    #[\Override]
    public function validateUserValue($values)
    {
        parent::validateUserValue($values);

        $option = $this->getOption();
        $value = $this->getUserValue();

        $isValid = $dateValid = $timeValid = true;

        $pattern = null;
        $matches = [];

        if (isset($value['date']) && $this->useCalendar()) {
            $pattern = $this->_timeExists()
                ? '/^(\d{2})\/(\d{2})\/(\d{4}) (\d{2}):(\d{2})$/'
                : '/^(\d{2})\/(\d{2})\/(\d{4})$/';
            $isValid = (bool) preg_match($pattern, $value['date'] ?? '', $matches);

            if ($isValid) {
                $value['day']      = $matches[1] ?? null;
                $value['month']    = $matches[2] ?? null;
                $value['year']     = $matches[3] ?? null;
                $value['hour']     = $matches[4] ?? null;
                $value['minute']   = $matches[5] ?? null;
            } else {
                $isValid = $dateValid = false;
            }
        } elseif (isset($value['time']) && $this->useCalendar()) {
            $pattern = '/^(\d{2}):(\d{2})$/';
            $isValid = (bool) preg_match($pattern, $value['time'] ?? '', $matches);

            if ($isValid) {
                $value['hour']     = $matches[1] ?? null;
                $value['minute']   = $matches[2] ?? null;
            } else {
                $isValid = $timeValid = false;
            }
        } else {
            if ($this->_dateExists()) {
                if (($value['day'] ?? 0) <= 0 || ($value['month'] ?? 0) <= 0 || ($value['year'] ?? 0) <= 0) {
                    $isValid = $dateValid = false;
                }
            }
            if ($this->_timeExists()) {
                if (!is_numeric($value['hour'] ?? '') || !is_numeric($value['minute'] ?? '')) {
                    $isValid = $timeValid = false;
                }
            }
        }

        if ($isValid) {
            $this->setUserValue([
                'year' => (int) ($value['year'] ?? 0),
                'month' => (int) ($value['month'] ?? 0),
                'day' => (int) ($value['day'] ?? 0),
                'hour' => (int) ($value['hour'] ?? 0),
                'minute' => (int) ($value['minute'] ?? 0),
                'day_part' => $value['day_part'] ?? '',
                'date_internal' => $value['date_internal'] ?? '',
            ]);
        } elseif ($option->getIsRequired() && !$this->getSkipCheckRequiredOption()) {
            $this->setIsValid(false);
            if (!$dateValid) {
                $message = 'Please specify date required option <em>%s</em>.';
            } elseif (!$timeValid) {
                $message = 'Please specify time required option <em>%s</em>.';
            } else {
                $message = 'Please specify the product required option <em>%s</em>.';
            }
            Mage::throwException(Mage::helper('catalog')->__($message, $option->getTitle()));
        } else {
            $this->setUserValue(null);
            return $this;
        }

        return $this;
    }

    /**
     * Prepare option value for cart
     *
     * @throws Mage_Core_Exception
     * @return ?string Prepared option value
     */
    #[\Override]
    public function prepareForCart()
    {
        $option = $this->getOption();
        $value = $this->getUserValue();

        if ($this->getIsValid() && $option !== null && $value !== null) {
            if (isset($value['date_internal']) && $value['date_internal'] != '') {
                $this->_setInternalInRequest($value['date_internal']);
                return $value['date_internal'];
            }

            $timestamp = 0;

            if ($this->_dateExists()) {
                $timestamp += mktime(0, 0, 0, $value['month'], $value['day'], $value['year']);
            } else {
                $timestamp += mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y'));
            }

            if ($this->_timeExists()) {
                // 24hr hour conversion
                if (!$this->is24hTimeFormat() && !$this->useCalendar()) {
                    $pmDayPart = (strtolower($value['day_part']) == 'pm');
                    if ($value['hour'] == 12) {
                        $value['hour'] = $pmDayPart ? 12 : 0;
                    } elseif ($pmDayPart) {
                        $value['hour'] += 12;
                    }
                }

                $timestamp += 60 * 60 * $value['hour'] + 60 * $value['minute'];
            }

            $date = new Zend_Date($timestamp);
            $result = $date->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);

            // Save date in internal format to avoid locale date bugs
            $this->_setInternalInRequest($result);

            return $result;
        } else {
            return null;
        }
    }

    /**
     * Return formatted option value for quote option
     *
     * @param string $optionValue Prepared for cart option value
     * @return string
     */
    #[\Override]
    public function getFormattedOptionValue($optionValue)
    {
        if ($this->_formattedOptionValue === null) {
            $option = $this->getOption();
            $locale = Mage::app()->getLocale();
            $timeType = $this->is24hTimeFormat() ? $locale::FORMAT_TIME_24H : $locale::FORMAT_TIME_12H;
            if ($option->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE) {
                $format = $locale->getDateFormat($locale::FORMAT_TYPE_MEDIUM);
                $result = $locale->date($optionValue, Zend_Date::ISO_8601, null, false)
                    ->toString($format);
            } elseif ($option->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE_TIME) {
                $format = $locale->getDateTimeFormat($locale::FORMAT_TYPE_SHORT, $timeType);
                $result = $locale->date($optionValue, Varien_Date::DATETIME_INTERNAL_FORMAT, null, false)
                    ->toString($format);
            } elseif ($option->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_TIME) {
                $format = $locale->getTimeFormat($timeType);
                $result = $locale->date($optionValue, Varien_Date::DATETIME_INTERNAL_FORMAT, null, false)
                    ->toString($format);
            } else {
                $result = $optionValue;
            }
            $this->_formattedOptionValue = $result;
        }
        return $this->_formattedOptionValue;
    }

    /**
     * Return printable option value
     *
     * @param string $optionValue Prepared for cart option value
     * @return string
     */
    #[\Override]
    public function getPrintableOptionValue($optionValue)
    {
        return $this->getFormattedOptionValue($optionValue);
    }

    /**
     * Return formatted option value ready to edit, ready to parse
     *
     * @param string $optionValue Prepared for cart option value
     * @return string
     */
    #[\Override]
    public function getEditableOptionValue($optionValue)
    {
        return $this->getFormattedOptionValue($optionValue);
    }

    /**
     * Parse user input value and return cart prepared value
     *
     * @param string $optionValue
     * @param array $productOptionValues Values for product option
     * @return string|null
     */
    #[\Override]
    public function parseOptionValue($optionValue, $productOptionValues)
    {
        $timestamp = strtotime($optionValue);
        if ($timestamp === false || $timestamp == -1) {
            return null;
        }

        $date = new Zend_Date($timestamp);
        return $date->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);
    }

    /**
     * Prepare option value for info buy request
     *
     * @param string $optionValue
     * @return mixed
     */
    #[\Override]
    public function prepareOptionValueForRequest($optionValue)
    {
        $confItem = $this->getConfigurationItem();
        $infoBuyRequest = $confItem->getOptionByCode('info_buyRequest');
        try {
            $value = unserialize($infoBuyRequest->getValue());
            if (is_array($value) && isset($value['options']) && isset($value['options'][$this->getOption()->getId()])) {
                return $value['options'][$this->getOption()->getId()];
            } else {
                return ['date_internal' => $optionValue];
            }
        } catch (Exception $e) {
            return ['date_internal' => $optionValue];
        }
    }

    /**
     * Use Calendar on frontend or not
     *
     * @return bool
     */
    public function useCalendar()
    {
        return (bool) $this->getConfigData('use_calendar');
    }

    /**
     * Time Format
     *
     * @return bool
     */
    public function is24hTimeFormat()
    {
        return (bool) ($this->getConfigData('time_format') == '24h');
    }

    /**
     * Year range start
     *
     * @return mixed
     */
    public function getYearStart()
    {
        $_range = explode(',', $this->getConfigData('year_range'));
        if (isset($_range[0]) && !empty($_range[0])) {
            return $_range[0];
        } else {
            return date('Y');
        }
    }

    /**
     * Year range end
     *
     * @return mixed
     */
    public function getYearEnd()
    {
        $_range = explode(',', $this->getConfigData('year_range'));
        if (isset($_range[1]) && !empty($_range[1])) {
            return $_range[1];
        } else {
            return date('Y');
        }
    }

    /**
     * Save internal value of option in infoBuy_request
     *
     * @param string $internalValue Datetime value in internal format
     * @throws Mage_Core_Exception
     */
    protected function _setInternalInRequest($internalValue)
    {
        $requestOptions = $this->getRequest()->getOptions();
        if (!isset($requestOptions[$this->getOption()->getId()])) {
            $requestOptions[$this->getOption()->getId()] = [];
        }
        $requestOptions[$this->getOption()->getId()]['date_internal'] = $internalValue;
        $this->getRequest()->setOptions($requestOptions);
    }

    /**
     * Does option have date?
     *
     * @return bool
     */
    protected function _dateExists()
    {
        return in_array($this->getOption()->getType(), [
            Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE,
            Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE_TIME,
        ]);
    }

    /**
     * Does option have time?
     *
     * @return bool
     */
    protected function _timeExists()
    {
        return in_array($this->getOption()->getType(), [
            Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE_TIME,
            Mage_Catalog_Model_Product_Option::OPTION_TYPE_TIME,
        ]);
    }
}
