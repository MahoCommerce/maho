<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Rule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_Rule
 */
class Mage_Rule_Block_Conditions implements Varien_Data_Form_Element_Renderer_Interface
{
    /**
     * @return string
     */
    #[\Override]
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        if ($element->getRule() && $element->getRule()->getConditions()) {
            return $element->getRule()->getConditions()->asHtmlRecursive();
        }
        return '';
    }
}
