<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
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
    document.querySelectorAll('#_accountnew_password,#account-send-pass').forEach(function(elem) {
        elem.addEventListener('change', function() {
            var newPasswordField = document.getElementById('_accountnew_password');
            var sendPassField = document.getElementById('account-send-pass');
            if ((newPasswordField && newPasswordField.value) || (sendPassField && sendPassField.checked)) {
                document.getElementById('{$element->getHtmlId()}_container').style.display = '';
                document.getElementById('{$element->getHtmlId()}').disabled = false;
            } else {
                document.getElementById('{$element->getHtmlId()}_container').style.display = 'none';
                document.getElementById('{$element->getHtmlId()}').disabled = true;
            }
            var warningField = document.getElementById('email-passowrd-warning');
            if (warningField) {
                if (!(newPasswordField && newPasswordField.value) || (sendPassField && sendPassField.checked)) {
                    warningField.style.display = 'none';
                } else if (newPasswordField && newPasswordField.value) {
                    warningField.style.display = '';
                }
            }
        });
        elem.addEventListener('focus', function() {
            document.getElementById('{$element->getHtmlId()}_container').style.display = '';
            document.getElementById('{$element->getHtmlId()}').disabled = false;
        });
        elem.addEventListener('blur', function() {
            var newPasswordField = document.getElementById('_accountnew_password');
            var sendPassField = document.getElementById('account-send-pass');
            if (!(newPasswordField && newPasswordField.value) && !(sendPassField && sendPassField.checked)) {
                document.getElementById('{$element->getHtmlId()}_container').style.display = 'none';
                document.getElementById('{$element->getHtmlId()}').disabled = true;
            }
        });
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById('{$element->getHtmlId()}_container').style.display = 'none';
            document.getElementById('{$element->getHtmlId()}').disabled = true;
        });
    });
</script>
SCRIPT;
    }
}
