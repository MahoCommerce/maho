<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Catalog_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareLayout()
    {
        $renderer = $this->getLayout()->createBlock('adminhtml/widget_form_renderer_element');
        if ($renderer instanceof \Maho\Data\Form\Element\Renderer\RendererInterface) {
            \Maho\Data\Form::setElementRenderer($renderer);
        }

        $renderer = $this->getLayout()->createBlock('adminhtml/widget_form_renderer_fieldset');
        if ($renderer instanceof \Maho\Data\Form\Element\Renderer\RendererInterface) {
            \Maho\Data\Form::setFieldsetRenderer($renderer);
        }

        $renderer = $this->getLayout()->createBlock('adminhtml/catalog_form_renderer_fieldset_element');
        if ($renderer instanceof \Maho\Data\Form\Element\Renderer\RendererInterface) {
            \Maho\Data\Form::setFieldsetElementRenderer($renderer);
        }

        return $this;
    }
}
