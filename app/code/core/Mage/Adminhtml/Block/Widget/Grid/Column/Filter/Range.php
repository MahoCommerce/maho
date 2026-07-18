<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Range extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Abstract
{
    #[\Override]
    public function getHtml()
    {
        $fromLabel = Mage::helper('adminhtml')->__('From');
        $toLabel = Mage::helper('adminhtml')->__('To');

        $html  = '<div class="range filter-range">';
        $html .= '<div class="range-line">'
            . '<span class="label" aria-hidden="true" title="' . $this->quoteEscape($fromLabel) . '">&ge;</span>'
            . '<input type="number" name="' . $this->_getHtmlName() . '[from]" id="' . $this->_getHtmlId() . '_from"'
                . ' aria-label="' . $this->quoteEscape($fromLabel) . '" title="' . $this->quoteEscape($fromLabel) . '"'
                . ' value="' . $this->getEscapedValue('from') . '" class="input-text no-changes"></div>';
        $html .= '<div class="range-line">'
            . '<span class="label" aria-hidden="true" title="' . $this->quoteEscape($toLabel) . '">&le;</span>'
            . '<input type="number" name="' . $this->_getHtmlName() . '[to]" id="' . $this->_getHtmlId() . '_to"'
                . ' aria-label="' . $this->quoteEscape($toLabel) . '" title="' . $this->quoteEscape($toLabel) . '"'
                . ' value="' . $this->getEscapedValue('to') . '" class="input-text no-changes"></div>';
        $html .= '</div>';

        return $html;
    }

    public function getValue($index = null)
    {
        if ($index) {
            return $this->getData('value', $index);
        }
        $value = $this->getData('value');
        if ((isset($value['from']) && (string) $value['from'] !== '')
            || (isset($value['to']) && (string) $value['to'] !== '')
        ) {
            return $value;
        }
        return null;
    }

    #[\Override]
    public function getCondition()
    {
        $value = $this->getValue();

        if (isset($value['from']) && isset($value['to']) && $value['from'] === $value['to']) {
            return ['eq' => $value['from']];
        }

        return $value;
    }
}
