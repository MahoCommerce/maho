<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Date grid column filter
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @todo       date format
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
        $format = $this->getLocale()->getDateStrFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
        $html = '<div class="range"><div class="range-line date">'
            . '<span class="label">' . $fromLabel . '</span>'
            . '<input type="text" name="' . $this->_getHtmlName() . '[from]" id="' . $htmlId . '_from"'
                . ' placeholder="' . $fromLabel . '"'
                . ' value="' . $this->getEscapedValue('from') . '" class="input-text no-changes"/>'
            . '</div>';
        $html .= '<div class="range-line date">'
            . '<span class="label">' . $toLabel . '</span>'
            . '<input type="text" name="' . $this->_getHtmlName() . '[to]" id="' . $htmlId . '_to"'
                . ' placeholder="' . $fromLabel . '"'
                . ' value="' . $this->getEscapedValue('to') . '" class="input-text no-changes"/>'
            . '</div></div>';
        $html .= '<input type="hidden" name="' . $this->_getHtmlName() . '[locale]"'
            . 'value="' . $this->getLocale()->getLocaleCode() . '"/>';
        $html .= '<script type="text/javascript">
            Calendar.setup({
                inputField : "' . $htmlId . '_from",
                ifFormat : "' . $format . '"
            });
            Calendar.setup({
                inputField : "' . $htmlId . '_to",
                ifFormat : "' . $format . '"
            });
        </script>';
        return $html;
    }

    #[\Override]
    public function getEscapedValue($index = null)
    {
        $value = $this->getValue($index);
        if ($value instanceof Zend_Date) {
            return $value->toString($this->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT));
        }
        return $value;
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
     * @return Zend_Date|null
     */
    protected function _convertDate($date, $locale)
    {
        try {
            $dateObj = $this->getLocale()->date(null, null, $locale, false);

            //set default timezone for store (admin)
            $dateObj->setTimezone(
                Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE)
            );

            //set beginning of day
            $dateObj->setHour(00);
            $dateObj->setMinute(00);
            $dateObj->setSecond(00);

            //set date with applying timezone of store
            $dateObj->set($date, Zend_Date::DATE_SHORT, $locale);

            //convert store date to default date in UTC timezone without DST
            $dateObj->setTimezone(Mage_Core_Model_Locale::DEFAULT_TIMEZONE);

            return $dateObj;
        } catch (Exception $e) {
            return null;
        }
    }
}
