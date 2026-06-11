<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_System_Config_Form_Field_Heading extends Mage_Adminhtml_Block_Abstract implements \Maho\Data\Form\Element\Renderer\RendererInterface
{
    /**
     * Render element html
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
    {
        return sprintf(
            '<tr class="system-fieldset-sub-head" id="row_%s"><td colspan="5"><h4 id="%s">%s</h4></td></tr>',
            $element->getHtmlId(),
            $element->getHtmlId(),
            $element->getLabel(),
        );
    }
}
