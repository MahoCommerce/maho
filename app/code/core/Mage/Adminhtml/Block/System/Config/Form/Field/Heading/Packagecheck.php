<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_System_Config_Form_Field_Heading_Packagecheck extends Mage_Adminhtml_Block_System_Config_Form_Field_Heading implements \Maho\Data\Form\Element\Renderer\RendererInterface
{
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element): string
    {
        $originalData = $element->getOriginalData();
        $package = $originalData['mandatory_package'] ?? null;
        if ($package) {
            $element->setLabel($element->getLabel() . Mage::helper('core')->packageInstallWarning($package, '<br>'));
        }

        return parent::render($element);
    }
}
