<?php

/**
 * Skin theme select, with a palette swatch for every installed theme.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_System_Config_Form_Field_Design_Skin extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    protected function _getElementHtml(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $html = parent::_getElementHtml($element);

        // Every package: the configured one may differ from the one selected in the form
        $root = Mage::getBaseDir('skin') . DS . 'frontend';
        $palettes = [];
        foreach (Maho::listDirectories($root) as $package) {
            foreach (Maho::listDirectories($root . DS . $package) as $theme) {
                $palettes[$package][$theme] = array_values(Mage_Core_Model_Design_Tokens::paletteOf($package, $theme));
            }
        }
        if (!$palettes) {
            return $html;
        }

        $config = Mage::helper('core')->jsonEncode([
            'selectId' => $element->getHtmlId(),
            'packageId' => str_replace('theme_skin', 'package_name', (string) $element->getHtmlId()),
            'palettes' => $palettes,
        ]);

        return $html . <<<HTML
            <script>MahoDesignTokens.initThemeSelect({$config});</script>
            HTML;
    }
}
