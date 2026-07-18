<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_System_Config_Form_Field_File extends \Maho\Data\Form\Element\File
{
    /**
     * Get element html
     *
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $html = parent::getElementHtml();
        $html .= $this->_getDeleteCheckbox();
        return $html;
    }

    /**
     * Get html for additional delete checkbox field
     *
     * @return string
     */
    protected function _getDeleteCheckbox()
    {
        $html = '';
        if ((string) $this->getValue()) {
            $label = Mage::helper('adminhtml')->__('Delete File');
            $html .= '<div>' . Mage::helper('adminhtml')->escapeHtml($this->getValue()) . ' ';
            $html .= '<input type="checkbox" name="' . parent::getName() . '[delete]" value="1" class="checkbox" id="' . $this->getHtmlId() . '_delete"' . ($this->getDisabled() ? ' disabled="disabled"' : '') . '>';
            $html .= '<label for="' . $this->getHtmlId() . '_delete"' . ($this->getDisabled() ? ' class="disabled"' : '') . '> ' . $label . '</label>';
            $html .= '<input type="hidden" name="' . parent::getName() . '[value]" value="' . $this->getValue() . '">';
            $html .= '</div>';
        }
        return $html;
    }
}
