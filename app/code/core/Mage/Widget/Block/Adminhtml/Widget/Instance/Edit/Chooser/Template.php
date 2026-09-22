<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Widget
 */

/**
 * Widget Instance template chooser
 */
class Mage_Widget_Block_Adminhtml_Widget_Instance_Edit_Chooser_Template extends Mage_Adminhtml_Block_Widget
{
    /**
     * Prepare html output
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (!$this->getWidgetTemplates()) {
            $html = '<p class="nm"><small>' . Mage::helper('widget')->__('Please Select Block Reference First') . '</small></p>';
        } elseif (count($this->getWidgetTemplates()) == 1) {
            $widgetTemplate = current($this->getWidgetTemplates());
            $html = '<input type="hidden" name="template" value="' . $widgetTemplate['value'] . '">';
            $html .= $widgetTemplate['label'];
        } else {
            $html = $this->getLayout()->createBlock('core/html_select')
                ->setName('template')
                ->setClass('select')
                ->setOptions($this->getWidgetTemplates())
                ->setValue($this->getSelected())->toHtml();
        }
        return parent::_toHtml() . $html;
    }

    public function getSelected(): ?string
    {
        $value = $this->getData('selected');
        return $value === null ? null : (string) $value;
    }

    public function setSelected(?string $value): static
    {
        return $this->setData('selected', $value);
    }

    public function getWidgetTemplates(): ?array
    {
        return $this->getData('widget_templates');
    }

    public function setWidgetTemplates(?array $value): static
    {
        return $this->setData('widget_templates', $value);
    }
}
