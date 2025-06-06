<?php

/**
 * Maho
 *
 * @package    Mage_Rule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Rule_Block_Newchild extends Mage_Core_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface
{
    /**
     * @return string
     */
    #[\Override]
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $element->addClass('element-value-changer');
        $html = '&nbsp;<span class="rule-param rule-param-new-child"' . ($element->getParamId() ? ' id="' . $element->getParamId() . '"' : '') . '>';
        $html .= '<a href="javascript:void(0)" class="label">';
        $html .= $element->getValueName();
        $html .= '</a><span class="element">';
        $html .= $element->getElementHtml();
        $html .= '</span></span>&nbsp;';
        return $html;
    }
}
