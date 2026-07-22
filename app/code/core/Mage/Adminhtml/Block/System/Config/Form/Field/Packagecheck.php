<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

/**
 * System-config field renderer that appends a "⚠️ Install <package>"
 * hint to the field's comment when its declared <mandatory_package> isn't
 * installed. Groups and heading rows get this natively from the base
 * Fieldset and Heading renderers; use this class for cases where the
 * warning belongs inline with a regular field.
 */
class Mage_Adminhtml_Block_System_Config_Form_Field_Packagecheck extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element): string
    {
        $package = $element->getOriginalData()['mandatory_package'] ?? null;
        if ($package) {
            $warning = trim(Mage::helper('core')->packageInstallWarning($package));
            if ($warning !== '') {
                $current = (string) $element->getComment();
                $element->setComment($current === '' ? $warning : $current . '<br>' . $warning);
            }
        }
        return parent::render($element);
    }
}
