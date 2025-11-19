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

class Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Date extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Abstract
{
    protected $_locale;

    #[\Override]
    protected function _prepareLayout()
    {
        if ($head = $this->getLayout()->getBlock('head')) {
            $head->setCanLoadCalendarJs(true);
        }
        return parent::_prepareLayout();
    }

    /**
     * @return string
     * @throws Exception
     */
    #[\Override]
    public function getHtml()
    {
        $fromLabel = Mage::helper('adminhtml')->__('From');
        $toLabel = Mage::helper('adminhtml')->__('To');
        $htmlId = $this->_getHtmlId() . time();

        // Convert values to ISO format for native date input
        $fromValue = '';
        $toValue = '';

        if ($fromDate = $this->getValue('from')) {
            $fromValue = Mage::app()->getLocale()->storeDate(null, $fromDate, false, 'html5') ?? '';
        }

        if ($toDate = $this->getValue('to')) {
            $toValue = Mage::app()->getLocale()->storeDate(null, $toDate, false, 'html5') ?? '';
        }

        $html = '<div class="range"><div class="range-line date">'
            . '<span class="label">' . $fromLabel . '</span>'
            . '<input type="date" name="' . $this->_getHtmlName() . '[from]" id="' . $htmlId . '_from"'
                . ' placeholder="' . $fromLabel . '"'
                . ' value="' . $this->escapeHtml($fromValue) . '" class="input-text no-changes"/>'
            . '</div>';
        $html .= '<div class="range-line date">'
            . '<span class="label">' . $toLabel . '</span>'
            . '<input type="date" name="' . $this->_getHtmlName() . '[to]" id="' . $htmlId . '_to"'
                . ' placeholder="' . $toLabel . '"'
                . ' value="' . $this->escapeHtml($toValue) . '" class="input-text no-changes"/>'
            . '</div></div>';
        $html .= '<input type="hidden" name="' . $this->_getHtmlName() . '[locale]"'
            . 'value="' . $this->getLocale()->getLocaleCode() . '"/>';
        return $html;
    }

    #[\Override]
    public function getEscapedValue($index = null)
    {
        return $this->getValue($index);
    }

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
            $value['date'] = true;
        }
        return $value;
    }

    #[\Override]
    public function getCondition()
    {
        return $this->getValue();
    }

    public function setValue($value)
    {
        if (isset($value['locale'])) {
            if (!empty($value['from'])) {
                $value['orig_from'] = $value['from'];
                $value['from'] = $this->_convertDate($this->stripTags($value['from']), $value['locale']);
            }
            if (!empty($value['to'])) {
                $value['orig_to'] = $value['to'];
                $value['to'] = $this->_convertDate($this->stripTags($value['to']), $value['locale']);
            }
        }
        if (empty($value['from']) && empty($value['to'])) {
            $value = null;
        }
        $this->setData('value', $value);
        return $this;
    }

    /**
     * Retrieve locale
     *
     * @return Mage_Core_Model_Locale
     */
    public function getLocale()
    {
        if (!$this->_locale) {
            $this->_locale = Mage::app()->getLocale();
        }
        return $this->_locale;
    }

    /**
     * Convert given date to default (UTC) timezone
     *
     * @param string $date
     * @param string $locale
     * @return string|null
     */
    protected function _convertDate($date, $locale)
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            // Validate that the date is actually valid (not just format)
            $dateTime = DateTime::createFromFormat(Mage_Core_Model_Locale::DATE_FORMAT, $date);
            if ($dateTime && $dateTime->format(Mage_Core_Model_Locale::DATE_FORMAT) === $date) {
                return Mage::app()->getLocale()->utcDate(null, $date, false, 'html5');
            }
        }

        return null;
    }
}
