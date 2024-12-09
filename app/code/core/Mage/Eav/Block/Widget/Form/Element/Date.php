<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://www.openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method DateTime getTime()
 * @method $this setTime(string $value)
 */
class Mage_Eav_Block_Widget_Form_Element_Date extends Mage_Eav_Block_Widget_Form_Element_Abstract
{
    /**
     * @var array
     */
    protected $_dateInputs = [];

    #[\Override]
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('eav/widget/form/element/date.phtml');
    }

    /**
     * @param string $date
     * @return $this
     */
    public function setDate($date)
    {
        if ($date) {
            try {
                $dateTime = new DateTime($date);
                $this->setTime($dateTime);
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        $this->setData('date', $date);

        return $this;
    }

    /**
     * @return bool
     */
    public function hasTime()
    {
        return $this->getTime() instanceof DateTime;
    }

    /**
     * @return string
     */
    public function getDay()
    {
        return $this->hasTime() ? $this->getTime()->format('d') : '';
    }

    /**
     * @return string
     */
    public function getMonth()
    {
        return $this->hasTime() ? $this->getTime()->format('m') : '';
    }

    /**
     * @return string
     */
    public function getYear()
    {
        return $this->hasTime() ? $this->getTime()->format('Y') : '';
    }

    /**
     * Returns format which will be applied for date in javascript
     *
     * @return string
     */
    public function getDateFormat()
    {
        return Mage::app()->getLocale()->getDateStrFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
    }

    /**
     * Add date input html
     *
     * @param string $code
     * @param string $html
     */
    public function setDateInput($code, $html)
    {
        $this->_dateInputs[$code] = $html . "\n";
    }

    /**
     * Sort date inputs by dateformat order of current locale
     *
     * @return string
     */
    public function getSortedDateInputs()
    {
        $strtr = [
            '%b' => '%1$s',
            '%B' => '%1$s',
            '%m' => '%1$s',
            '%d' => '%2$s',
            '%e' => '%2$s',
            '%Y' => '%3$s',
            '%y' => '%3$s'
        ];

        $dateFormat = preg_replace('/[^\%\w]/', '\\1', $this->getDateFormat());

        return sprintf(
            strtr($dateFormat, $strtr),
            $this->_dateInputs['m'],
            $this->_dateInputs['d'],
            $this->_dateInputs['y']
        );
    }
}
