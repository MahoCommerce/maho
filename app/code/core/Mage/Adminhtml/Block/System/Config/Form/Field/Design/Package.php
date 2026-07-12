<?php

/**
 * Design package select that filters the theme selects below it to the
 * themes actually installed in the chosen package.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_System_Config_Form_Field_Design_Package extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    #[\Override]
    protected function _getElementHtml(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $html = parent::_getElementHtml($element);

        // Two inventories per package: templates/layout/translations/default
        // resolve against app/design, the skin field against public/skin -
        // a theme can exist in one root without the other
        $design = Mage::getSingleton('core/design_package');
        $skinRoot = Mage::getBaseDir('skin') . DS . 'frontend';
        $map = [];
        foreach ($design->getPackageList() as $package) {
            $designThemes = $design->getThemeList($package);
            $skinThemes = Maho::listDirectories($skinRoot . DS . $package);
            sort($designThemes);
            sort($skinThemes);
            $map[$package] = ['design' => $designThemes, 'skin' => $skinThemes];
        }

        $htmlId = $element->getHtmlId();
        /** @var Mage_Core_Helper_Data $helper */
        $helper = Mage::helper('core');
        $mapJson = $helper->jsonEncode($map);
        $missingLabel = $helper->jsonEncode($this->__(' (not installed in this package)'));

        return $html . <<<HTML
            <script>
            (function () {
                const packageSelect = document.getElementById('{$htmlId}');
                if (!packageSelect || packageSelect.tagName !== 'SELECT') {
                    return;
                }
                const themesByPackage = {$mapJson};
                const missingLabel = {$missingLabel};
                // resolved lazily: this script renders with the package field,
                // before the theme selects exist in the DOM
                const themeFields = ['theme_locale', 'theme_template', 'theme_skin', 'theme_layout', 'theme_default'];

                function syncThemeOptions() {
                    const inventories = themesByPackage[packageSelect.value] || { design: [], skin: [] };
                    themeFields.forEach((field) => {
                        const select = document.getElementById(packageSelect.id.replace('package_name', field));
                        if (!select || select.tagName !== 'SELECT') {
                            return;
                        }
                        const themes = field === 'theme_skin' ? inventories.skin : inventories.design;
                        const current = select.value;
                        const add = (value, label) => {
                            const option = document.createElement('option');
                            option.value = value;
                            option.textContent = label;
                            select.add(option);
                        };
                        select.textContent = '';
                        add('', '');
                        themes.forEach((theme) => add(theme, theme));
                        // keep a configured-but-missing value selectable so an
                        // unrelated save cannot silently drop it
                        if (current && !themes.includes(current)) {
                            add(current, current + missingLabel);
                        }
                        select.value = current;
                    });
                }

                packageSelect.addEventListener('change', syncThemeOptions);
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', syncThemeOptions);
                } else {
                    syncThemeOptions();
                }
            })();
            </script>
            HTML;
    }
}
