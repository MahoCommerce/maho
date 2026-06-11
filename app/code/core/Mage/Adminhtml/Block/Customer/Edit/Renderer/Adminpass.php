<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Customer_Edit_Renderer_Adminpass extends Mage_Adminhtml_Block_Abstract implements \Maho\Data\Form\Element\Renderer\RendererInterface
{
    /**
     * Render block
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
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
    protected function _getScriptHtml(\Maho\Data\Form\Element\AbstractElement $element)
    {
        return <<<SCRIPT
<script>
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
