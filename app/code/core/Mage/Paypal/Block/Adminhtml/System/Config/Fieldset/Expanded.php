<?php

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Paypal_Block_Adminhtml_System_Config_Fieldset_Expanded extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    /**
     * Return collapse state
     *
     * @param \Maho\Data\Form\Element\AbstractElement $element
     * @return bool
     */
    #[\Override]
    protected function _getCollapseState($element)
    {
        $extra = Mage::getSingleton('admin/session')->getUser()->getExtra();
        return $extra['configState'][$element->getId()] ?? true;
    }
}
