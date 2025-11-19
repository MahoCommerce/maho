<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method bool getSkipJsReloadPrice()
 */
class Mage_Catalog_Block_Product_View_Options_Type_Date extends Mage_Catalog_Block_Product_View_Options_Abstract
{
    #[\Override]
    protected function _prepareLayout()
    {
        if ($head = $this->getLayout()->getBlock('head')) {
            $head->setCanLoadCalendarJs(true);
        }
        return parent::_prepareLayout();
    }

    /**
     * Use JS calendar settings
     *
     * @return bool
     * @deprecated since 25.9.0
     */
    public function useCalendar()
    {
        return true; // Always use native date inputs
    }

    /**
     * Date input
     *
     * @return string Formatted Html
     */
    public function getDateHtml()
    {
        $option = $this->getOption();

        // For datetime options, use datetime-local input
        if ($option->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE_TIME) {
            return $this->getDateTimeLocalHtml();
        }

        return $this->getCalendarDateHtml();
    }

    /**
     * JS Calendar html
     *
     * @return string Formatted Html
     */
    public function getCalendarDateHtml()
    {
        $option = $this->getOption();
        $preconfiguredValues = $this->getProduct()->getPreconfiguredValues();
        $optionValues = $preconfiguredValues->getData('options/' . $option->getId());

        $yearStart = Mage::getSingleton('catalog/product_option_type_date')->getYearStart();
        $yearEnd = Mage::getSingleton('catalog/product_option_type_date')->getYearEnd();

        // Extract date value from different possible formats
        $dateValue = null;
        if (is_array($optionValues)) {
            $dateValue = $optionValues['date'] ?? $optionValues['date_internal'] ?? null;
        } elseif (is_string($optionValues)) {
            $dateValue = $optionValues;
        }

        // Convert value to ISO format if needed
        $isoValue = '';
        if ($dateValue) {
            try {
                $dateTime = new DateTime($dateValue);
                $isoValue = $dateTime->format(Mage_Core_Model_Locale::DATE_FORMAT);
            } catch (Exception $e) {
                $isoValue = is_string($dateValue) ? $dateValue : '';
            }
        }

        $html = '<input type="date" '
            . 'id="options_' . $this->getOption()->getId() . '_date" '
            . 'name="options[' . $this->getOption()->getId() . '][date]" '
            . 'class="product-custom-option datetime-picker input-text" '
            . 'value="' . $this->escapeHtml($isoValue) . '" ';

        // Add min/max attributes for year range
        if ($yearStart) {
            $html .= 'min="' . $yearStart . '-01-01" ';
        }
        if ($yearEnd) {
            $html .= 'max="' . $yearEnd . '-12-31" ';
        }

        if (!$this->getSkipJsReloadPrice()) {
            $html .= 'onchange="opConfig.reloadPrice()" ';
        }

        $html .= '/>';

        return $html;
    }


    /**
     * Time input - now uses native time input
     *
     * @return string Formatted Html
     */
    public function getTimeHtml()
    {
        $option = $this->getOption();
        $preconfiguredValues = $this->getProduct()->getPreconfiguredValues();
        $optionValues = $preconfiguredValues->getData('options/' . $option->getId());

        // Extract time value from different possible formats
        $value = null;
        if (is_array($optionValues)) {
            $value = $optionValues['time'] ?? null;
        }

        // Convert value to HH:mm format if needed
        $timeValue = '';
        if ($value) {
            try {
                // Handle various time formats
                if (is_array($value) && isset($value['hour']) && isset($value['minute'])) {
                    $hour = (int) $value['hour'];
                    $minute = (int) $value['minute'];

                    // Handle 12-hour format conversion
                    if (isset($value['day_part']) && $value['day_part'] === 'pm' && $hour < 12) {
                        $hour += 12;
                    } elseif (isset($value['day_part']) && $value['day_part'] === 'am' && $hour === 12) {
                        $hour = 0;
                    }

                    $timeValue = sprintf('%02d:%02d', $hour, $minute);
                } else {
                    $timeValue = $value;
                }
            } catch (Exception $e) {
                $timeValue = $value;
            }
        }

        $html = '<input type="time" '
            . 'id="options_' . $this->getOption()->getId() . '_time" '
            . 'name="options[' . $this->getOption()->getId() . '][time]" '
            . 'class="product-custom-option datetime-picker input-text" '
            . 'value="' . $this->escapeHtml($timeValue) . '" ';

        if (!$this->getSkipJsReloadPrice()) {
            $html .= 'onchange="opConfig.reloadPrice()" ';
        }

        $html .= '/>';

        return $html;
    }




