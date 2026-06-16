<?php

/**
 * Options filter rendered as a customizable <select>: icon-only when closed, icon + label when open.
 *
 * Falls back to a plain native select (showing the text labels) in browsers
 * that do not support appearance: base-select.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Iconoptions extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Select
{
    #[\Override]
    public function getHtml()
    {
        $icons = $this->getColumn()->getIcons();
        if (!is_array($icons) || empty($icons)) {
            return parent::getHtml();
        }

        $value = $this->getValue();

        $html = '<select name="' . $this->_getHtmlName() . '" id="' . $this->_getHtmlId() . '" class="no-changes grid-icon-filter">';
        $html .= '<button type="button"><selectedcontent></selectedcontent></button>';
        foreach ($this->_getOptions() as $option) {
            if (is_array($option['value'])) {
                continue;
            }
            $html .= $this->_renderIconOption($option, $value, $icons);
        }
        $html .= '</select>';
        return $html;
    }

    protected function _renderIconOption(array $option, ?string $value, array $icons): string
    {
        $optionValue = $option['value'];
        $selected = ($optionValue == $value && $value !== null) ? ' selected="selected"' : '';

        $iconSvg = '<span class="grid-icon-value"></span>';
        if ($optionValue !== null && isset($icons[$optionValue])) {
            $icon = Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Iconoptions::normalizeIcon($icons[$optionValue]);
            $class = 'grid-icon-value' . ($icon['class'] !== '' ? ' ' . $icon['class'] : '');
            $svg = '';
            foreach ($icon['icons'] as $name) {
                $svg .= $this->getIconSvg($name, $icon['variant'], 'none');
            }
            $iconSvg = '<span class="' . $this->escapeHtml($class) . '">' . $svg . '</span>';
        }

        return '<option value="' . $this->escapeHtml($optionValue) . '"' . $selected . '>'
            . $iconSvg
            . '<span class="grid-icon-filter-label">' . $this->escapeHtml($option['label']) . '</span>'
            . '</option>';
    }
}
