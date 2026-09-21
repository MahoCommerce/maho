<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Button extends Mage_Adminhtml_Block_Widget
{
    public function getType()
    {
        return ($type = $this->getData('type')) ? $type : 'button';
    }

    public function getOnClick()
    {
        if (!$this->getData('on_click')) {
            return $this->getData('onclick');
        }
        return $this->getData('on_click');
    }

    #[\Override]
    protected function _toHtml()
    {
        $helper = Mage::helper('core');

        return $this->getBeforeHtml() . '<button '
            . ($this->getId() ? ' id="' . $helper->quoteEscape($this->getId()) . '"' : '')
            . ($this->getElementName() ? ' name="' . $helper->quoteEscape($this->getElementName()) . '"' : '')
            . ' title="'
            . $helper->quoteEscape($this->getTitle() ?: $this->getLabel())
            . '"'
            . ' type="' . $helper->quoteEscape($this->getType()) . '"'
            . ' class="scalable ' . $helper->quoteEscape($this->getClass()) . ($this->getDisabled() ? ' disabled' : '') . '"'
            . ' onclick="' . $helper->quoteEscape($this->getOnClick()) . '"'
            . ' style="' . $helper->quoteEscape($this->getStyle()) . '"'
            . ($this->getValue() ? ' value="' . $helper->quoteEscape($this->getValue()) . '"' : '')
            . ($this->getDisabled() ? ' disabled="disabled"' : '')
            . '>' . $this->getLabel() . '</button>' . $this->getAfterHtml();
    }
}