    /**
     * DateTime-local input for combined date and time
     *
     * @return string Formatted Html
     */
    public function getDateTimeLocalHtml()
    {
        $option = $this->getOption();
        $preconfiguredValues = $this->getProduct()->getPreconfiguredValues();

        // Get the option value structure from preconfigured values
        $optionValues = $preconfiguredValues->getData('options/' . $option->getId());

        // Extract datetime value from different possible formats
        $datetimeValue = null;
        $dateValue = null;
        $timeValue = null;

        if (is_array($optionValues)) {
            // Check for datetime-local format first (native datetime-local input)
            if (isset($optionValues['datetime'])) {
                $datetimeValue = $optionValues['datetime'];
            }
            // Check for date_internal (fallback format)
            elseif (isset($optionValues['date_internal'])) {
                $datetimeValue = $optionValues['date_internal'];
            }
            // Check for separate date and time values
            else {
                $dateValue = $optionValues['date'] ?? null;
                $timeValue = $optionValues['time'] ?? null;
            }
        } elseif (is_string($optionValues)) {
            // Direct string value
            $datetimeValue = $optionValues;
        }

        $yearStart = Mage::getSingleton('catalog/product_option_type_date')->getYearStart();
        $yearEnd = Mage::getSingleton('catalog/product_option_type_date')->getYearEnd();

        // Convert values to ISO datetime-local format (YYYY-MM-DDTHH:mm)
        $isoValue = '';
        if ($datetimeValue) {
            // Handle single datetime value
            try {
                $dateTime = new DateTime($datetimeValue);
                $isoValue = $dateTime->format(Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT);
            } catch (Exception $e) {
                $isoValue = '';
            }
        } elseif ($dateValue || $timeValue) {
            // Handle separate date and time values
            try {
                // Set date part
                $dateTime = $dateValue ? new DateTime($dateValue) : new DateTime('today');

                // Set time part
                if (is_array($timeValue) && isset($timeValue['hour']) && isset($timeValue['minute'])) {
                    $hour = (int) $timeValue['hour'];
                    $minute = (int) $timeValue['minute'];

                    // Handle 12-hour format conversion
                    if (isset($timeValue['day_part']) && $timeValue['day_part'] === 'pm' && $hour < 12) {
                        $hour += 12;
                    } elseif (isset($timeValue['day_part']) && $timeValue['day_part'] === 'am' && $hour === 12) {
                        $hour = 0;
                    }

                    $dateTime->setTime($hour, $minute);
                } elseif ($timeValue) {
                    // Parse time string
                    $timeParts = explode(':', $timeValue);
                    if (count($timeParts) >= 2) {
                        $dateTime->setTime((int) $timeParts[0], (int) $timeParts[1]);
                    }
                }

                $isoValue = $dateTime->format(Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT);
            } catch (Exception $e) {
                $isoValue = '';
            }
        }

        $html = '<input type="datetime-local" '
            . 'id="options_' . $this->getOption()->getId() . '_datetime" '
            . 'name="options[' . $this->getOption()->getId() . '][datetime]" '
            . 'class="product-custom-option datetime-picker input-text" '
            . 'value="' . $this->escapeHtml($isoValue) . '" ';

        // Add min/max attributes for year range
        if ($yearStart) {
            $html .= 'min="' . $yearStart . '-01-01T00:00" ';
        }
        if ($yearEnd) {
            $html .= 'max="' . $yearEnd . '-12-31T23:59" ';
        }

        if (!$this->getSkipJsReloadPrice()) {
            $html .= 'onchange="opConfig.reloadPrice()" ';
        }

        $html .= '/>';

        return $html;
    }
}
