<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Store_Switcher_Form_Renderer_Fieldset extends Mage_Adminhtml_Block_Template implements \Maho\Data\Form\Element\Renderer\RendererInterface
{
    /**
     * Form element which re-rendering
     */
    protected ?\Maho\Data\Form\Element\AbstractElement $_element = null;

    #[\Override]
    protected function _construct()
    {
        $this->setTemplate('store/switcher/form/renderer/fieldset.phtml');
    }

    public function getElement(): ?\Maho\Data\Form\Element\AbstractElement
    {
        return $this->_element;
    }

    /**
     * Render element
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $this->_element = $element;
        return $this->toHtml();
    }
}
