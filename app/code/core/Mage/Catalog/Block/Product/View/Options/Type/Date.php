<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method bool getSkipJsReloadPrice()
 */
class Mage_Catalog_Block_Product_View_Options_Type_Date extends Mage_Catalog_Block_Product_View_Options_Abstract
{
    /**
     * Fill date and time options with leading zeros or not
     *
     * @var bool
     */
    protected $_fillLeadingZeros = true;

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
     */
    public function useCalendar()
    {
        return Mage::getSingleton('catalog/product_option_type_date')->useCalendar();
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

        // For date-only options, use date input
        if ($this->useCalendar()) {
            return $this->getCalendarDateHtml();
        } else {
            return $this->getDropDownsDateHtml();
        }
    }

    /**
     * JS Calendar html
     *
     * @return string Formatted Html
     */
    public function getCalendarDateHtml()
    {
        $option = $this->getOption();
        $value = $this->getProduct()->getPreconfiguredValues()->getData('options/' . $option->getId() . '/date');

        $yearStart = Mage::getSingleton('catalog/product_option_type_date')->getYearStart();
        $yearEnd = Mage::getSingleton('catalog/product_option_type_date')->getYearEnd();

        // Convert value to ISO format if needed
        $isoValue = '';
        if ($value) {
            try {
                $dateTime = new DateTime($value);
                $isoValue = $dateTime->format('Y-m-d');
            } catch (Exception $e) {
                $isoValue = $value;
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
     * Date (dd/mm/yyyy) html drop-downs
     *
     * @return string Formatted Html
     */
    public function getDropDownsDateHtml()
    {
        $fieldsSeparator = '&nbsp;';
        $fieldsOrder = Mage::getSingleton('catalog/product_option_type_date')->getConfigData('date_fields_order');
        $fieldsOrder = str_replace(',', $fieldsSeparator, $fieldsOrder);

        $monthsHtml = $this->_getSelectFromToHtml('month', 1, 12);
        $daysHtml = $this->_getSelectFromToHtml('day', 1, 31);

        $yearStart = Mage::getSingleton('catalog/product_option_type_date')->getYearStart();
        $yearEnd = Mage::getSingleton('catalog/product_option_type_date')->getYearEnd();
        $yearsHtml = $this->_getSelectFromToHtml('year', $yearStart, $yearEnd);

        $translations = [
            'd' => $daysHtml,
            'm' => $monthsHtml,
            'y' => $yearsHtml,
        ];
        return strtr($fieldsOrder, $translations);
    }

    /**
     * Time input - now uses native time input
     *
     * @return string Formatted Html
     */
    public function getTimeHtml()
    {
        $option = $this->getOption();
        $value = $this->getProduct()->getPreconfiguredValues()->getData('options/' . $option->getId() . '/time');

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
     * Return drop-down html with range of values
     *
     * @param string $name      Id/name of html select element
     * @param string|int $from  Start position
     * @param string|int $to    End position
     * @param string $value     Value selected
     * @return string           Formatted Html
     */
    protected function _getSelectFromToHtml($name, $from, $to, $value = null)
    {
        $options = [
            ['value' => '', 'label' => '-'],
        ];
        for ($i = $from; $i <= $to; $i++) {
            $options[] = ['value' => $i, 'label' => $this->_getValueWithLeadingZeros($i)];
        }
        return $this->_getHtmlSelect($name, $value)
            ->setOptions($options)
            ->getHtml();
    }

    /**
     * HTML select element
     *
     * @param string $name Id/name of html select element
     * @param string|null $value
     * @return Mage_Core_Block_Html_Select
     */
    protected function _getHtmlSelect($name, $value = null)
    {
        $option = $this->getOption();

        $require = '';
        $select = $this->getLayout()->createBlock('core/html_select')
            ->setId('options_' . $this->getOption()->getId() . '_' . $name)
            ->setClass('product-custom-option datetime-picker' . $require)
            ->setExtraParams()
            ->setName('options[' . $option->getId() . '][' . $name . ']');

        $extraParams = 'style="width:auto"';
        if (!$this->getSkipJsReloadPrice()) {
            $extraParams .= ' onchange="opConfig.reloadPrice()"';
        }
        $select->setExtraParams($extraParams);

        if (is_null($value)) {
            $value = $this->getProduct()->getPreconfiguredValues()->getData('options/' . $option->getId() . '/' . $name);
        }
        if (!is_null($value)) {
            $select->setValue($value);
        }

        return $select;
    }

    /**
     * Add Leading Zeros to number less than 10
     *
     * @param int $value
     * @return int|string
     */
    protected function _getValueWithLeadingZeros($value)
    {
        if (!$this->_fillLeadingZeros) {
            return $value;
        }
        return $value < 10 ? '0' . $value : $value;
    }

    /**
     * DateTime-local input for combined date and time
     *
     * @return string Formatted Html
     */
    public function getDateTimeLocalHtml()
    {
        $option = $this->getOption();
        $dateValue = $this->getProduct()->getPreconfiguredValues()->getData('options/' . $option->getId() . '/date');
        $timeValue = $this->getProduct()->getPreconfiguredValues()->getData('options/' . $option->getId() . '/time');

        $yearStart = Mage::getSingleton('catalog/product_option_type_date')->getYearStart();
        $yearEnd = Mage::getSingleton('catalog/product_option_type_date')->getYearEnd();

        // Convert values to ISO datetime-local format (YYYY-MM-DDTHH:mm)
        $isoValue = '';
        if ($dateValue || $timeValue) {
            try {
                $dateTime = new DateTime();

                // Set date part
                if ($dateValue) {
                    $dateTime = new DateTime($dateValue);
                } else {
                    $dateTime = new DateTime('today');
                }

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

                $isoValue = $dateTime->format('Y-m-d\TH:i');
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
