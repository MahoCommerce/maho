<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Customer_Edit_Renderer_Newpass extends Mage_Adminhtml_Block_Abstract implements \Maho\Data\Form\Element\Renderer\RendererInterface
{
    /**
     * Render block
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $html  = '<tr>';
        $html .= '<td class="label">' . $element->getLabelHtml() . '</td>';
        $html .= '<td class="value">' . $element->getElementHtml();
        if ($element->getNote()) {
            $html .= '<p class="note"><span>' . $element->getNote() . '</span></p>';
        }
        $html .= '<p id="email-passowrd-warning" style="display:none;" class="note"><span>' . Mage::helper('customer')->__('Warning: email containing password in plaintext will be sent.') . '</span></p>';
        $html .= '</td>';
        $html .= '</tr>' . "\n";
        $html .= '<tr>';
        $html .= '<td class="label"><label>&nbsp;</label></td>';
        $html .= '<td class="value">' . Mage::helper('customer')->__('or') . '</td>';
        $html .= '</tr>' . "\n";
        $html .= '<tr>';
        $html .= '<td class="label"><label>&nbsp;</label></td>';
        $html .= '<td class="value"><input type="checkbox" id="account-send-pass" name="'
            . $element->getName()
            . '" value="auto" onclick="setElementDisable(\''
            . $element->getHtmlId()
            . '\', this.checked)"/>&nbsp;';
        $html .= '<label for="account-send-pass">'
            . Mage::helper('customer')->__('Email Link to Set Password')
            . '</label></td>';
        $html .= '</tr>' . "\n";

        return $html;
    }
}
