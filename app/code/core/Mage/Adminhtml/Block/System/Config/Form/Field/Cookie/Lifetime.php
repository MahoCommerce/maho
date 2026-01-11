<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_System_Config_Form_Field_Cookie_Lifetime extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element): string
    {
        if ($element->getHtmlId() === 'admin_security_session_cookie_lifetime') {
            $min = Mage_Adminhtml_Controller_Action::SESSION_MIN_LIFETIME;
            $max = Mage_Adminhtml_Controller_Action::SESSION_MAX_LIFETIME;
        } else {
            $min = Mage_Core_Controller_Front_Action::SESSION_MIN_LIFETIME;
            $max = Mage_Core_Controller_Front_Action::SESSION_MAX_LIFETIME;
        }

        $element->setComment(Mage::helper('core')->__('Value must be between %d and %d', $min, $max));
        $element->addClass("validate-digits validate-digits-range digits-range-$min-$max");

        return parent::render($element);
    }
}
