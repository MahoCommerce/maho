<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Block for log settings: hides the section when XML configuration is present
 */
class Mage_Adminhtml_Block_System_Config_Frontend_Log extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element): string
    {
        if (Mage::getModel('core/logger')::isLogConfigManagedByXml()) {
            $html = $this->_getHeaderHtml($element);

            $html .= '<ul>';
            $html .= '<li class="notice-msg">';
            $html .= '<ul>';
            $html .= '<li><strong>' . $this->__('Logging is currently managed via XML configuration files.') . '</strong></li>';
            $html .= '</ul>';
            $html .= '</li>';
            $html .= '</ul>';

            $html .= $this->_getFooterHtml($element);
            return $html;
        }

        return parent::render($element);
    }
}
