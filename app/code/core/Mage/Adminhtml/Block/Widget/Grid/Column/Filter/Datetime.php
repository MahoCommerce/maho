<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Datetime extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Date
{
    #[\Override]
    public function getValue($index = null)
    {
        if ($index) {
            if ($data = $this->getData('value', 'orig_' . $index)) {
                return $data;//date('Y-m-d', strtotime($data));
            }
            return null;
        }
        $value = $this->getData('value');
        if (is_array($value)) {
            $value['datetime'] = true;
        }
        if (!empty($value['to']) && !$this->getColumn()->getFilterTime()) {
            try {
                $dateTime = new DateTime($value['to']);

                // Set timezone to store timezone
                $storeTimezone = Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
                $dateTime->setTimezone(new DateTimeZone($storeTimezone));

                // Set to end of day (23:59:59) - DST-safe
                $dateTime->setTime(23, 59, 59);

                // Convert to UTC
                $dateTime->setTimezone(new DateTimeZone(Mage_Core_Model_Locale::DEFAULT_TIMEZONE));

                // Update the value with the processed date string
                $value['to'] = $dateTime->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
            } catch (Exception $e) {
            }
        }
        return $value;
    }

    /**
     * Convert given date to default (UTC) timezone
     *
     * @param string $date
     * @param string $locale
     * @return string|null
     */
    #[\Override]
    protected function _convertDate($date, $locale)
    {
        if ($this->getColumn()->getFilterTime()) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $date)) {
                // Validate that the datetime is actually valid (not just format)
                $dateTime = DateTime::createFromFormat(Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT, substr($date, 0, 16));
                if ($dateTime && $dateTime->format(Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT) === substr($date, 0, 16)) {
                    return Mage::app()->getLocale()->utcDate(null, $date, true, 'html5');
                }
            }
        }

        return parent::_convertDate($date, $locale);
    }

    /**
     * Render filter html
     *
     * @return string
     */
    #[\Override]
    public function getHtml()
    {
        $fromLabel = Mage::helper('adminhtml')->__('From');
        $toLabel = Mage::helper('adminhtml')->__('To');
        $htmlId = $this->_getHtmlId() . time();

        // Determine input type based on whether time is needed
        $inputType = $this->getColumn()->getFilterTime() ? 'datetime-local' : 'date';

        // Convert values to ISO format for native inputs
        $fromValue = '';
        $toValue = '';
        $isDateOnly = !$this->getColumn()->getFilterTime();

        if ($fromDate = $this->getValue('from')) {
            $fromValue = Mage::app()->getLocale()->storeDate(null, $fromDate, !$isDateOnly, 'html5') ?? '';
        }

        if ($toDate = $this->getValue('to')) {
            $toValue = Mage::app()->getLocale()->storeDate(null, $toDate, !$isDateOnly, 'html5') ?? '';
        }

        $html = '<div class="range"><div class="range-line date">'
            . '<span class="label">' . $fromLabel . '</span>'
            . '<input type="' . $inputType . '" name="' . $this->_getHtmlName() . '[from]" id="' . $htmlId . '_from"'
                . ' placeholder="' . $fromLabel . '"'
                . ' value="' . $this->escapeHtml($fromValue) . '" class="input-text no-changes"/>'
            . '</div>';
        $html .= '<div class="range-line date">'
            . '<span class="label">' . $toLabel . '</span>'
            . '<input type="' . $inputType . '" name="' . $this->_getHtmlName() . '[to]" id="' . $htmlId . '_to"'
                . ' placeholder="' . $toLabel . '"'
                . ' value="' . $this->escapeHtml($toValue) . '" class="input-text no-changes"/>'
            . '</div></div>';
        $html .= '<input type="hidden" name="' . $this->_getHtmlName() . '[locale]"'
            . ' value="' . $this->getLocale()->getLocaleCode() . '"/>';
        return $html;
    }

    /**
     * Return escaped value for calendar
     *
     * @param string $index
     * @return string
     */
    #[\Override]
    public function getEscapedValue($index = null)
    {
        return $this->escapeHtml($this->getValue($index));
    }
}
