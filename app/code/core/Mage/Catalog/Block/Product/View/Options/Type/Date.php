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

    public function getHtml(): string
    {
        $option = $this->getOption();

        if ($this->useCalendar()) {
            return $option->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_TIME
                ? $this->getCalendarTimeHtml()
                : $this->getCalendarDateHtml();
        }

        $html = '';
        if ($option->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE_TIME || $option->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE) {
            $html .= $this->getDropDownsDateHtml();
        }
        if ($option->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE_TIME || $option->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_TIME) {
            $html .= $this->getDropDownsTimeHtml();
        }
        return $html;
    }

    /**
     * Date input
     *
     * @return string Formatted Html
     */
    public function getDateHtml()
    {
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
        $value = $this->getProduct()->getPreconfiguredValues()->getData("options/{$option->getId()}/date");

        $yearStart = Mage::getSingleton('catalog/product_option_type_date')->getYearStart();
        $yearEnd = Mage::getSingleton('catalog/product_option_type_date')->getYearEnd();

        $calendar = $this->getLayout()
            ->createBlock('core/html_date')
            ->setId("options_{$option->getId()}_date")
            ->setName("options[{$option->getId()}][date]")
            ->setClass('product-custom-option datetime-picker input-text')
            ->setFormat(Mage::app()->getLocale()->getDateStrFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT))
            ->setValue($value)
            ->setYearsRange("[$yearStart , $yearEnd]")
            ->setConfig([
                'enableTime' => $option->getType() == Mage_Catalog_Model_Product_Option::OPTION_TYPE_DATE_TIME,
                'time_24hr' => Mage::getSingleton('catalog/product_option_type_date')->is24hTimeFormat(),
            ]);
        if (!$this->getSkipJsReloadPrice()) {
            $calendar->setExtraParams('onchange="opConfig.reloadPrice()"');
        }

        return $calendar->getHtml();
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
     * Time input
     *
     * @return string Formatted Html
     */
    public function getTimeHtml()
    {
        if ($this->useCalendar()) {
            return $this->getCalendarTimeHtml();
        } else {
            return $this->getDropDownsTimeHtml();
        }
    }

    /**
     * JS Calendar (time only) html
     */
    public function getCalendarTimeHtml(): string
    {
        $option = $this->getOption();
        $calendar = $this->getLayout()
            ->createBlock('core/html_date')
            ->setId("options_{$option->getId()}_time")
            ->setName("options[{$option->getId()}][time]")
            ->setClass('product-custom-option datetime-picker input-text')
            ->setConfig([
                'noCalendar' => true,
                'enableTime' => true,
            ]);

        if (Mage::getSingleton('catalog/product_option_type_date')->is24hTimeFormat()) {
            $calendar->setFormat(Mage::app()->getLocale()->getTimeFormat(Mage_Core_Model_Locale::FORMAT_TIME_24H))
                ->setConfig('time_24h', true);
        } else {
            $calendar->setFormat(Mage::app()->getLocale()->getTimeFormat(Mage_Core_Model_Locale::FORMAT_TIME_12H));
        }

        if (!$this->getSkipJsReloadPrice()) {
            $calendar->setExtraParams('onchange="opConfig.reloadPrice()"');
        }

        return $calendar->getHtml();
    }

    /**
     * Time (hh:mm am/pm) html drop-downs
     */
    public function getDropDownsTimeHtml(): string
    {
        if (Mage::getSingleton('catalog/product_option_type_date')->is24hTimeFormat()) {
            $hourStart = 0;
            $hourEnd = 23;
            $dayPartHtml = '';
        } else {
            $hourStart = 1;
            $hourEnd = 12;
            $dayPartHtml = $this->_getHtmlSelect('day_part')
                ->setOptions([
                    'am' => Mage::helper('catalog')->__('AM'),
                    'pm' => Mage::helper('catalog')->__('PM'),
                ])
                ->getHtml();
        }
        $hoursHtml = $this->_getSelectFromToHtml('hour', $hourStart, $hourEnd);
        $minutesHtml = $this->_getSelectFromToHtml('minute', 0, 59);

        return $hoursHtml . '&nbsp;<b>:</b>&nbsp;' . $minutesHtml . '&nbsp;' . $dayPartHtml;
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
        $select = $this->getLayout()->createBlock('core/html_select')
            ->setId("options_{$option->getId()}_{$name}")
            ->setClass('product-custom-option datetime-picker')
            ->setExtraParams()
            ->setName("options[{$option->getId()}][{$name}]");

        $extraParams = 'style="width:auto"';
        if (!$this->getSkipJsReloadPrice()) {
            $extraParams .= ' onchange="opConfig.reloadPrice()"';
        }
        $select->setExtraParams($extraParams);

        if (is_null($value)) {
            $value = $this->getProduct()->getPreconfiguredValues()->getData("options/{$option->getId()}/{$name}");
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
            return (string) $value;
        }
        return str_pad((string) $value, 2, '0', STR_PAD_LEFT);
    }
}
