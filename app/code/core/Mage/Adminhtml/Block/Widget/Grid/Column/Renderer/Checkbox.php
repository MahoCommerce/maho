<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Checkbox extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    #[\Override]
    protected ?int $_defaultWidth = 55;
    protected ?array $_values = null;

    /**
     * Returns values of the column
     *
     * @return array
     */
    public function getValues()
    {
        $this->_values ??= $this->getColumn()->getData('values') ?: [];
        return $this->_values;
    }
    #[\Override]
    public function render(\Maho\DataObject $row): string
    {
        $values = $this->getColumn()->getValues();
        $value  = $row->getData($this->getColumn()->getIndex());
        if (is_array($values)) {
            $checked = in_array($value, $values);
        } else {
            $checked = $value === $this->getColumn()->getValue();
        }

        $disabledValues = $this->getColumn()->getDisabledValues();
        if (is_array($disabledValues)) {
            $disabled = in_array($value, $disabledValues);
        } else {
            $disabled = $value === $this->getColumn()->getDisabledValue();
        }

        $this->setDisabled($disabled);

        if ($this->getNoObjectId() || $this->getColumn()->getUseIndex()) {
            $v = $value;
        } else {
            $v = ($row->getId() != '') ? $row->getId() : $value;
        }

        return $this->_getCheckboxHtml((string) $v, $checked);
    }

    protected function _getCheckboxHtml(string $value, bool $checked): string
    {
        $html = '<input type="checkbox" ';
        $html .= 'name="' . $this->getColumn()->getFieldName() . '" ';
        $html .= 'value="' . $this->escapeHtml($value) . '" ';
        $html .= 'class="' . ($this->getColumn()->getInlineCss() ?: 'checkbox') . '"';
        $html .= ($checked ? ' checked="checked"' : '') . ($this->getDisabled() ? ' disabled="disabled"' : '') . '>';
        return $html;
    }

    /**
     * Renders header of the column
     *
     * @return string
     */
    #[\Override]
    public function renderHeader()
    {
        if ($this->getColumn()->getHeader()) {
            return parent::renderHeader();
        }

        $checked = '';
        if ($filter = $this->getColumn()->getFilter()) {
            $checked = $filter->getValue() ? ' checked="checked"' : '';
        }

        $disabled = '';
        if ($this->getColumn()->getDisabled()) {
            $disabled = ' disabled="disabled"';
        }
        $html = '<input type="checkbox" ';
        $html .= 'name="' . $this->getColumn()->getFieldName() . '" ';
        $html .= 'onclick="' . $this->getColumn()->getGrid()->getJsObjectName() . '.checkCheckboxes(this)" ';
        $html .= 'class="checkbox"' . $checked . $disabled . ' ';
        $html .= 'title="' . Mage::helper('adminhtml')->__('Select All') . '">';
        return $html;
    }
}
