<?php

/**
 * Maho
 *
 * @package    Varien_Data
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Varien_Data_Form_Element_Radios extends Varien_Data_Form_Element_Abstract
{
    /**
     * Varien_Data_Form_Element_Radios constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setType('radios');
    }

    /**
     * @return string
     */
    public function getSeparator()
    {
        $separator = $this->getData('separator');
        if (is_null($separator)) {
            $separator = '&nbsp;';
        }
        return $separator;
    }

    /**
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $html = '';
        $value = $this->getValue();
        if ($values = $this->getValues()) {
            foreach ($values as $option) {
                $html .= $this->_optionToHtml($option, $value);
            }
        }
        $html .= $this->getAfterElementHtml();
        return $html;
    }

    /**
     * @param array|Varien_Object $option
     * @param $selected
     * @return string
     */
    protected function _optionToHtml($option, $selected)
    {
        $html = '<input type="radio"' . $this->serialize(['name', 'class', 'style']);
        if (is_array($option)) {
            $html .= 'value="' . $this->_escape($option['value']) . '"  id="' . $this->getHtmlId() . $option['value'] . '"';
            if ($option['value'] == $selected) {
                $html .= ' checked="checked"';
            }
            $html .= ' />';
            $html .= '<label class="inline" for="' . $this->getHtmlId() . $option['value'] . '">' . $option['label'] . '</label>';
        } elseif ($option instanceof Varien_Object) {
            $html .= 'id="' . $this->getHtmlId() . $option->getValue() . '"' . $option->serialize(['label', 'title', 'value', 'class', 'style']);
            if (in_array($option->getValue(), $selected)) {
                $html .= ' checked="checked"';
            }
            $html .= ' />';
            $html .= '<label class="inline" for="' . $this->getHtmlId() . $option->getValue() . '">' . $option->getLabel() . '</label>';
        }
        $html .= $this->getSeparator() . "\n";
        return $html;
    }
}
