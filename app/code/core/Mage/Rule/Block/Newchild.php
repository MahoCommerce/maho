<?php

/**
 * Maho
 *
 * @package    Mage_Rule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @package    Mage_Rule
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
        $id = $element->getParamId() ? "id=\"{$element->getParamId()}\"" : '';

        return <<<HTML
            <span class="rule-param rule-param-new-child" $id>
                <a href="javascript:void(0)" class="label">{$element->getValueName()}</a>
                <span class="element">{$element->getElementHtml()}</span>
            </span>
        HTML;
    }
}
