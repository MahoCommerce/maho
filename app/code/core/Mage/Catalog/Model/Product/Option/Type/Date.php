<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
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

        $dateValid = true;
        if ($this->_dateExists()) {
            // Check for native datetime-local input format (for datetime options)
            if ($option->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE_TIME && isset($value['datetime'])) {
                $dateTime = DateTime::createFromFormat(Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT, substr($value['datetime'], 0, 16));
                $dateValid = $dateTime !== false;
            }
            // Check for native date input format (ISO 8601)
            elseif (isset($value['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value['date'])) {
                $dateTime = DateTime::createFromFormat(Mage_Core_Model_Locale::DATE_FORMAT, $value['date']);
                $dateValid = $dateTime !== false;
            }
            // Handle case where datetime value might be passed as 'date' field
            elseif (isset($value['date']) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value['date'])) {
                $dateTime = DateTime::createFromFormat(Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT, substr($value['date'], 0, 16));
                $dateValid = $dateTime !== false;
            } else {
                $dateValid = false;
            }
        }

        $timeValid = true;
        if ($this->_timeExists()) {
            // For datetime options, time is included in the datetime field
            if ($option->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE_TIME) {
                $timeValid = true; // Already validated above
            }
            // For time-only options, check for native time input format
            elseif ($option->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_TIME && isset($value['time'])) {
                // Validate time format HH:mm
                $timeParts = explode(':', $value['time']);
                if (count($timeParts) === 2) {
                    $hour = (int) $timeParts[0];
                    $minute = (int) $timeParts[1];
                    $timeValid = $hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59;
                } else {
                    $timeValid = false;
                }
            } else {
                $timeValid = false;
            }
        }

        $isValid = $dateValid && $timeValid;

        if ($isValid) {
            $this->setUserValue(
                [
                    'date' => $value['date'] ?? '',
                    'datetime' => $value['datetime'] ?? '',
                    'time' => $value['time'] ?? '',
                    'date_internal' => $value['date_internal'] ?? '',
                ],
            );
        } elseif ($option->getIsRequired() && !$this->getSkipCheckRequiredOption()) {
            $this->setIsValid(false);
            if (!$dateValid) {
                Mage::throwException(Mage::helper('catalog')->__('Please specify date required option <em>%s</em>.', $option->getTitle()));
            } elseif (!$timeValid) {
                Mage::throwException(Mage::helper('catalog')->__('Please specify time required option <em>%s</em>.', $option->getTitle()));
            } else {
                Mage::throwException(Mage::helper('catalog')->__('Please specify the product required option <em>%s</em>.', $option->getTitle()));
            }
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

            try {
                // Check if datetime-local format from native input (for datetime options)
                if (isset($value['datetime']) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value['datetime'])) {
                    // Parse ISO datetime-local format directly
                    $dateTime = DateTime::createFromFormat(Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT, substr($value['datetime'], 0, 16));
                    $result = $dateTime->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                }
                // Check if time-only format from native input
                elseif (isset($value['time']) && preg_match('/^\d{2}:\d{2}/', $value['time'])) {
                    // For time-only options, use today's date with the specified time
                    $dateTime = new DateTime('today');
                    $timeParts = explode(':', $value['time']);
                    $dateTime->setTime((int) $timeParts[0], (int) $timeParts[1]);
                    $result = $dateTime->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                }
                // Check if datetime-local format is passed in the 'date' field
                elseif (isset($value['date']) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value['date'])) {
                    // Parse ISO datetime-local format directly
                    $dateTime = DateTime::createFromFormat(Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT, substr($value['date'], 0, 16));
                    $result = $dateTime->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                }
                // Check if date is in ISO format from native input
                elseif (isset($value['date']) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value['date'])) {
                    // Parse ISO date format (date-only option)
                    $dateTime = new DateTime($value['date']);
                    $result = $dateTime->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                } else {
                    // Invalid format - return null
                    return null;
                }

                // Save date in internal format to avoid locale date bugs
                $this->_setInternalInRequest($result);

                return $result;
            } catch (Exception $e) {
                return null;
            }
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
            if ($this->getOption()->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE) {
                $format = Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM);
                $result = Mage::app()->getLocale()->dateMutable($optionValue, DateTime::ATOM, null, false)
                    ->format($format);
            } elseif ($this->getOption()->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE_TIME) {
                $format = Mage::app()->getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
                $result = Mage::app()->getLocale()
                    ->date($optionValue, Mage_Core_Model_Locale::DATETIME_FORMAT, null, false)->format($format);
            } elseif ($this->getOption()->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_TIME) {
                $date = new DateTime($optionValue);
                $result = $date->format('H:i');
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

        $date = new DateTime('@' . $timestamp);
        return $date->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
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
            $value = unserialize($infoBuyRequest->getValue(), ['allowed_classes' => false]);
            if (is_array($value) && isset($value['options']) && isset($value['options'][$this->getOption()->getId()])) {
                return $value['options'][$this->getOption()->getId()];
            }
            return ['date_internal' => $optionValue];
        } catch (Exception $e) {
            return ['date_internal' => $optionValue];
        }
    }

    /**
     * Use Calendar on frontend or not
     * Always returns true as we only use native inputs now
     *
     * @return bool
     * @deprecated since 25.9.0
     */
    public function useCalendar()
    {
        return true;
    }

    /**
     * Time Format
     * Always returns true for 24h format as native inputs handle this
     *
     * @return bool
     * @deprecated since 25.9.0
     */
    public function is24hTimeFormat()
    {
        return true;
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
        }
        return date('Y');
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
        }
        return date('Y');
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
