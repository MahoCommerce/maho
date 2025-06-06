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

class Mage_Adminhtml_Block_Customer_Edit_Renderer_Adminpass extends Mage_Adminhtml_Block_Abstract implements Varien_Data_Form_Element_Renderer_Interface
{
    /**
     * Render block
     *
     * @return string
     */
    #[\Override]
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html  = '<tr id="' . $element->getHtmlId() . '_container">';
        $html .= '<td class="label">' . $element->getLabelHtml() . '</td>';
        $html .= '<td class="value">' . $element->getElementHtml() . ' ' . $this->_getScriptHtml($element) . '</td>';
        $html .= '</tr>' . "\n";
        $html .= '<tr>';

        return $html;
    }

    /**
     * @return string
     */
    protected function _getScriptHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return <<<SCRIPT
<script type="text/javascript">
//<![CDATA[
    $$('#_accountnew_password,#account-send-pass').each(function(elem) {
        $(elem).on('change', function() {
            if ($('_accountnew_password').getValue() || $('account-send-pass').checked) {
                $('{$element->getHtmlId()}_container').show();
                $('{$element->getHtmlId()}').enable();
            } else {
                $('{$element->getHtmlId()}_container').hide();
                $('{$element->getHtmlId()}').disable();
            }
            if ($('email-passowrd-warning')) {
                if (!$('_accountnew_password').getValue() || $('account-send-pass').checked) {
                    $('email-passowrd-warning').hide();
                } else if ($('_accountnew_password').getValue()) {
                    $('email-passowrd-warning').show();
                }
            }
        });
        $(elem).on('focus', function() {
            $('{$element->getHtmlId()}_container').show();
            $('{$element->getHtmlId()}').enable();
        });
        $(elem).on('blur', function() {
            if (!$('_accountnew_password').getValue() && !$('account-send-pass').checked) {
                $('{$element->getHtmlId()}_container').hide();
                $('{$element->getHtmlId()}').disable();
            }
        });
        document.observe("dom:loaded", function() {
            $('{$element->getHtmlId()}_container').hide();
            $('{$element->getHtmlId()}').disable();
        });
    });
//]]></script>
SCRIPT;
    }
}
