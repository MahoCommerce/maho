<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Date range promo widget chooser
 * Currently works without localized format
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Promo_Widget_Chooser_Daterange extends Mage_Adminhtml_Block_Abstract
{
    /**
     * HTML ID of the element that will obtain the joined chosen values
     *
     * @var string
     */
    protected $_targetElementId = '';

    /**
     * From/To values to be rendered
     *
     * @var array
     */
    protected $_rangeValues     = ['from' => '', 'to' => ''];

    /**
     * Range string delimiter for from/to dates
     *
     * @var string
     */
    protected $_rangeDelimiter  = '...';

    /**
     * Render the chooser HTML
     * Target element should be set.
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (empty($this->_targetElementId)) {
            return '';
        }

        $idSuffix = Mage::helper('core')->uniqHash();
        $form = new Varien_Data_Form();
        foreach ([
            'from' => Mage::helper('adminhtml')->__('From'),
            'to'   => Mage::helper('adminhtml')->__('To')] as $key => $label
        ) {
            $id = "{$key}_{$idSuffix}";
            $element = new Varien_Data_Form_Element_Date([
                'format'   => Varien_Date::DATE_INTERNAL_FORMAT, // hardcode because hardcoded values delimiter
                'label'    => $label,
                'onchange' => "dateTimeChoose_{$idSuffix}()", // won't work through Event.observe()
                'value'    => $this->_rangeValues[$key],
            ]);
            $element->setId($id);
            $form->addElement($element);
        }
        return $form->toHtml() . "<script type=\"text/javascript\">
            dateTimeChoose_{$idSuffix} = function() {
                $('{$this->_targetElementId}').value = $('from_{$idSuffix}').value + '{$this->_rangeDelimiter}' + $('to_{$idSuffix}').value;
            };
            </script>";
    }

    /**
     * Target element ID setter
     *
     * @param string $value
     * @return $this
     */
    public function setTargetElementId($value)
    {
        $this->_targetElementId = trim($value);
        return $this;
    }

    /**
     * Range values setter
     *
     * @param string $from
     * @param string $to
     * @return $this
     */
    public function setRangeValues($from, $to)
    {
        $this->_rangeValues = ['from' => $from, 'to' => $to];
        return $this;
    }

    /**
     * Range values setter, string implementation.
     * Automatically attempts to split the string by delimiter
     *
     * @param string $delimitedString
     * @return $this
     */
    public function setRangeValue($delimitedString)
    {
        $split = explode($this->_rangeDelimiter, $delimitedString, 2);
        $from = $split[0];
        $to = $split[1] ?? '';
        return $this->setRangeValues($from, $to);
    }

    /**
     * Range delimiter setter
     *
     * @param string $value
     * @return $this
     */
    public function setRangeDelimiter($value)
    {
        $this->_rangeDelimiter = (string) $value;
        return $this;
    }
}
