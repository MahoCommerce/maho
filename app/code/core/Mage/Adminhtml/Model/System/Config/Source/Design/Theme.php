<?php

/**
 * Installed design theme options (union across frontend packages).
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Design_Theme
{
    public function toOptionArray(): array
    {
        $themes = [];
        foreach (Mage::getSingleton('core/design_package')->getThemeList() as $packageThemes) {
            foreach ((array) $packageThemes as $theme) {
                $themes[$theme] = true;
            }
        }

        // Skins live under public/skin and may have no app/design counterpart
        $skinRoot = Mage::getBaseDir('skin') . DS . 'frontend';
        foreach (Maho::listDirectories($skinRoot) as $package) {
            foreach (Maho::listDirectories($skinRoot . DS . $package) as $theme) {
                $themes[$theme] = true;
            }
        }
        ksort($themes);

        // Empty = fall back to the package's default theme
        $options = [['value' => '', 'label' => '']];
        foreach (array_keys($themes) as $theme) {
            $options[] = ['value' => $theme, 'label' => $theme];
        }
        return $options;
    }
}
